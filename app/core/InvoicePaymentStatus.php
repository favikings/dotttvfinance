<?php

declare(strict_types=1);

/**
 * Invoice payment status, mirroring the `invoices.payment_status` DB ENUM in
 * schema.sql exactly (CLAUDE.md rule 7 — never bare string literals for
 * status comparisons).
 */
enum InvoicePaymentStatus: string
{
    case Unpaid = 'unpaid';
    case Partial = 'partial';
    case Paid = 'paid';
}