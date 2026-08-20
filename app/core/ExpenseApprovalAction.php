<?php

declare(strict_types=1);

/** Mirrors expense_approvals.action ENUM in schema.sql. */
enum ExpenseApprovalAction: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Cancelled = 'cancelled';
}