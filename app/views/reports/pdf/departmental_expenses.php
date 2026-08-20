<?php
/**
 * @var string $from
 * @var string $to
 * @var array{rows: array<int, array{id: int, name: string, total: float, historical_total: float,
 *            has_historical: bool, cnt: int, pct: float}>, grand_total: float} $data
 */
$badge = static fn (bool $flag): string => $flag ? '<span class="badge-backfilled">Backfilled</span>' : '';
?>
<table class="report-table">
    <thead>
        <tr>
            <th>Department</th>
            <th class="text-right">Expenses</th>
            <th class="text-right">Amount</th>
            <th class="text-right">% of Total</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($data['rows'] as $row): ?>
            <tr>
                <td><?= View::e($row['name']) ?> <?= $badge($row['has_historical']) ?></td>
                <td class="text-right"><?= $row['cnt'] ?></td>
                <td class="text-right"><?= naira_pdf($row['total']) ?></td>
                <td class="text-right"><?= number_format($row['pct'], 1) ?>%</td>
            </tr>
        <?php endforeach; ?>
        <tr class="total-row">
            <td>Total</td>
            <td></td>
            <td class="text-right"><?= naira_pdf($data['grand_total']) ?></td>
            <td class="text-right">100.0%</td>
        </tr>
    </tbody>
</table>
