<?php

declare(strict_types=1);

/**
 * Reporting suite (Build Prompt 3.3 / PRD §6.7 / Tech Spec §10, §14). Every
 * method here is a read-only, date-filtered query — no writes, so unlike the
 * rest of the app these never touch AuditLogger (CLAUDE.md rule 3 only
 * applies to state-changing actions; viewing a report changes nothing).
 *
 * Tech Spec §10: "the same subquery pattern is reused with a WHERE date
 * BETWEEN filter, so opening balance and movement-in-period both come from
 * one consistent calculation path." balanceAsOf() is that one path — every
 * other method below either calls it directly or mirrors its exact
 * status/is_historical predicates, so a figure never gets computed two
 * different ways in two different reports.
 */
class Report
{
    /** v1 is a single-float system (PRD §4) — the first active fund account. */
    public static function primaryFundAccountId(): int
    {
        $stmt = Database::connection()->query(
            'SELECT id FROM fund_account WHERE is_active = 1 ORDER BY id LIMIT 1'
        );
        $id = $stmt->fetchColumn();
        return $id !== false ? (int) $id : 1;
    }

    /**
     * Balance as of a given date (inclusive) — identical predicates to the
     * fund_balances VIEW (schema.sql §11), just date-bounded. This is the
     * single calculation path every "opening balance" / "closing balance"
     * figure in the report suite goes through.
     */
    public static function balanceAsOf(int $fundAccountId, string $asOfDate): array
    {
        $stmt = Database::connection()->prepare(
            "SELECT
                COALESCE((SELECT SUM(amount) FROM fund_topups
                          WHERE fund_account_id = :fa1 AND (status = 'approved' OR is_historical = 1)
                            AND date <= :d1), 0) AS total_topups,
                COALESCE((SELECT SUM(amount) FROM expenses
                          WHERE fund_account_id = :fa2 AND (status IN ('approved','paid') OR is_historical = 1)
                            AND date <= :d2), 0) AS total_spent"
        );
        $stmt->execute([
            'fa1' => $fundAccountId, 'd1' => $asOfDate,
            'fa2' => $fundAccountId, 'd2' => $asOfDate,
        ]);
        $row = $stmt->fetch();
        $topups = (float) $row['total_topups'];
        $spent = (float) $row['total_spent'];

        return ['total_topups' => $topups, 'total_spent' => $spent, 'balance' => $topups - $spent];
    }

    /**
     * Funds received / spent within [from, to] — the same predicates as
     * balanceAsOf(), just summed over a range instead of as-of a single
     * date. Used by the dashboard's period-scoped KPI cards (CLAUDE.md
     * rule 4: date-filtered equivalent of the fund_balances view, never a
     * separately-maintained figure).
     */
    public static function periodTotals(int $fundAccountId, string $from, string $to): array
    {
        $stmt = Database::connection()->prepare(
            "SELECT COALESCE(SUM(amount), 0) AS total
             FROM fund_topups
             WHERE fund_account_id = ? AND (status = 'approved' OR is_historical = 1)
               AND date BETWEEN ? AND ?"
        );
        $stmt->execute([$fundAccountId, $from, $to]);
        $topups = (float) $stmt->fetchColumn();

        $stmt = Database::connection()->prepare(
            "SELECT COALESCE(SUM(amount), 0) AS total
             FROM expenses
             WHERE fund_account_id = ? AND (status IN ('approved','paid') OR is_historical = 1)
               AND date BETWEEN ? AND ?"
        );
        $stmt->execute([$fundAccountId, $from, $to]);
        $spent = (float) $stmt->fetchColumn();

        return ['total_topups' => $topups, 'total_spent' => $spent];
    }

