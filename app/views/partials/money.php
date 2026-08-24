<?php

declare(strict_types=1);

// Naira money formatting for display (never for arithmetic or storage).
// Accepts the string/float values PDO returns for DECIMAL columns. Usage:
// echo naira($row['current_balance']);.
if (!function_exists('naira')) {
    function naira(float|string|int|null $amount): string
    {
        return '₦' . number_format((float) $amount, 2);
    }
}

// UI Component Guide §5b — the one helper for debit/credit color coding, so
// amount-sign color never gets reimplemented (and drifts) per screen. Reuses
// naira() for the actual currency string, so the two never disagree on
// formatting — only color differs. 'debit' = money leaving the float (red),
// 'credit' = money entering it (green), 'neutral' = a balance/snapshot, never
// colored since it isn't a flow direction.
if (!function_exists('format_amount')) {
    function format_amount(float|string|int|null $amount, string $type = 'neutral'): string
    {
        $colorClass = match ($type) {
            'debit' => 'text-error',
            'credit' => 'text-emerald-600',
            default => 'text-on-surface',
        };
        return '<span class="' . $colorClass . ' font-medium">' . naira($amount) . '</span>';
    }
}
