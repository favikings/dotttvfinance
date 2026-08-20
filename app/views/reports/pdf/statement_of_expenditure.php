<?php
/**
 * @var string $from
 * @var string $to
 * @var array{opening_balance: float, funds_received: float, funds_received_count: int,
 *            has_historical_funds: bool, expenditure: float, expenditure_count: int,
 *            has_historical_expenditure: bool, outstanding_liabilities: float,
 *            outstanding_liabilities_count: int, closing_balance: float} $data
 */
$badge = static fn (bool $flag): string => $flag ? '<span class="badge-backfilled">Backfilled</span>' : '';
?>
<table class="metrics">
    <tr>
        <td><div class="metric-label">Opening Balance</div><div class="metric-value"><?= naira_pdf($data['opening_balance']) ?></div></td>
        <td><div class="metric-label">Closing Balance</div><div class="metric-value"><?= naira_pdf($data['closing_balance']) ?></div></td>
        <td><div class="metric-label">Outstanding Liabilities</div><div class="metric-value"><?= naira_pdf($data['outstanding_liabilities']) ?></div></td>
    </tr>
</table>

<table class="report-table">
    <tr>
        <td>Opening Balance (as of <?= View::e(date('d/m/Y', strtotime($from . ' -1 day'))) ?>)</td>
        <td class="text-right"><?= naira_pdf($data['opening_balance']) ?></td>
    </tr>
    <tr>
        <td>Add: Funds Received in Period (<?= $data['funds_received_count'] ?>) <?= $badge($data['has_historical_funds']) ?></td>
        <td class="text-right text-success">+ <?= naira_pdf($data['funds_received']) ?></td>
    </tr>
    <tr>
        <td>Less: Approved Expenditure in Period (<?= $data['expenditure_count'] ?>) <?= $badge($data['has_historical_expenditure']) ?></td>
        <td class="text-right text-error">- <?= naira_pdf($data['expenditure']) ?></td>
    </tr>
    <tr class="total-row">
        <td>Closing Balance</td>
        <td class="text-right"><?= naira_pdf($data['closing_balance']) ?></td>
    </tr>
    <tr>
        <td>Outstanding Liabilities (approved, not yet paid, as of <?= View::e(date('d/m/Y', strtotime($to))) ?>) &mdash; <?= $data['outstanding_liabilities_count'] ?> item(s)</td>
        <td class="text-right text-warning"><?= naira_pdf($data['outstanding_liabilities']) ?></td>
    </tr>
</table>
