<?php

declare(strict_types=1);

// UI Component Guide §12 — provenance flag for historical/backfilled records
// (PRD §5), distinct from status_badge(): this isn't a workflow state, it's
// a data-source flag, so it renders as an outlined chip rather than a filled
// pill — reads as metadata, not another status, when placed next to one.
// Takes the row's own is_historical value and decides internally whether to
// render anything, same as status_badge() taking the raw status — so a call
// site can never forget to guard it and show "Backfilled" on a live entry.
// Usage: echo backfilled_badge($expense['is_historical']);
if (!function_exists('backfilled_badge')) {
    function backfilled_badge(bool|int $isHistorical): string
    {
        if (!$isHistorical) {
            return '';
        }

        return '<span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium border border-outline-variant text-on-surface-variant bg-surface-container">Backfilled</span>';
    }
}
