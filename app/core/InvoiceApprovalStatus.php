<?php

declare(strict_types=1);

/**
 * Invoice GM approval status, mirroring the `invoices.approval_status` DB ENUM
 * added in schema.sql §7 (Prompt 3.1). Single-approver sign-off: revenue
 * recognition is not a spend-control gate, so there's no tiered chain like
 * expenses — just pending -> approved/rejected.
 */
enum InvoiceApprovalStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
}