    /**
     * The core report (PRD §6.7 / Tech Spec §14): opening balance, funds
     * received in range, approved expenditure in range, outstanding
     * liabilities (approved-but-unpaid), closing balance.
     */
    public static function statementOfExpenditure(int $fundAccountId, string $from, string $to): array
    {
        $openingAsOf = (new DateTimeImmutable($from))->modify('-1 day')->format('Y-m-d');
        $opening = self::balanceAsOf($fundAccountId, $openingAsOf)['balance'];
        $closing = self::balanceAsOf($fundAccountId, $to)['balance'];

        $stmt = Database::connection()->prepare(
            "SELECT COALESCE(SUM(amount), 0) AS total,
                    COALESCE(SUM(CASE WHEN is_historical = 1 THEN amount ELSE 0 END), 0) AS historical_total,
                    COUNT(*) AS cnt
             FROM fund_topups
             WHERE fund_account_id = ? AND (status = 'approved' OR is_historical = 1)
               AND date BETWEEN ? AND ?"
        );
        $stmt->execute([$fundAccountId, $from, $to]);
        $received = $stmt->fetch();

        $stmt = Database::connection()->prepare(
            "SELECT COALESCE(SUM(amount), 0) AS total,
                    COALESCE(SUM(CASE WHEN is_historical = 1 THEN amount ELSE 0 END), 0) AS historical_total,
                    COUNT(*) AS cnt
             FROM expenses
             WHERE fund_account_id = ? AND (status IN ('approved','paid') OR is_historical = 1)
               AND date BETWEEN ? AND ?"
        );
        $stmt->execute([$fundAccountId, $from, $to]);
        $spent = $stmt->fetch();

        // "Outstanding liabilities" = approved but not yet paid, as of the
        // report's closing date — a snapshot, not a within-range movement
        // (an expense approved last quarter and still unpaid is still owed
        // today), matching the Payables report's own definition below.
        $stmt = Database::connection()->prepare(
            "SELECT COALESCE(SUM(amount), 0) AS total, COUNT(*) AS cnt
             FROM expenses
             WHERE fund_account_id = ? AND status = 'approved' AND date <= ?"
        );
        $stmt->execute([$fundAccountId, $to]);
        $liabilities = $stmt->fetch();

        return [
            'from' => $from,
            'to' => $to,
            'opening_balance' => $opening,
            'funds_received' => (float) $received['total'],
            'funds_received_count' => (int) $received['cnt'],
            'has_historical_funds' => (float) $received['historical_total'] > 0,
            'expenditure' => (float) $spent['total'],
            'expenditure_count' => (int) $spent['cnt'],
            'has_historical_expenditure' => (float) $spent['historical_total'] > 0,
            'outstanding_liabilities' => (float) $liabilities['total'],
            'outstanding_liabilities_count' => (int) $liabilities['cnt'],
            'closing_balance' => $closing,
        ];
    }

    /**
     * Revenue (approved invoices, gross/VAT/WHT) less expenditure in range.
     * VAT collected is a FIRS pass-through, not company income, so net
     * revenue excludes it; WHT is withheld at source by the client so it
     * reduces cash actually received — both are broken out on the report
     * rather than silently netted, so a reviewer can see the full picture.
     */
    public static function profitLoss(int $fundAccountId, string $from, string $to): array
    {
        $stmt = Database::connection()->prepare(
            "SELECT COALESCE(SUM(amount), 0) AS gross,
                    COALESCE(SUM(vat_amount), 0) AS vat,
                    COALESCE(SUM(wht_amount), 0) AS wht,
                    COUNT(*) AS cnt
             FROM invoices
             WHERE approval_status = 'approved' AND date BETWEEN ? AND ?"
        );
        $stmt->execute([$from, $to]);
        $revenue = $stmt->fetch();
        $netRevenue = (float) $revenue['gross'] - (float) $revenue['wht'];

        $stmt = Database::connection()->prepare(
            "SELECT COALESCE(SUM(amount), 0) AS total,
                    COALESCE(SUM(CASE WHEN is_historical = 1 THEN amount ELSE 0 END), 0) AS historical_total,
                    COUNT(*) AS cnt
             FROM expenses
             WHERE fund_account_id = ? AND (status IN ('approved','paid') OR is_historical = 1)
               AND date BETWEEN ? AND ?"
        );
        $stmt->execute([$fundAccountId, $from, $to]);
        $expenses = $stmt->fetch();

        return [
            'from' => $from,
            'to' => $to,
            'gross_revenue' => (float) $revenue['gross'],
            'vat_collected' => (float) $revenue['vat'],
            'wht_withheld' => (float) $revenue['wht'],
            'net_revenue' => $netRevenue,
            'invoice_count' => (int) $revenue['cnt'],
            'total_expenses' => (float) $expenses['total'],
            'expense_count' => (int) $expenses['cnt'],
            'has_historical_expenses' => (float) $expenses['historical_total'] > 0,
            'net_profit' => $netRevenue - (float) $expenses['total'],
        ];
    }

