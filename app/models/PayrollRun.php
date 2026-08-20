<?php

declare(strict_types=1);

/**
 * Payroll run model (Build Prompt 3.2 / PRD §6.6). One run per
 * period_month/period_year (schema's UNIQUE(period_month, period_year)) —
 * the Accountant creates it as 'draft' and adds payroll_items to it, the GM
 * gives a single sign-off (no tiered chain, no reject — the ENUM has no
 * 'rejected' value), and it flips to 'paid' once every item's
 * payment_status has cleared (see PayrollItem::markPaid()).
 */
class PayrollRun
{
    private const MONTH_NAMES = [
        1 => 'January', 2 => 'February', 3 => 'March', 4 => 'April',
        5 => 'May', 6 => 'June', 7 => 'July', 8 => 'August',
        9 => 'September', 10 => 'October', 11 => 'November', 12 => 'December',
    ];

    public static function monthName(int $month): string
    {
        return self::MONTH_NAMES[$month] ?? (string) $month;
    }

    /**
     * List view — joined with creator/approver names and the item totals
     * the UI needs, so views never run their own aggregate queries.
     *
     * @return array<int, array>
     */
    public static function all(): array
    {
        $stmt = Database::connection()->query(
            'SELECT r.id, r.period_month, r.period_year, r.status,
                    r.created_by, r.approved_by, r.created_at, r.updated_at,
                    c.name AS created_by_name,
                    a.name AS approved_by_name,
                    COUNT(i.id) AS item_count,
                    COALESCE(SUM(i.net_salary), 0) AS total_net_salary
             FROM payroll_runs r
             LEFT JOIN users c ON c.id = r.created_by
             LEFT JOIN users a ON a.id = r.approved_by
             LEFT JOIN payroll_items i ON i.payroll_run_id = r.id
             GROUP BY r.id, r.period_month, r.period_year, r.status,
                      r.created_by, r.approved_by, r.created_at, r.updated_at,
                      c.name, a.name
             ORDER BY r.period_year DESC, r.period_month DESC'
        );
        return $stmt->fetchAll();
    }

    public static function find(int $id): ?array
    {
        $stmt = Database::connection()->prepare(
            'SELECT r.id, r.period_month, r.period_year, r.status,
                    r.created_by, r.approved_by, r.created_at, r.updated_at,
                    c.name AS created_by_name,
                    a.name AS approved_by_name
             FROM payroll_runs r
             LEFT JOIN users c ON c.id = r.created_by
             LEFT JOIN users a ON a.id = r.approved_by
             WHERE r.id = ? LIMIT 1'
        );
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    /** Enforces the schema's UNIQUE(period_month, period_year) with a friendly pre-check. */
    public static function existsForPeriod(int $month, int $year): bool
    {
        $stmt = Database::connection()->prepare(
            'SELECT COUNT(*) FROM payroll_runs WHERE period_month = ? AND period_year = ?'
        );
        $stmt->execute([$month, $year]);
        return (int) $stmt->fetchColumn() > 0;
    }

    public static function create(int $month, int $year, int $createdBy): int
    {
        $stmt = Database::connection()->prepare(
            'INSERT INTO payroll_runs (period_month, period_year, status, created_by)
             VALUES (?, ?, ?, ?)'
        );
        $stmt->execute([$month, $year, PayrollRunStatus::Draft->value, $createdBy]);
        return (int) Database::connection()->lastInsertId();
    }

    /**
     * FOR UPDATE lock on a draft run about to get a GM sign-off: stops two
     * GMs racing the same run into two approvals — the second blocks until
     * the first commits (flipping status), then re-reads the row and the
     * WHERE clause stops matching.
     */
    public static function lockDraft(int $id): ?array
    {
        $stmt = Database::connection()->prepare(
            'SELECT id, period_month, period_year, status
             FROM payroll_runs
             WHERE id = ? AND status = ?
             LIMIT 1
             FOR UPDATE'
        );
        $stmt->execute([$id, PayrollRunStatus::Draft->value]);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    /** Single GM sign-off: the run's items are locked in and cleared for payment. */
    public static function approve(int $id, int $approverId): void
    {
        $stmt = Database::connection()->prepare(
            'UPDATE payroll_runs SET status = ?, approved_by = ?, updated_at = NOW() WHERE id = ?'
        );
        $stmt->execute([PayrollRunStatus::Approved->value, $approverId, $id]);
    }

    /** Called once the last item on an approved run clears to 'paid' (PayrollItem::markPaid()). */
    public static function markPaid(int $id): void
    {
        $stmt = Database::connection()->prepare(
            'UPDATE payroll_runs SET status = ?, updated_at = NOW() WHERE id = ?'
        );
        $stmt->execute([PayrollRunStatus::Paid->value, $id]);
    }
}
