<?php

declare(strict_types=1);

// Report suite date-range selector + PDF/Excel export actions (Build Prompt
// 3.3, 3.3a), shared by every report screen so the control never gets
// re-composed slightly differently per report. GET-submits back to the same
// report path with ?from=&to=; the PDF and Excel links carry the same two
// params through to their matching *Pdf() / *Excel() routes. Payables has no
// natural "from" (it's a point-in-time snapshot of what's currently owed), so
// it renders a single "As of" date field instead via $singleDate.
// Usage: echo date_range_filter('/reports/statement-of-expenditure', $from, $to,
//   '/reports/statement-of-expenditure/pdf', excelAction: '/reports/statement-of-expenditure/excel');
if (!function_exists('date_range_filter')) {
    function date_range_filter(string $action, string $from, string $to, string $pdfAction, bool $singleDate = false, string $excelAction = ''): string
    {
        $actionUrl = htmlspecialchars(url($action), ENT_QUOTES, 'UTF-8');
        $fromEsc = htmlspecialchars($from, ENT_QUOTES, 'UTF-8');
        $toEsc = htmlspecialchars($to, ENT_QUOTES, 'UTF-8');
        $pdfUrl = htmlspecialchars(url($pdfAction) . '?from=' . urlencode($from) . '&to=' . urlencode($to), ENT_QUOTES, 'UTF-8');

        $exportButtons = '<a href="' . $pdfUrl . '"
               class="inline-flex items-center gap-1.5 border border-outline text-on-surface font-medium text-sm px-4 py-2.5 rounded hover:bg-surface-container transition-colors">
                <svg class="w-4 h-4" stroke="currentColor" fill="none"><use href="' . htmlspecialchars(url('/assets/icons/sprite.svg'), ENT_QUOTES, 'UTF-8') . '#download"></use></svg>
                Export PDF
            </a>';

        if ($excelAction !== '') {
            $excelUrl = htmlspecialchars(url($excelAction) . '?from=' . urlencode($from) . '&to=' . urlencode($to), ENT_QUOTES, 'UTF-8');
            $exportButtons .= '
            <a href="' . $excelUrl . '"
               class="inline-flex items-center gap-1.5 border border-outline text-on-surface font-medium text-sm px-4 py-2.5 rounded hover:bg-surface-container transition-colors">
                <svg class="w-4 h-4" stroke="currentColor" fill="none"><use href="' . htmlspecialchars(url('/assets/icons/sprite.svg'), ENT_QUOTES, 'UTF-8') . '#download"></use></svg>
                Export Excel
            </a>';
        }

        $dateFields = $singleDate
            ? '<div>
                 <label class="block text-label-md text-on-surface-variant mb-1.5" for="to">As of</label>
                 <input type="date" id="to" name="to" value="' . $toEsc . '"
                        class="px-3 py-2 rounded border border-outline bg-surface-container-lowest text-on-surface text-sm
                               focus:outline-none focus:ring-2 focus:ring-secondary-container focus:border-transparent">
               </div>'
            : '<div>
                 <label class="block text-label-md text-on-surface-variant mb-1.5" for="from">From</label>
                 <input type="date" id="from" name="from" value="' . $fromEsc . '"
                        class="px-3 py-2 rounded border border-outline bg-surface-container-lowest text-on-surface text-sm
                               focus:outline-none focus:ring-2 focus:ring-secondary-container focus:border-transparent">
               </div>
               <div>
                 <label class="block text-label-md text-on-surface-variant mb-1.5" for="to">To</label>
                 <input type="date" id="to" name="to" value="' . $toEsc . '"
                        class="px-3 py-2 rounded border border-outline bg-surface-container-lowest text-on-surface text-sm
                               focus:outline-none focus:ring-2 focus:ring-secondary-container focus:border-transparent">
               </div>';

        return '
        <form method="get" action="' . $actionUrl . '" class="bg-surface-container-lowest rounded-lg border border-outline-variant p-4 mb-6 flex flex-wrap items-end gap-4">
            ' . $dateFields . '
            <button type="submit"
                    class="bg-secondary-container text-on-secondary-container font-medium text-sm px-4 py-2.5 rounded hover:opacity-90 transition-opacity">
                Apply
            </button>
            <div class="ml-auto flex items-center gap-2">
                ' . $exportButtons . '
            </div>
        </form>';
    }
}
