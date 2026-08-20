<?php

declare(strict_types=1);

// UI Component Guide §6 — the one reusable dashboard/report KPI card.
// Usage: metric_card('Current Balance', '₦1,240,500', '+12% this month')
//
// Deviates from the guide's literal example: uses this project's own
// display-lg / label-sm typography tokens and the success token instead of
// the guide's raw text-[32px] / text-[11px] / text-emerald-600 bracket
// values — display-lg (32px/600/1.2/-0.02em) and label-sm (11px/600) are
// exact matches for those bracket sizes, so the rendered output is pixel-
// identical while staying on named tokens per guide §10.
if (!function_exists('metric_card')) {
    function metric_card(string $label, string $value, ?string $trend = null): string
    {
        $html = '<div class="bg-surface-container-lowest rounded-lg border border-outline-variant p-card-padding shadow-[var(--shadow-ambient)]">';
        $html .= '<p class="text-label-sm uppercase text-on-surface-variant mb-2">'
            . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</p>';
        $html .= '<p class="text-2xl sm:text-display-lg text-on-surface">'
            . htmlspecialchars($value, ENT_QUOTES, 'UTF-8') . '</p>';

        if ($trend !== null && $trend !== '') {
            $html .= '<p class="text-label-sm text-success mt-2">'
                . htmlspecialchars($trend, ENT_QUOTES, 'UTF-8') . '</p>';
        }

        $html .= '</div>';

        return $html;
    }
}
