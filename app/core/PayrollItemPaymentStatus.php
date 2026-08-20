<?php

declare(strict_types=1);

/**
 * Payroll item payment statuses, mirroring the `payroll_items.payment_status`
 * DB ENUM in schema.sql exactly (CLAUDE.md rule 7 — never bare string
 * literals for status comparisons).
 */
enum PayrollItemPaymentStatus: string
{
    case Pending = 'pending';
    case Paid = 'paid';
}
