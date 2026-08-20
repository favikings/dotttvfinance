<?php
/**
 * @var string $from
 * @var string $to
 * @var array{rows: array<int, array{id: int, invoice_no: string, date: string, client: string,
 *            amount: float, due_date: ?string, payment_status: string, department_name: ?string,
 *            is_overdue: bool}>, total: float, overdue_total: float} $data
 */
?>
<table class="metrics">
    <tr>
        <td><div class="metric-label">Total Outstanding</div><div class="metric-value"><?= naira_pdf($data['total']) ?></div></td>
        <td><div class="metric-label">Overdue</div><div class="metric-value"><?= naira_pdf($data['overdue_total']) ?></div></td>
    </tr>
</table>

<table class="report-table">
    <thead>
        <tr>
            <th>Invoice No</th>
            <th>Date</th>
            <th>Client</th>
            <th>Due</th>
            <th class="text-right">Amount</th>
            <th>Status</th>
        </tr>
    </thead>
    <tbody>
        <?php if (empty($data['rows'])): ?>
            <tr><td colspan="6">Nothing outstanding for this period.</td></tr>
        <?php else: ?>
            <?php foreach ($data['rows'] as $row): ?>
                <tr>
                    <td><?= View::e($row['invoice_no']) ?></td>
                    <td><?= View::e(date('d/m/Y', strtotime($row['date']))) ?></td>
                    <td><?= View::e($row['client']) ?></td>
                    <td><?= $row['due_date'] !== null ? View::e(date('d/m/Y', strtotime($row['due_date']))) : '&mdash;' ?></td>
                    <td class="text-right"><?= naira_pdf($row['amount']) ?></td>
                    <td class="<?= $row['is_overdue'] ? 'text-error' : '' ?>"><?= View::e(ucfirst($row['payment_status'])) ?><?= $row['is_overdue'] ? ' (Overdue)' : '' ?></td>
                </tr>
            <?php endforeach; ?>
        <?php endif; ?>
        <tr class="total-row">
            <td colspan="4">Total</td>
            <td class="text-right"><?= naira_pdf($data['total']) ?></td>
            <td></td>
        </tr>
    </tbody>
</table>