    /**
     * Actual cash movement, distinct from the Statement of Expenditure's
     * accrual view (which counts an expense the moment it's approved).
     * Cash in = top-ups dated in range; cash out = payment vouchers actually
     * disbursed in range, PLUS historical expenses (already spent in the
     * real world at their recorded date, never routed through a voucher).
     */
    public static function cashFlow(int $fundAccountId, string $from, string $to): array
    {
        $stmt = Database::connection()->prepare(
            "SELECT COALESCE(SUM(amount), 0) AS total,
                    COALESCE(SUM(CASE WHEN is_historical = 1 THEN amount ELSE 0 END), 0) AS historical_total,
                    COUNT(*) AS cnt
             FROM fund_topups
             WHERE fund_account_id = ? AND (status = 'approved' OR is_historical = 1)
               AND date BETWEEN ? AND ?"
        );
        $stmt->execute([$fundAccountId, $from, $to]);
        $cashIn = $stmt->fetch();

        $stmt = Database::connection()->prepare(
            "SELECT COALESCE(SUM(e.amount), 0) AS total, COUNT(*) AS cnt
             FROM expenses e
             JOIN payment_vouchers pv ON pv.expense_id = e.id
             WHERE e.fund_account_id = ? AND DATE(pv.paid_at) BETWEEN ? AND ?"
        );
        $stmt->execute([$fundAccountId, $from, $to]);
        $paidOut = $stmt->fetch();

        $stmt = Database::connection()->prepare(
            "SELECT COALESCE(SUM(amount), 0) AS total, COUNT(*) AS cnt
             FROM expenses
             WHERE fund_account_id = ? AND is_historical = 1 AND date BETWEEN ? AND ?"
        );
        $stmt->execute([$fundAccountId, $from, $to]);
        $historicalOut = $stmt->fetch();

        $cashOut = (float) $paidOut['total'] + (float) $historicalOut['total'];

        return [
            'from' => $from,
            'to' => $to,
            'cash_in' => (float) $cashIn['total'],
            'cash_in_count' => (int) $cashIn['cnt'],
            'has_historical_in' => (float) $cashIn['historical_total'] > 0,
            'cash_out' => $cashOut,
            'cash_out_paid_count' => (int) $paidOut['cnt'],
            'cash_out_historical_count' => (int) $historicalOut['cnt'],
            'has_historical_out' => (float) $historicalOut['total'] > 0,
            'net_cash_flow' => (float) $cashIn['total'] - $cashOut,
        ];
    }

    /**
     * Expenditure grouped by department, same status/is_historical
     * predicates as the Statement of Expenditure's expenditure figure so the
     * two reports never disagree on what counts as "spent."
     */
    public static function departmentalExpenses(int $fundAccountId, string $from, string $to): array
    {
        $stmt = Database::connection()->prepare(
            "SELECT d.id, d.name,
                    COALESCE(SUM(e.amount), 0) AS total,
                    COALESCE(SUM(CASE WHEN e.is_historical = 1 THEN e.amount ELSE 0 END), 0) AS historical_total,
                    COUNT(e.id) AS cnt
             FROM departments d
             LEFT JOIN expenses e ON e.department_id = d.id AND e.fund_account_id = ?
                 AND (e.status IN ('approved','paid') OR e.is_historical = 1)
                 AND e.date BETWEEN ? AND ?
             GROUP BY d.id, d.name
             ORDER BY total DESC, d.name ASC"
        );
        $stmt->execute([$fundAccountId, $from, $to]);
        $rows = $stmt->fetchAll();

        $grandTotal = 0.0;
        foreach ($rows as &$r) {
            $r['total'] = (float) $r['total'];
            $r['historical_total'] = (float) $r['historical_total'];
            $r['has_historical'] = $r['historical_total'] > 0;
            $r['cnt'] = (int) $r['cnt'];
            $grandTotal += $r['total'];
        }
        unset($r);
        foreach ($rows as &$r) {
            $r['pct'] = $grandTotal > 0 ? ($r['total'] / $grandTotal * 100) : 0.0;
        }
        unset($r);

        return ['from' => $from, 'to' => $to, 'rows' => $rows, 'grand_total' => $grandTotal];
    }

