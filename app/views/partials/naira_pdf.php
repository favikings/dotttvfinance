<?php

declare(strict_types=1);

// PDF-only money formatter (Build Prompt 3.3 / Tech Spec §14). dompdf's core
// Helvetica font (a base-14 PDF font, no embedded glyph table) has no glyph
// for the ₦ sign — it silently renders as "?" instead of failing loudly,
// which would make every amount on every exported report look broken. The
// web UI keeps naira() and the real ₦ symbol (browsers render it fine from
// the system font); PDF exports use this ASCII-safe "NGN" form instead.
if (!function_exists('naira_pdf')) {
    function naira_pdf(float|string|int|null $amount): string
    {
        return 'NGN ' . number_format((float) $amount, 2);
    }
}
