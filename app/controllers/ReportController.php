<?php

declare(strict_types=1);

use Dompdf\Dompdf;
use Dompdf\Options;

/**
 * Reporting suite (Build Prompt 3.3 / PRD §6.7 / Tech Spec §10, §14). Every
 * action is read-only (reports.view, granted to all four roles per the PRD
 * §3.2 matrix) — nothing here writes to the database, so unlike every other
 * controller in this app there's no AuditLogger call: CLAUDE.md rule 3 only
 * requires an audit row for state-changing actions, and viewing/exporting a
 * report changes nothing.
 *
 * Each report has a matching *Pdf() action that renders the same figures
 * (via Report::) into a dompdf-safe HTML fragment (plain inline-friendly CSS,
 * no Tailwind — dompdf's CSS support doesn't reliably cover the compiled
 * app.css) and streams it as a PDF, styled with the same hex values as
 * design-system-dotttv.md rather than dompdf's Times New Roman default.
 */
class ReportController
{
    private function guard(): array
    {
        $user = Auth::user();
        Permission::require($user, 'reports', 'view');
        return $user;
    }

    /** @return array{0: string, 1: string} [from, to], defaulting to month-to-date and always chronological. */
    private function dateRange(): array
    {
        $from = $_GET['from'] ?? date('Y-m-01');
        $to = $_GET['to'] ?? date('Y-m-d');

        if (!self::validDate($from)) {
            $from = date('Y-m-01');
        }
        if (!self::validDate($to)) {
            $to = date('Y-m-d');
        }
        if ($from > $to) {
            [$from, $to] = [$to, $from];
        }

        return [$from, $to];
    }

    private static function validDate(mixed $date): bool
    {
        if (!is_string($date) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            return false;
        }
        $d = DateTime::createFromFormat('Y-m-d', $date);
        return $d !== false && $d->format('Y-m-d') === $date;
    }

    // ------------------------------------------------------------------
    // Index — no standalone landing page, point at the core report
    // ------------------------------------------------------------------

    public function index(): void
    {
        $this->guard();
        header('Location: ' . url('/reports/statement-of-expenditure'));
        exit;
    }

    // ------------------------------------------------------------------
    // Statement of Expenditure — the PRD's core report
    // ------------------------------------------------------------------

    public function statementOfExpenditure(): void
    {
        $this->guard();
        [$from, $to] = $this->dateRange();
        $fundAccountId = Report::primaryFundAccountId();

        View::render('reports/statement_of_expenditure', [
            'title' => 'Statement of Expenditure',
            'from' => $from,
            'to' => $to,
            'data' => Report::statementOfExpenditure($fundAccountId, $from, $to),
        ]);
    }

    public function statementOfExpenditurePdf(): void
    {
        $this->guard();
        [$from, $to] = $this->dateRange();
        $fundAccountId = Report::primaryFundAccountId();

        $this->streamPdf('reports/pdf/statement_of_expenditure', [
            'reportTitle' => 'Statement of Expenditure',
            'from' => $from,
            'to' => $to,
            'data' => Report::statementOfExpenditure($fundAccountId, $from, $to),
        ], 'statement-of-expenditure_' . $from . '_to_' . $to);
    }

    // ------------------------------------------------------------------
    // Fund Ledger (Build Prompt 3.3a / Tech Spec §14a) — the one report
    // deliberately NOT filtered by department: every approved/paid expense
    // and every approved top-up, interleaved chronologically, with a running
    // balance computed in SQL via a window function.
    // ------------------------------------------------------------------

    public function fundLedger(): void
    {
        $this->guard();
        [$from, $to] = $this->dateRange();
        $fundAccountId = Report::primaryFundAccountId();

        View::render('reports/fund_ledger', [
            'title' => 'Fund Ledger',
            'from' => $from,
            'to' => $to,
            'data' => Report::fundLedger($fundAccountId, $from, $to),
        ]);
    }