    /**
     * Per-department monthly budgets, stored in the key-value `settings`
     * table (schema.sql §10) under `budget_department_{id}` — there is no
     * dedicated budgets table in schema.sql, and CLAUDE.md rule says table
     * structure isn't touched without being explicitly asked, so this reuses
     * the general-purpose config table rather than inventing a new one.
     *
     * @return array<int, float> department_id => monthly budget amount
     */
    public static function departmentBudgets(): array
    {
        $map = [];
        foreach (Setting::allByPrefix('budget_department_') as $key => $value) {
            $deptId = (int) substr($key, strlen('budget_department_'));
            if ($deptId > 0) {
                $map[$deptId] = (float) $value;
            }
        }
        return $map;
    }

    public static function setDepartmentBudget(int $departmentId, float $monthlyAmount, ?int $updatedBy = null): void
    {
        Setting::set('budget_department_' . $departmentId, number_format($monthlyAmount, 2, '.', ''), $updatedBy);
    }

    /**
     * Budgets are configured as a monthly figure (the natural cadence for
     * this size of operation); the selected report range is prorated against
     * an average 30.44-day month so any date range — not just calendar
     * months — gets a defensible comparison figure. That assumption is
     * surfaced in the UI, never silently baked in.
     */
    public static function budgetVsActual(int $fundAccountId, string $from, string $to): array
    {
        $dept = self::departmentalExpenses($fundAccountId, $from, $to);
        $budgets = self::departmentBudgets();

        $daysInRange = (new DateTimeImmutable($from))->diff(new DateTimeImmutable($to))->days + 1;
        $proration = $daysInRange / 30.44;

        $totalBudget = 0.0;
        $rows = [];
        foreach ($dept['rows'] as $r) {
            $monthlyBudget = $budgets[(int) $r['id']] ?? null;
            $proratedBudget = $monthlyBudget !== null ? $monthlyBudget * $proration : null;
            if ($proratedBudget !== null) {
                $totalBudget += $proratedBudget;
            }
            $rows[] = $r + [
                'monthly_budget' => $monthlyBudget,
                'budget' => $proratedBudget,
                'variance' => $proratedBudget !== null ? $proratedBudget - $r['total'] : null,
            ];
        }

        return [
            'from' => $from,
            'to' => $to,
            'days_in_range' => $daysInRange,
            'rows' => $rows,
            'grand_total_actual' => $dept['grand_total'],
            'grand_total_budget' => $totalBudget,
        ];
    }

    /**
     * Money owed TO DOTT TV: approved invoices not yet fully paid, dated in
     * range. Distinct listing from the P&L's revenue total — this one is a
     * receivables ledger (who owes what, since when), not a period summary.
     */
    public static function receivables(string $from, string $to): array
    {
        $stmt = Database::connection()->prepare(
            "SELECT i.id, i.invoice_no, i.date, i.client, i.amount, i.due_date, i.payment_status,
                    d.name AS department_name
             FROM invoices i
             LEFT JOIN departments d ON d.id = i.department_id
             WHERE i.approval_status = 'approved' AND i.payment_status IN ('unpaid','partial')
               AND i.date BETWEEN ? AND ?
             ORDER BY (i.due_date IS NULL), i.due_date ASC, i.date ASC"
        );
        $stmt->execute([$from, $to]);
        $rows = $stmt->fetchAll();

        $today = date('Y-m-d');
        $total = 0.0;
        $overdueTotal = 0.0;
        foreach ($rows as &$r) {
            $r['amount'] = (float) $r['amount'];
            $r['is_overdue'] = $r['due_date'] !== null && $r['due_date'] < $today;
            $total += $r['amount'];
            if ($r['is_overdue']) {
                $overdueTotal += $r['amount'];
            }
        }
        unset($r);

        return ['from' => $from, 'to' => $to, 'rows' => $rows, 'total' => $total, 'overdue_total' => $overdueTotal];
    }

