<?php
/**
 * @var string $from
 * @var string $to
 * @var array{cash_in: float, cash_in_count: int, has_historical_in: bool, cash_out: float,
 *            cash_out_paid_count: int, cash_out_historical_count: int, has_historical_out: bool,
 *            net_cash_flow: float} $data
 */
$badge = static fn (bool $flag): string => $flag ? '<span class="badge-backfilled">Backfilled</span>' : '';
?>
<table class="subtitle"><tr><td>Actual cash movement (top-up date / payment date), not accrual</td></tr></table>

<table class="metrics">
    <tr>
        <td><div class="metric-label">Cash In</div><div class="metric-value"><?= naira_pdf($data['cash_in']) ?></div></td>
        <td><div class="metric-label">Cash Out</div><div class="metric-value"><?= naira_pdf($data['cash_out']) ?></div></td>
        <td><div class="metric-label">Net Cash Flow</div><div class="metric-value"><?= naira_pdf($data['net_cash_flow']) ?></div></td>
    </tr>
</table>

<table class="report-table">
    <tr>
        <td>Cash In &mdash; Fund Top-Ups Received (<?= $data['cash_in_count'] ?>) <?= $badge($data['has_historical_in']) ?></td>
        <td class="text-right text-success">+ <?= naira_pdf($data['cash_in']) ?></td>
    </tr>
    <tr>
        <td>Cash Out &mdash; Payments Disbursed (<?= $data['cash_out_paid_count'] + $data['cash_out_historical_count'] ?>) <?= $badge($data['has_historical_out']) ?></td>
        <td class="text-right text-error">- <?= naira_pdf($data['cash_out']) ?></td>
    </tr>
    <tr class="total-row">
        <td>Net Cash Flow</td>
        <td class="text-right"><?= naira_pdf($data['net_cash_flow']) ?></td>
    </tr>
</table>