    public function fundLedgerPdf(): void
    {
        $this->guard();
        [$from, $to] = $this->dateRange();
        $fundAccountId = Report::primaryFundAccountId();

        $this->streamPdf('reports/pdf/fund_ledger', [
            'reportTitle' => 'Fund Ledger',
            'from' => $from,
            'to' => $to,
            'data' => Report::fundLedger($fundAccountId, $from, $to),
        ], 'fund-ledger_' . $from . '_to_' . $to);
    }

    // ------------------------------------------------------------------
    // Excel exports (Build Prompt 3.3a / Tech Spec §14b) — one Excel action
    // per report that has a PDF export, each calling the SAME Report:: data
    // fetch as its PDF twin, so both formats can never disagree on the
    // numbers. ExcelExporter handles all of the formatting (title + range,
    // bold headers, currency columns, auto-size) from the same payload.
    // ------------------------------------------------------------------

    public function statementOfExpenditureExcel(): void
    {
        $this->guard();
        [$from, $to] = $this->dateRange();
        $fundAccountId = Report::primaryFundAccountId();
        $data = Report::statementOfExpenditure($fundAccountId, $from, $to);

        ExcelExporter::download(
            'Statement of Expenditure',
            $this->rangeLabel($from, $to),
            $this->companyName(),
            ['Item', 'Amount (NGN)'],
            [
                ['Opening Balance (as of ' . date('d/m/Y', strtotime($from . ' -1 day')) . ')', $data['opening_balance']],
                ['Add: Funds Received in Period (' . $data['funds_received_count'] . ' top-up(s))', $data['funds_received']],
                ['Less: Approved Expenditure in Period (' . $data['expenditure_count'] . ' expense(s))', $data['expenditure']],
                ['Closing Balance', $data['closing_balance']],
                ['Outstanding Liabilities (approved, not yet paid, as of ' . date('d/m/Y', strtotime($to)) . ') — ' . $data['outstanding_liabilities_count'] . ' item(s)', $data['outstanding_liabilities']],
            ],
            [ExcelExporter::FORMAT_TEXT, ExcelExporter::FORMAT_CURRENCY],
            'statement-of-expenditure_' . $from . '_to_' . $to
        );
    }

    public function profitLossExcel(): void
    {
        $this->guard();
        [$from, $to] = $this->dateRange();
        $fundAccountId = Report::primaryFundAccountId();
        $data = Report::profitLoss($fundAccountId, $from, $to);

        ExcelExporter::download(
            'Profit & Loss',
            $this->rangeLabel($from, $to),
            $this->companyName(),
            ['Item', 'Amount (NGN)'],
            [
                ['Gross Invoiced (approved, ' . $data['invoice_count'] . ' invoice(s))', $data['gross_revenue']],
                ['Less: WHT Withheld at Source', $data['wht_withheld']],
                ['VAT Collected (pass-through, excluded from revenue)', $data['vat_collected']],
                ['Net Revenue', $data['net_revenue']],
                ['Expenses (' . $data['expense_count'] . ')', $data['total_expenses']],
                ['Net Profit', $data['net_profit']],
            ],
            [ExcelExporter::FORMAT_TEXT, ExcelExporter::FORMAT_CURRENCY],
            'profit-loss_' . $from . '_to_' . $to
        );
    }

    public function cashFlowExcel(): void
    {
        $this->guard();
        [$from, $to] = $this->dateRange();
        $fundAccountId = Report::primaryFundAccountId();
        $data = Report::cashFlow($fundAccountId, $from, $to);

        ExcelExporter::download(
            'Cash Flow',
            $this->rangeLabel($from, $to),
            $this->companyName(),
            ['Item', 'Amount (NGN)'],
            [
                ['Cash In — Fund Top-Ups Received (' . $data['cash_in_count'] . ' top-up(s))', $data['cash_in']],
                ['Cash Out — Payments Disbursed (' . ($data['cash_out_paid_count'] + $data['cash_out_historical_count']) . ' disbursement(s))', $data['cash_out']],
                ['Net Cash Flow', $data['net_cash_flow']],
            ],
            [ExcelExporter::FORMAT_TEXT, ExcelExporter::FORMAT_CURRENCY],
            'cash-flow_' . $from . '_to_' . $to
        );
    }