    /**
     * Money DOTT TV owes: approved-but-unpaid expenses as of the report's
     * end date — the same "outstanding liabilities" definition the
     * Statement of Expenditure summarizes, shown here as the full itemized
     * list behind that number.
     */
    public static function payables(int $fundAccountId, string $to): array
    {
        $stmt = Database::connection()->prepare(
            "SELECT e.id, e.expense_no, e.date, e.payee, e.amount, e.is_historical,
                    d.name AS department_name
             FROM expenses e
             LEFT JOIN departments d ON d.id = e.department_id
             WHERE e.fund_account_id = ? AND e.status = 'approved' AND e.date <= ?
             ORDER BY e.date ASC, e.id ASC"
        );
        $stmt->execute([$fundAccountId, $to]);
        $rows = $stmt->fetchAll();

        $total = 0.0;
        $hasHistorical = false;
        foreach ($rows as &$r) {
            $r['amount'] = (float) $r['amount'];
            $total += $r['amount'];
            if ((int) $r['is_historical'] === 1) {
                $hasHistorical = true;
            }
        }
        unset($r);

        return ['to' => $to, 'rows' => $rows, 'total' => $total, 'has_historical' => $hasHistorical];
    }

    /**
     * Fund Ledger (Build Prompt 3.3a / Tech Spec §14a / PRD §6.7) — the one
     * report deliberately NOT filtered by department: a single continuous
     * chronological list of every approved/paid expense and every approved
     * top-up, with a running balance after each row. The running balance is
     * computed in SQL via a window function (MySQL 8) exactly as §14a
     * specifies — never a PHP loop, so the balance math has one source of
     * truth. Positional parameters are used (not the spec's named ones) so
     * the query works with emulated prepares OFF, which forbids reusing a
     * named parameter.
     */
    public static function fundLedger(int $fundAccountId, string $from, string $to): array
    {
        $stmt = Database::connection()->prepare(
            "SELECT
                ledger.date,
                ledger.created_at,
                ledger.type,
                ledger.document_no,
                ledger.payee,
                ledger.department_id,
                d.name AS department_name,
                ledger.description,
                ledger.amount_in,
                ledger.amount_out,
                ledger.is_historical,
                SUM(ledger.amount_in - ledger.amount_out)
                    OVER (ORDER BY ledger.date, ledger.created_at ROWS UNBOUNDED PRECEDING) AS running_balance
             FROM (
                 SELECT date, created_at, 'topup' AS type, NULL AS document_no, NULL AS payee,
                        NULL AS department_id, reference AS description,
                        amount AS amount_in, 0 AS amount_out, is_historical
                 FROM fund_topups
                 WHERE fund_account_id = ? AND (status = 'approved' OR is_historical = 1)
                   AND date BETWEEN ? AND ?

                 UNION ALL

                 SELECT date, created_at, 'expense' AS type, document_no, payee,
                        department_id, description,
                        0 AS amount_in, amount AS amount_out, is_historical
                 FROM expenses
                 WHERE fund_account_id = ? AND (status IN ('approved','paid') OR is_historical = 1)
                   AND date BETWEEN ? AND ?
             ) AS ledger
             LEFT JOIN departments d ON d.id = ledger.department_id
             ORDER BY ledger.date, ledger.created_at"
        );
        $stmt->execute([$fundAccountId, $from, $to, $fundAccountId, $from, $to]);
        $rows = $stmt->fetchAll();

        $totalIn = 0.0;
        $totalOut = 0.0;
        $hasHistorical = false;
        foreach ($rows as &$r) {
            $r['amount_in'] = (float) $r['amount_in'];
            $r['amount_out'] = (float) $r['amount_out'];
            $r['running_balance'] = (float) $r['running_balance'];
            $r['is_historical'] = (int) $r['is_historical'];
            $totalIn += $r['amount_in'];
            $totalOut += $r['amount_out'];
            if ($r['is_historical'] === 1) {
                $hasHistorical = true;
            }
        }
        unset($r);

        return [
            'from' => $from,
            'to' => $to,
            'rows' => $rows,
            'total_in' => $totalIn,
            'total_out' => $totalOut,
            'has_historical' => $hasHistorical,
        ];
    }
}
