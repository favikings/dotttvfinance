<?php
/**
 * @var string $from
 * @var string $to
 * @var array{gross_revenue: float, vat_collected: float, wht_withheld: float, net_revenue: float,
 *            invoice_count: int, total_expenses: float, expense_count: int,
 *            has_historical_expenses: bool, net_profit: float} $data
 */
$badge = static fn (bool $flag): string => $flag ? '<span class="badge-backfilled">Backfilled</span>' : '';
?>
<table class="metrics">
    <tr>
        <td><div class="metric-label">Net Revenue</div><div class="metric-value"><?= naira_pdf($data['net_revenue']) ?></div></td>
        <td><div class="metric-label">Total Expenses</div><div class="metric-value"><?= naira_pdf($data['total_expenses']) ?></div></td>
        <td><div class="metric-label">Net Profit</div><div class="metric-value"><?= naira_pdf($data['net_profit']) ?></div></td>
    </tr>
</table>

<table class="report-table">
    <tr><td><strong>Revenue</strong></td><td></td></tr>
    <tr><td>Gross Invoiced (approved, <?= $data['invoice_count'] ?> invoice(s))</td><td class="text-right"><?= naira_pdf($data['gross_revenue']) ?></td></tr>
    <tr><td>Less: WHT Withheld at Source</td><td class="text-right text-error">- <?= naira_pdf($data['wht_withheld']) ?></td></tr>
    <tr><td>VAT Collected (pass-through, excluded from revenue)</td><td class="text-right text-muted"><?= naira_pdf($data['vat_collected']) ?></td></tr>
    <tr class="total-row"><td>Net Revenue</td><td class="text-right"><?= naira_pdf($data['net_revenue']) ?></td></tr>
    <tr><td>Expenses (<?= $data['expense_count'] ?>) <?= $badge($data['has_historical_expenses']) ?></td><td class="text-right text-error">- <?= naira_pdf($data['total_expenses']) ?></td></tr>
    <tr class="total-row"><td>Net Profit</td><td class="text-right"><?= naira_pdf($data['net_profit']) ?></td></tr>
</table>