    public function departmentalExpensesExcel(): void
    {
        $this->guard();
        [$from, $to] = $this->dateRange();
        $fundAccountId = Report::primaryFundAccountId();
        $data = Report::departmentalExpenses($fundAccountId, $from, $to);

        $rows = [];
        $historicalGrand = 0.0;
        foreach ($data['rows'] as $r) {
            $rows[] = [$r['name'], $r['cnt'], $r['total'], $r['historical_total'], $r['pct']];
            $historicalGrand += $r['historical_total'];
        }
        $rows[] = ['Total', count($data['rows']) > 0 ? array_sum(array_column($data['rows'], 'cnt')) : 0, $data['grand_total'], $historicalGrand, 100.0];

        ExcelExporter::download(
            'Departmental Expenses',
            $this->rangeLabel($from, $to),
            $this->companyName(),
            ['Department', 'Expenses', 'Amount (NGN)', 'Backfilled (NGN)', '% of Total'],
            $rows,
            [ExcelExporter::FORMAT_TEXT, ExcelExporter::FORMAT_NUMBER, ExcelExporter::FORMAT_CURRENCY, ExcelExporter::FORMAT_CURRENCY, ExcelExporter::FORMAT_PERCENT],
            'departmental-expenses_' . $from . '_to_' . $to
        );
    }

    public function budgetVsActualExcel(): void
    {
        $this->guard();
        [$from, $to] = $this->dateRange();
        $fundAccountId = Report::primaryFundAccountId();
        $data = Report::budgetVsActual($fundAccountId, $from, $to);

        $rows = [];
        foreach ($data['rows'] as $r) {
            $rows[] = [
                $r['name'],
                $r['total'],
                $r['budget'] !== null ? $r['budget'] : 'Not set',
                $r['variance'] !== null ? $r['variance'] : 'Not set',
            ];
        }
        $rows[] = [
            'Total',
            $data['grand_total_actual'],
            $data['grand_total_budget'] > 0 ? $data['grand_total_budget'] : 'Not set',
            $data['grand_total_budget'] > 0 ? $data['grand_total_budget'] - $data['grand_total_actual'] : 'Not set',
        ];

        ExcelExporter::download(
            'Budget vs. Actual',
            $this->rangeLabel($from, $to) . ' — budgets prorated over ' . $data['days_in_range'] . ' day(s)',
            $this->companyName(),
            ['Department', 'Actual (NGN)', 'Budget (prorated) (NGN)', 'Variance (NGN)'],
            $rows,
            [ExcelExporter::FORMAT_TEXT, ExcelExporter::FORMAT_CURRENCY, ExcelExporter::FORMAT_CURRENCY, ExcelExporter::FORMAT_CURRENCY],
            'budget-vs-actual_' . $from . '_to_' . $to
        );
    }

    public function receivablesExcel(): void
    {
        $this->guard();
        [$from, $to] = $this->dateRange();
        $data = Report::receivables($from, $to);

        $rows = [];
        foreach ($data['rows'] as $r) {
            $rows[] = [
                $r['invoice_no'],
                $r['date'],
                $r['client'],
                $r['department_name'] ?? '—',
                $r['due_date'] !== null ? $r['due_date'] : '',
                $r['amount'],
                ucfirst($r['payment_status']) . ($r['is_overdue'] ? ' (Overdue)' : ''),
            ];
        }
        $rows[] = ['Total', '', '', '', '', $data['total'], ''];

        ExcelExporter::download(
            'Receivables',
            $this->rangeLabel($from, $to),
            $this->companyName(),
            ['Invoice No', 'Date', 'Client', 'Department', 'Due Date', 'Amount (NGN)', 'Status'],
            $rows,
            [ExcelExporter::FORMAT_TEXT, ExcelExporter::FORMAT_DATE, ExcelExporter::FORMAT_TEXT, ExcelExporter::FORMAT_TEXT, ExcelExporter::FORMAT_DATE, ExcelExporter::FORMAT_CURRENCY, ExcelExporter::FORMAT_TEXT],
            'receivables_' . $from . '_to_' . $to
        );
    }

