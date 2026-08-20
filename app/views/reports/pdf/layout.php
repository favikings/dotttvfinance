<?php
/**
 * Shared dompdf document shell (Tech Spec §14 / Build Prompt 3.3): dompdf's
 * CSS support doesn't reliably cover the compiled Tailwind v4 output (custom
 * properties, modern layout), so PDF export gets its own minimal, table-safe
 * stylesheet using the exact hex values from design-system-dotttv.md instead
 * of dompdf's Times New Roman default. Every report's pdf/*.php content
 * template renders into $content and shares these classes.
 *
 * @var string $reportTitle
 * @var string $companyName
 * @var string $from
 * @var string $to
 * @var string $generatedAt
 * @var string $content
 */
$rangeLabel = isset($from) && $from !== ($to ?? '')
    ? date('d M Y', strtotime($from)) . ' - ' . date('d M Y', strtotime($to))
    : 'As of ' . date('d M Y', strtotime($to ?? 'now'));
?>
<!doctype html>
<html>
<head>
<meta charset="UTF-8">
<style>
    @page { margin: 100px 32px 60px 32px; }
    body { font-family: Helvetica, Arial, sans-serif; color: #1a1b20; font-size: 11px; }
    /* box-sizing: border-box (dompdf otherwise adds padding ON TOP OF the
       explicit height) plus a deliberate buffer between the header's bottom
       edge and content start: dompdf's position:fixed layout (it reflows
       fixed elements as real flow children per page, then repositions them —
       see FrameReflower/Page.php) leaves a small rendering slop right at the
       page-margin/content boundary that clips the top few px of whatever the
       literal first flow child is. -100px top on an 80px-tall header (not
       -80px) ends the header 20px above content start; the empty
       .content-spacer div below is the true first flow child so it — not
       real content — absorbs that clip. */
    header { position: fixed; top: -100px; left: -32px; right: -32px; height: 80px; box-sizing: border-box; background: #000d35; color: #ffffff; padding: 16px 32px; }
    header .company { font-size: 16px; font-weight: bold; }
    header .report-title { font-size: 12px; color: #b5c4ff; margin-top: 3px; }
    footer { position: fixed; bottom: -70px; left: -32px; right: -32px; height: 50px; box-sizing: border-box; padding: 10px 32px 0 32px; font-size: 8px; color: #757682; border-top: 1px solid #c5c6d2; }
    .content-spacer { height: 16px; }
    .meta { font-size: 9px; color: #444650; margin-bottom: 14px; }
    h1.title { font-size: 16px; color: #1a1b20; margin: 0 0 2px 0; }
    table.subtitle { width: 100%; margin-bottom: 12px; }
    table.subtitle td { font-size: 10px; color: #444650; padding: 0; }
    table.metrics { width: 100%; border-collapse: collapse; margin-bottom: 16px; }
    table.metrics td { width: 33%; padding: 10px 12px; border: 1px solid #c5c6d2; background: #f4f3f9; }
    table.metrics .metric-label { font-size: 8px; text-transform: uppercase; color: #444650; font-weight: bold; }
    table.metrics .metric-value { font-size: 15px; color: #1a1b20; font-weight: bold; margin-top: 3px; }
    table.report-table { width: 100%; border-collapse: collapse; font-size: 9.5px; }
    table.report-table th { text-align: left; background: #f4f3f9; color: #444650; font-weight: bold; padding: 7px 8px; border-bottom: 1px solid #c5c6d2; }
    table.report-table td { padding: 7px 8px; border-bottom: 1px solid #e3e1e8; color: #1a1b20; }
    table.report-table tr.total-row td { font-weight: bold; background: #f4f3f9; }
    .text-right { text-align: right; }
    .text-muted { color: #444650; }
    .text-success { color: #1e7e4f; }
    .text-error { color: #ba1a1a; }
    .text-warning { color: #a15c00; }
    .badge-backfilled { display: inline-block; font-size: 7.5px; padding: 2px 6px; border: 1px solid #c5c6d2; border-radius: 8px; color: #444650; margin-left: 4px; }
</style>
</head>
<body>
    <header>
        <div class="company"><?= View::e($companyName) ?></div>
        <div class="report-title"><?= View::e($reportTitle) ?> &mdash; <?= View::e($rangeLabel) ?></div>
    </header>
    <footer>
        Generated <?= View::e($generatedAt) ?> &middot; DOTT TV Finance &amp; Accounting System &middot; Confidential &mdash; for internal management review only
    </footer>

    <div class="content-spacer"></div>
    <?= $content ?>
</body>
</html>
