<?php

declare(strict_types=1);

/**
 * Fund top-up statuses, mirroring the `fund_topups.status` DB ENUM in
 * schema.sql exactly (CLAUDE.md rule 7 — never bare string literals for
 * status comparisons).
 */
enum TopupStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
}
