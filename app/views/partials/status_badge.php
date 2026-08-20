<?php

declare(strict_types=1);

// UI Component Guide §5 — one shared badge renderer for every status across
// every module (expenses, invoices, top-ups, payroll). Usage: status_badge('pending_gm')
//
// Deviates from the guide's literal example: 'approved'/'paid' map to this
// project's own success / success-container tokens (design-system-dotttv.md)
// instead of the guide's raw bg-emerald-100/text-emerald-700, since emerald
// isn't a named design-system token and would itself violate guide §10's
// "no arbitrary Tailwind color" rule.
if (!function_exists('status_badge')) {
    function status_badge(string $status): string
    {
        $map = [
            'draft'            => ['bg-surface-container-high text-on-surface-variant', 'Draft'],
            'pending'          => ['bg-warning-container text-warning', 'Pending'],
            'pending_gm'       => ['bg-secondary-container/30 text-on-secondary-container', 'Pending GM'],
            'pending_chairman' => ['bg-secondary-container/30 text-on-secondary-container', 'Pending Chairman'],
            'approved'         => ['bg-success-container text-success', 'Approved'],
            'rejected'         => ['bg-error-container text-on-error-container', 'Rejected'],
            'closed'           => ['bg-surface-container-high text-on-surface-variant', 'Closed'],
            'paid'             => ['bg-success-container text-success', 'Paid'],
            'unpaid'           => ['bg-warning-container text-warning', 'Unpaid'],
            'partial'          => ['bg-secondary-container/30 text-on-secondary-container', 'Partial'],
            'overdue'          => ['bg-error-container text-on-error-container', 'Overdue'],
            'active'           => ['bg-success-container text-success', 'Active'],
            'inactive'         => ['bg-surface-container-high text-on-surface-variant', 'Inactive'],
        ];

        [$classes, $label] = $map[$status] ?? ['bg-surface-container text-on-surface-variant', ucfirst($status)];

        return '<span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium ' . $classes . '">'
            . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</span>';
    }
}
