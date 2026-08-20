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
