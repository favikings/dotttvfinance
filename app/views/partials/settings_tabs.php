<?php

declare(strict_types=1);

// Settings sub-navigation — a segmented tab bar used by every settings
// screen. Usage: echo settings_tabs('departments');. Keys are the
// canonical section ids; the active one renders on the surface-white
// segment, the rest as muted hoverable segments. All colors are named
// design-system tokens (guide §10).
if (!function_exists('settings_tabs')) {
    function settings_tabs(string $active): string
    {
        $tabs = [
            'departments'    => ['/settings/departments', 'Departments'],
            'categories'     => ['/settings/categories', 'Categories'],
            'fund_accounts'  => ['/settings/fund-accounts', 'Fund Account'],
            'approval_rules' => ['/settings/approval-rules', 'Approval Rules'],
            'users'          => ['/settings/users', 'Users'],
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
