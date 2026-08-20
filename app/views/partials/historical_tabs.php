<?php

declare(strict_types=1);

// UI Component Guide §12a — segmented tab bar for the two Historical Entry
// screens, same visual pattern as settings_tabs() (§11a) with its own two
// keys. Usage: echo historical_tabs('expenses'); // key: expenses | topups
if (!function_exists('historical_tabs')) {
    function historical_tabs(string $active): string
    {
        $tabs = [
            'expenses' => ['/historical-entry/expenses', 'Expenses'],
            'topups'   => ['/historical-entry/topups', 'Fund Top-Up'],
        ];

        $html = '<div class="flex flex-wrap gap-1 rounded-lg bg-surface-container p-1 mb-6">';
        foreach ($tabs as $key => [$href, $label]) {
            $isActive = $key === $active;
            $classes = $isActive
                ? 'bg-surface-container-lowest text-on-surface shadow-[var(--shadow-ambient)]'
                : 'text-on-surface-variant hover:bg-surface-container-high hover:text-on-surface';
            $html .= '<a href="' . htmlspecialchars(url($href), ENT_QUOTES, 'UTF-8') . '"'
                . ' class="rounded px-4 py-2 text-sm font-medium transition-colors ' . $classes . '">'
                . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</a>';
        }
        $html .= '</div>';

        return $html;
    }
}
