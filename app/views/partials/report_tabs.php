<?php

declare(strict_types=1);

// Report suite sub-navigation (Build Prompt 3.3) — same segmented tab bar
// pattern as settings_tabs()/historical_tabs(), its own report keys. The
// date range query string is preserved across tabs so switching report type
// mid-review doesn't reset the period the user picked.
// Usage: echo report_tabs('statement-of-expenditure', $from, $to);
if (!function_exists('report_tabs')) {
    function report_tabs(string $active, ?string $from = null, ?string $to = null): string
    {
        $tabs = [
            'statement-of-expenditure' => ['/reports/statement-of-expenditure', 'Statement of Expenditure'],
            'fund-ledger'              => ['/reports/fund-ledger', 'Fund Ledger'],
            'profit-loss'              => ['/reports/profit-loss', 'Profit & Loss'],
            'cash-flow'                => ['/reports/cash-flow', 'Cash Flow'],
            'departmental-expenses'    => ['/reports/departmental-expenses', 'Departmental Expenses'],
            'budget-vs-actual'         => ['/reports/budget-vs-actual', 'Budget vs. Actual'],
            'receivables'              => ['/reports/receivables', 'Receivables'],
            'payables'                 => ['/reports/payables', 'Payables'],
        ];

        $query = '';
        if ($from !== null && $to !== null) {
            $query = '?from=' . urlencode($from) . '&to=' . urlencode($to);
        }

        $html = '<div class="flex flex-wrap gap-1 rounded-lg bg-surface-container p-1 mb-6">';
        foreach ($tabs as $key => [$href, $label]) {
            $isActive = $key === $active;
            $classes = $isActive
                ? 'bg-surface-container-lowest text-on-surface shadow-[var(--shadow-ambient)]'
                : 'text-on-surface-variant hover:bg-surface-container-high hover:text-on-surface';
            $html .= '<a href="' . htmlspecialchars(url($href . $query), ENT_QUOTES, 'UTF-8') . '"'
                . ' class="rounded px-4 py-2 text-sm font-medium whitespace-nowrap transition-colors ' . $classes . '">'
                . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</a>';
        }
        $html .= '</div>';

        return $html;
    }
}
