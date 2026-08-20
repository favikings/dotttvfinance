<?php
/**
 * @var string $from
 * @var string $to
 * @var array{days_in_range: int, rows: array<int, array{id: int, name: string, total: float,
 *            has_historical: bool, cnt: int, monthly_budget: ?float, budget: ?float,
 *            variance: ?float}>, grand_total_actual: float, grand_total_budget: float} $data
 */
$badge = static fn (bool $flag): string => $flag ? '<span class="badge-backfilled">Backfilled</span>' : '';
?>
<table class="subtitle"><tr><td>Budgets are monthly figures, prorated here for <?= $data['days_in_range'] ?> day(s)</td></tr></table>

<table class="metrics">
    <tr>
        <td><div class="metric-label">Actual Spend</div><div class="metric-value"><?= naira_pdf($data['grand_total_actual']) ?></div></td>
        <td><div class="metric-label">Total Budget</div><div class="metric-value"><?= $data['grand_total_budget'] > 0 ? naira_pdf($data['grand_total_budget']) : 'Not set' ?></div></td>
        <td><div class="metric-label">Variance</div><div class="metric-value"><?= $data['grand_total_budget'] > 0 ? naira_pdf($data['grand_total_budget'] - $data['grand_total_actual']) : '&mdash;' ?></div></td>
    </tr>
</table>

<table class="report-table">
    <thead>
        <tr>
            <th>Department</th>
            <th class="text-right">Actual</th>
            <th class="text-right">Budget (prorated)</th>
            <th class="text-right">Variance</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($data['rows'] as $row): ?>
            <tr>
                <td><?= View::e($row['name']) ?> <?= $badge($row['has_historical']) ?></td>
                <td class="text-right"><?= naira_pdf($row['total']) ?></td>
                <td class="text-right"><?= $row['budget'] !== null ? naira_pdf($row['budget']) : '&mdash;' ?></td>
                <td class="text-right <?= $row['variance'] !== null && $row['variance'] < 0 ? 'text-error' : '' ?>">
                    <?= $row['variance'] !== null ? naira_pdf($row['variance']) : '&mdash;' ?>
                </td>
            </tr>
        <?php endforeach; ?>
        <tr class="total-row">
            <td>Total</td>
            <td class="text-right"><?= naira_pdf($data['grand_total_actual']) ?></td>
            <td class="text-right"><?= $data['grand_total_budget'] > 0 ? naira_pdf($data['grand_total_budget']) : '&mdash;' ?></td>
            <td class="text-right"><?= $data['grand_total_budget'] > 0 ? naira_pdf($data['grand_total_budget'] - $data['grand_total_actual']) : '&mdash;' ?></td>
        </tr>
    </tbody>
</table>