    public function payablesExcel(): void
    {
        $this->guard();
        [, $to] = $this->dateRange();
        $fundAccountId = Report::primaryFundAccountId();
        $data = Report::payables($fundAccountId, $to);

        $rows = [];
        foreach ($data['rows'] as $r) {
            $rows[] = [
                $r['expense_no'] ?? '—',
                $r['date'],
                $r['payee'],
                $r['department_name'] ?? '—',
                $r['amount'],
                (int) $r['is_historical'] === 1 ? 'Backfilled' : 'Live',
            ];
        }
        $rows[] = ['Total', '', '', '', $data['total'], ''];

        ExcelExporter::download(
            'Payables',
            $this->rangeLabel(null, $to),
            $this->companyName(),
            ['Expense No', 'Date', 'Payee', 'Department', 'Amount (NGN)', 'Source'],
            $rows,
            [ExcelExporter::FORMAT_TEXT, ExcelExporter::FORMAT_DATE, ExcelExporter::FORMAT_TEXT, ExcelExporter::FORMAT_TEXT, ExcelExporter::FORMAT_CURRENCY, ExcelExporter::FORMAT_TEXT],
            'payables_as-of_' . $to
        );
    }

    public function fundLedgerExcel(): void
    {
        $this->guard();
        [$from, $to] = $this->dateRange();
        $fundAccountId = Report::primaryFundAccountId();
        $data = Report::fundLedger($fundAccountId, $from, $to);

        $rows = [[
            $data['opening_as_of'],
            'Opening Balance',
            '—',
            '—',
            '—',
            'Brought forward as of ' . $data['opening_as_of'],
            '',
            '',
            $data['opening_balance'],
            'Live',
        ]];
        foreach ($data['rows'] as $r) {
            $rows[] = [
                $r['date'],
                ucfirst($r['type']),
                $r['document_no'] ?? '—',
                $r['payee'] ?? '—',
                $r['department_name'] ?? '—',
                $r['description'] ?? '—',
                $r['amount_in'],
                $r['amount_out'],
                $r['running_balance'],
                (int) $r['is_historical'] === 1 ? 'Backfilled' : 'Live',
            ];
        }
        $closing = $data['opening_balance'] + $data['total_in'] - $data['total_out'];
        $rows[] = ['Total', '', '', '', '', '', $data['total_in'], $data['total_out'], $closing, ''];

        ExcelExporter::download(
            'Fund Ledger',
            $this->rangeLabel($from, $to),
            $this->companyName(),
            ['Date', 'Type', 'Document No', 'Payee', 'Department', 'Description', 'In (NGN)', 'Out (NGN)', 'Balance (NGN)', 'Source'],
            $rows,
            [ExcelExporter::FORMAT_DATE, ExcelExporter::FORMAT_TEXT, ExcelExporter::FORMAT_TEXT, ExcelExporter::FORMAT_TEXT, ExcelExporter::FORMAT_TEXT, ExcelExporter::FORMAT_TEXT, ExcelExporter::FORMAT_CURRENCY, ExcelExporter::FORMAT_CURRENCY, ExcelExporter::FORMAT_CURRENCY, ExcelExporter::FORMAT_TEXT],
            'fund-ledger_' . $from . '_to_' . $to
        );
    }

    // ------------------------------------------------------------------
    // Shared PDF plumbing
    // ------------------------------------------------------------------
    // Profit & Loss
    // ------------------------------------------------------------------

    public function profitLoss(): void
    {
        $this->guard();
        [$from, $to] = $this->dateRange();
        $fundAccountId = Report::primaryFundAccountId();

        View::render('reports/profit_loss', [
            'title' => 'Profit & Loss',
            'from' => $from,
            'to' => $to,
            'data' => Report::profitLoss($fundAccountId, $from, $to),
        ]);
    }

