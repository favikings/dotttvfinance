<?php
/**
 * @var string $from
 * @var string $to
 * @var array{rows: array<int, array{id: int, invoice_no: string, date: string, client: string,
 *            amount: float, due_date: ?string, payment_status: string, department_name: ?string,
 *            is_overdue: bool}>, total: float, overdue_total: float} $data
 */
?>
<div class="space-y-6">
    <div>
        <h1 class="text-headline-md">Reports</h1>
        <p class="text-body-md text-on-surface-variant mt-1">Board-ready financial reports, generated straight from the ledger.</p>
    </div>

    <?= report_tabs('receivables', $from, $to) ?>
    <?= date_range_filter('/reports/receivables', $from, $to, '/reports/receivables/pdf', excelAction: '/reports/receivables/excel') ?>
    <?= sync_indicator() ?>

    <div class="bg-surface-container-lowest rounded-lg border border-outline-variant p-6">
        <div class="mb-6">
            <h2 class="text-headline-sm">Receivables</h2>
            <p class="text-body-md text-on-surface-variant mt-1">
                Approved invoices not yet fully paid, dated <?= View::e(date('d M Y', strtotime($from))) ?> &ndash; <?= View::e(date('d M Y', strtotime($to))) ?>
            </p>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-6">
            <?= metric_card('Total Outstanding', naira($data['total'])) ?>
            <?= metric_card('Overdue', naira($data['overdue_total'])) ?>
        </div>

        <div class="bg-surface-container-lowest rounded-lg border border-outline-variant overflow-hidden">
            <div class="overflow-x-auto table-scroll">
                <table class="w-full text-sm min-w-[760px]">
                <thead class="bg-surface-container-low border-b border-outline-variant">
                    <tr>
                        <th class="text-left px-4 py-3 font-medium text-on-surface-variant">Invoice No</th>
                        <th class="text-left px-4 py-3 font-medium text-on-surface-variant">Date</th>
                        <th class="text-left px-4 py-3 font-medium text-on-surface-variant">Client</th>
                        <th class="text-left px-4 py-3 font-medium text-on-surface-variant">Department</th>
                        <th class="text-left px-4 py-3 font-medium text-on-surface-variant">Due</th>
                        <th class="text-right px-4 py-3 font-medium text-on-surface-variant">Amount</th>
                        <th class="text-left px-4 py-3 font-medium text-on-surface-variant">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-outline-variant">
                    <?php if (empty($data['rows'])): ?>
                        <tr>
                            <td class="px-4 py-12 text-center text-on-surface-variant" colspan="7">Nothing outstanding for this period.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($data['rows'] as $row): ?>
                            <tr class="<?= $row['is_overdue'] ? 'bg-error-container/20' : 'hover:bg-surface-container-low' ?> transition-colors">
                                <td class="px-4 py-3 text-on-surface font-medium whitespace-nowrap"><?= View::e($row['invoice_no']) ?></td>
                                <td class="px-4 py-3 text-on-surface whitespace-nowrap"><?= View::e(date('d/m/Y', strtotime($row['date']))) ?></td>
                                <td class="px-4 py-3 text-on-surface whitespace-nowrap"><?= View::e($row['client']) ?></td>
                                <td class="px-4 py-3 text-on-surface-variant whitespace-nowrap"><?= View::e($row['department_name'] ?? '—') ?></td>
                                <td class="px-4 py-3 text-on-surface whitespace-nowrap"><?= $row['due_date'] !== null ? View::e(date('d/m/Y', strtotime($row['due_date']))) : '—' ?></td>
                                <td class="px-4 py-3 text-on-surface text-right whitespace-nowrap"><?= naira($row['amount']) ?></td>
                                <td class="px-4 py-3">
                                    <div class="flex flex-wrap items-center gap-1.5">
                                        <?= status_badge($row['payment_status']) ?>
                                        <?php if ($row['is_overdue']): ?><?= status_badge('overdue') ?><?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
                <tfoot class="bg-surface-container-low border-t border-outline-variant">
                    <tr>
                        <td class="px-4 py-3 text-on-surface font-semibold" colspan="5">Total</td>
                        <td class="px-4 py-3 text-on-surface text-right font-semibold whitespace-nowrap"><?= naira($data['total']) ?></td>
                        <td class="px-4 py-3"></td>
                    </tr>
                </tfoot>
            </table>
            </div>
        </div>
    </div>
</div>
