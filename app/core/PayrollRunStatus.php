<?php

declare(strict_types=1);

/**
 * Payroll run statuses, mirroring the `payroll_runs.status` DB ENUM in
 * schema.sql exactly (CLAUDE.md rule 7 — never bare string literals for
 * status comparisons).
 *
 * Lifecycle: draft (Accountant is building the run, adding/removing items)
 * -> approved (single GM sign-off per PRD §6.6/§3.2 — no tiered chain, no
 * reject step; the ENUM has no 'rejected' value) -> paid (every item's
 * payment_status has flipped to 'paid'; PayrollItem::markPaid() flips the
 * run automatically once the last item clears).
 */
enum PayrollRunStatus: string
{
    case Draft = 'draft';
    case Approved = 'approved';
    case Paid = 'paid';
}