    public function profitLossPdf(): void
    {
        $this->guard();
        [$from, $to] = $this->dateRange();
        $fundAccountId = Report::primaryFundAccountId();

        $this->streamPdf('reports/pdf/profit_loss', [
            'reportTitle' => 'Profit & Loss',
            'from' => $from,
            'to' => $to,
            'data' => Report::profitLoss($fundAccountId, $from, $to),
        ], 'profit-loss_' . $from . '_to_' . $to);
    }

    // ------------------------------------------------------------------
    // Cash Flow
    // ------------------------------------------------------------------

    public function cashFlow(): void
    {
        $this->guard();
        [$from, $to] = $this->dateRange();
        $fundAccountId = Report::primaryFundAccountId();

        View::render('reports/cash_flow', [
            'title' => 'Cash Flow',
            'from' => $from,
            'to' => $to,
            'data' => Report::cashFlow($fundAccountId, $from, $to),
        ]);
    }

    public function cashFlowPdf(): void
    {
        $this->guard();
        [$from, $to] = $this->dateRange();
        $fundAccountId = Report::primaryFundAccountId();

        $this->streamPdf('reports/pdf/cash_flow', [
            'reportTitle' => 'Cash Flow',
            'from' => $from,
            'to' => $to,
            'data' => Report::cashFlow($fundAccountId, $from, $to),
        ], 'cash-flow_' . $from . '_to_' . $to);
    }

    // ------------------------------------------------------------------
    // Departmental Expenses
    // ------------------------------------------------------------------

    public function departmentalExpenses(): void
    {
        $this->guard();
        [$from, $to] = $this->dateRange();
        $fundAccountId = Report::primaryFundAccountId();

        View::render('reports/departmental_expenses', [
            'title' => 'Departmental Expenses',
            'from' => $from,
            'to' => $to,
            'data' => Report::departmentalExpenses($fundAccountId, $from, $to),
        ]);
    }

    public function departmentalExpensesPdf(): void
    {
        $this->guard();
        [$from, $to] = $this->dateRange();
        $fundAccountId = Report::primaryFundAccountId();

        $this->streamPdf('reports/pdf/departmental_expenses', [
            'reportTitle' => 'Departmental Expenses',
            'from' => $from,
            'to' => $to,
            'data' => Report::departmentalExpenses($fundAccountId, $from, $to),
        ], 'departmental-expenses_' . $from . '_to_' . $to);
    }

    // ------------------------------------------------------------------
    // Budget vs. Actual — budgets are Super-Admin-editable per-department
    // monthly figures stored in `settings` (no dedicated budgets table)
    // ------------------------------------------------------------------

    public function budgetVsActual(): void
    {
        $user = $this->guard();
        [$from, $to] = $this->dateRange();
        $fundAccountId = Report::primaryFundAccountId();

        View::render('reports/budget_vs_actual', [
            'title' => 'Budget vs. Actual',
            'from' => $from,
            'to' => $to,
            'data' => Report::budgetVsActual($fundAccountId, $from, $to),
            'canEditBudgets' => Permission::check($user, 'settings', 'edit'),
        ]);
    }

    /** Super-Admin-only inline save from the Budget vs. Actual screen. */
    public function saveBudgets(): void
    {
        $user = $this->guard();
        Permission::require($user, 'settings', 'edit');

        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
            http_response_code(405);
            echo '405 Method Not Allowed';
            return;
        }

        $redirect = '/reports/budget-vs-actual';
        if (self::validDate($_POST['from'] ?? null) && self::validDate($_POST['to'] ?? null)) {
            $redirect .= '?from=' . urlencode($_POST['from']) . '&to=' . urlencode($_POST['to']);
        }

        if (!Csrf::verify($_POST['_csrf'] ?? null)) {
            $_SESSION['flash_error'] = 'Your session expired. Please try again.';
            header('Location: ' . url($redirect));
            exit;
        }

