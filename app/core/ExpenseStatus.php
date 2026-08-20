<?php

declare(strict_types=1);

/**
 * Expense workflow statuses, mirroring the `expenses.status` DB ENUM in
 * schema.sql exactly (Tech Spec §18 / CLAUDE.md rule 7 — never bare string
 * literals for status comparisons). The DB stays the source of truth; this
 * enum just moves typos to compile-time errors.
 */
enum ExpenseStatus: string
{
    case Draft = 'draft';
    case PendingGm = 'pending_gm';
    case PendingChairman = 'pending_chairman';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Closed = 'closed';
    case Paid = 'paid';

    public static function tryFromString(string $value): ?self
    {
        foreach (self::cases() as $case) {
            if ($case->value === $value) {
                return $case;
            }
        }
        return null;
    }
}
