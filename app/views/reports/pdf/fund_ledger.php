<?php
/**
 * @var string $from
 * @var string $to
 * @var array{rows: array<int, array{date: string, created_at: ?string, type: string,
 *            document_no: ?string, payee: ?string, department_id: ?int,
 *            department_name: ?string, description: ?string, amount_in: ?float,
 *            amount_out: ?float, is_historical: int, running_balance: float}>,
 *            opening_balance: float, opening_as_of: string, has_transactions: bool,
 *            total_in: float, total_out: float, has_historical: bool} $data
 *
 * `rows` is one chronological list mixing transactions with balance
 * carry-forward rows (type 'opening') at the range start and at the start of
 * every month in the range — same array the HTML screen and the Excel export
 * render, so the three can never disagree.
 */
$badge = static fn (bool $flag): string => $flag ? '<span class="badge-backfilled">Backfilled</span>' : '';
$closing = $data['opening_balance'] + $data['total_in'] - $data['total_out'];
?>
<table class="metrics">
    <tr>
        <td><div class="metric-label">Opening Balance</div><div class="metric-value"><?= naira_pdf($data['opening_balance']) ?></div></td>
        <td><div class="metric-label">Total In</div><div class="metric-value"><?= naira_pdf($data['total_in']) ?></div></td>
        <td><div class="metric-label">Total Out</div><div class="metric-value"><?= naira_pdf($data['total_out']) ?></div></td>
        <td><div class="metric-label">Closing Balance</div><div class="metric-value"><?= naira_pdf($closing) ?></div></td>
    </tr>
</table>

<table class="report-table">
    <thead>
        <tr>
            <th>Date</th>
            <th>Type</th>
            <th>Doc No</th>
            <th>Payee</th>
            <th>Department</th>
            <th class="text-right">In</th>
            <th class="text-right">Out</th>
            <th class="text-right">Balance</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($data['rows'] as $row): ?>
            <?php if ($row['type'] === 'opening'): ?>
                <tr>
                    <td><?= View::e(date('d/m/Y', strtotime($row['date']))) ?></td>
                    <td colspan="4" class="text-muted"><?= View::e($row['description']) ?></td>
                    <td class="text-right">&mdash;</td>
                    <td class="text-right">&mdash;</td>
                    <td class="text-right"><?= naira_pdf($row['running_balance']) ?></td>
                </tr>
            <?php else: ?>
                <tr>
                    <td><?= View::e(date('d/m/Y', strtotime($row['date']))) ?></td>
                    <td><?= $row['type'] === 'topup' ? 'Top-up' : 'Expense' ?> <?= $badge((int) $row['is_historical'] === 1) ?></td>
                    <td><?= View::e($row['document_no'] ?? '—') ?></td>
                    <td><?= View::e($row['payee'] ?? '—') ?></td>
                    <td><?= View::e($row['department_name'] ?? '—') ?></td>
                    <td class="text-right text-success"><?= $row['amount_in'] > 0 ? naira_pdf($row['amount_in']) : '&mdash;' ?></td>
                    <td class="text-right text-error"><?= $row['amount_out'] > 0 ? naira_pdf($row['amount_out']) : '&mdash;' ?></td>
                    <td class="text-right"><?= naira_pdf($row['running_balance']) ?></td>
                </tr>
            <?php endif; ?>
        <?php endforeach; ?>
        <?php if (!$data['has_transactions']): ?>
            <tr><td colspan="8">No fund movement in this date range &mdash; the balances shown are brought forward.</td></tr>
        <?php endif; ?>
        <tr class="total-row">
            <td colspan="5">Total</td>
            <td class="text-right"><?= naira_pdf($data['total_in']) ?></td>
            <td class="text-right"><?= naira_pdf($data['total_out']) ?></td>
            <td class="text-right"><?= naira_pdf($closing) ?></td>
        </tr>
    </tbody>
</table>

<p class="text-muted" style="font-size: 8px; margin-top: 8px;">
    Running balance begins from the opening balance (balance as of the day before the range start, Tech Spec &sect;10) carried into each row by a SQL window function over this date range (Tech Spec &sect;14a); each month in the range opens with its own brought-forward row; figures include backfilled entries where marked.
</p>