        $budgets = is_array($_POST['budget'] ?? null) ? $_POST['budget'] : [];
        foreach ($budgets as $departmentId => $amount) {
            $departmentId = (int) $departmentId;
            $amount = trim((string) $amount);
            if ($departmentId <= 0 || $amount === '' || !is_numeric($amount) || (float) $amount < 0) {
                continue;
            }
            Report::setDepartmentBudget($departmentId, (float) $amount, (int) $user['id']);
        }

        $_SESSION['flash_success'] = 'Department budgets updated.';
        header('Location: ' . url($redirect));
        exit;
    }

    public function budgetVsActualPdf(): void
    {
        $this->guard();
        [$from, $to] = $this->dateRange();
        $fundAccountId = Report::primaryFundAccountId();

        $this->streamPdf('reports/pdf/budget_vs_actual', [
            'reportTitle' => 'Budget vs. Actual',
            'from' => $from,
            'to' => $to,
            'data' => Report::budgetVsActual($fundAccountId, $from, $to),
        ], 'budget-vs-actual_' . $from . '_to_' . $to);
    }

    // ------------------------------------------------------------------
    // Receivables
    // ------------------------------------------------------------------

    public function receivables(): void
    {
        $this->guard();
        [$from, $to] = $this->dateRange();

        View::render('reports/receivables', [
            'title' => 'Receivables',
            'from' => $from,
            'to' => $to,
            'data' => Report::receivables($from, $to),
        ]);
    }

    public function receivablesPdf(): void
    {
        $this->guard();
        [$from, $to] = $this->dateRange();

        $this->streamPdf('reports/pdf/receivables', [
            'reportTitle' => 'Receivables',
            'from' => $from,
            'to' => $to,
            'data' => Report::receivables($from, $to),
        ], 'receivables_' . $from . '_to_' . $to);
    }

    // ------------------------------------------------------------------
    // Payables
    // ------------------------------------------------------------------

    public function payables(): void
    {
        $this->guard();
        [, $to] = $this->dateRange();
        $fundAccountId = Report::primaryFundAccountId();

        View::render('reports/payables', [
            'title' => 'Payables',
            'to' => $to,
            'data' => Report::payables($fundAccountId, $to),
        ]);
    }

    public function payablesPdf(): void
    {
        $this->guard();
        [, $to] = $this->dateRange();
        $fundAccountId = Report::primaryFundAccountId();

        $this->streamPdf('reports/pdf/payables', [
            'reportTitle' => 'Payables',
            'to' => $to,
            'data' => Report::payables($fundAccountId, $to),
        ], 'payables_as-of_' . $to);
    }

    // ------------------------------------------------------------------
    // Shared PDF plumbing
    // ------------------------------------------------------------------

    /** Human-readable date-range label for export headers, matching the PDF layout's own logic. */
    private function rangeLabel(?string $from, ?string $to): string
    {
        if ($from !== null && $to !== null && $from !== $to) {
            return date('d M Y', strtotime($from)) . ' - ' . date('d M Y', strtotime($to));
        }
        return 'As of ' . date('d M Y', strtotime($to ?? 'now'));
    }

    /** Company name from the settings table — same path every PDF/Excel export uses (Build Prompt R.5). */
    private function companyName(): string
    {
        return (string) Setting::get('company_name', APP_NAME);
    }

    private function streamPdf(string $contentView, array $data, string $filenameBase): void
    {
        $data += [
            'companyName' => $this->companyName(),
            'generatedAt' => date('d M Y, H:i'),
        ];
        $content = View::renderPartial($contentView, $data);
        $html = View::renderPartial('reports/pdf/layout', $data + [
            'content' => $content,
            'reportTitle' => $data['reportTitle'] ?? 'Report',
        ]);

        $options = new Options();
        $options->set('isRemoteEnabled', false);
        $options->set('isHtml5ParserEnabled', true);
        $options->set('defaultFont', 'Helvetica');

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        $safeName = preg_replace('/[^a-zA-Z0-9_\-]/', '', $filenameBase) . '.pdf';
        $dompdf->stream($safeName, ['Attachment' => true]);
        exit;
    }
}
