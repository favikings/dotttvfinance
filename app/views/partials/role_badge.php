<?php

declare(strict_types=1);

// Role pill for user lists / approval-rule rows. Same pill geometry as the
// status badge but neutral-tinted, since a role isn't a status. Usage:
// echo role_badge($user['role_name']);. Keys are the schema.sql role names.
if (!function_exists('role_badge')) {
    function role_badge(string $role): string
    {
        $labels = [
            'super_admin' => 'Super Admin',
            'accountant'  => 'Accountant',
            'gm'          => 'GM',
            'chairman'    => 'Chairman',
        ];
        $label = $labels[$role] ?? ucfirst($role);

        return '<span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium bg-surface-container-high text-on-surface-variant">'
            . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</span>';
    }
}
