<?php
/**
 * @var string $to
 * @var array{rows: array<int, array{id: int, expense_no: ?string, date: string, payee: string,
 *            amount: float, is_historical: int, department_name: ?string}>, total: float,
 *            has_historical: bool} $data
 */
$badge = static fn (bool $flag): string => $flag ? '<span class="badge-backfilled">Backfilled</span>' : '';
?>
<table class="metrics">
    <tr>
        <td><div class="metric-label">Total Owed</div><div class="metric-value"><?= naira_pdf($data['total']) ?></div></td>
        <td><div class="metric-label">Items</div><div class="metric-value"><?= count($data['rows']) ?></div></td>
    </tr>
</table>

<table class="report-table">
    <thead>
        <tr>
            <th>Expense No</th>
            <th>Date</th>
            <th>Payee</th>
            <th>Department</th>
            <th class="text-right">Amount</th>
        </tr>
    </thead>
    <tbody>
        <?php if (empty($data['rows'])): ?>
            <tr><td colspan="5">Nothing owed as of this date.</td></tr>
        <?php else: ?>
            <?php foreach ($data['rows'] as $row): ?>
                <tr>
                    <td><?= View::e($row['expense_no'] ?? '—') ?> <?= $badge((int) $row['is_historical'] === 1) ?></td>
                    <td><?= View::e(date('d/m/Y', strtotime($row['date']))) ?></td>
                    <td><?= View::e($row['payee']) ?></td>
                    <td><?= View::e($row['department_name'] ?? '—') ?></td>
                    <td class="text-right"><?= naira_pdf($row['amount']) ?></td>
                </tr>
            <?php endforeach; ?>
        <?php endif; ?>
        <tr class="total-row">
            <td colspan="4">Total</td>
            <td class="text-right"><?= naira_pdf($data['total']) ?></td>
        </tr>
    </tbody>
</table>
