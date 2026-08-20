<?php
/**
 * @var string $to
 * @var array{rows: array<int, array{id: int, expense_no: ?string, date: string, payee: string,
 *            amount: float, is_historical: int, department_name: ?string}>, total: float,
 *            has_historical: bool} $data
 */
?>
<div class="space-y-6">
    <div>
        <h1 class="text-headline-md">Reports</h1>
        <p class="text-body-md text-on-surface-variant mt-1">Board-ready financial reports, generated straight from the ledger.</p>
    </div>

    <?= report_tabs('payables', $to, $to) ?>
    <?= date_range_filter('/reports/payables', $to, $to, '/reports/payables/pdf', singleDate: true, excelAction: '/reports/payables/excel') ?>
    <?= sync_indicator() ?>

    <div class="bg-surface-container-lowest rounded-lg border border-outline-variant p-6">
        <div class="mb-6">
            <h2 class="text-headline-sm">Payables</h2>
            <p class="text-body-md text-on-surface-variant mt-1">
                Approved expenses not yet paid, as of <?= View::e(date('d M Y', strtotime($to))) ?>
            </p>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
            <?= metric_card('Total Owed', naira($data['total']), count($data['rows']) . ' expense(s)') ?>
        </div>

        <div class="bg-surface-container-lowest rounded-lg border border-outline-variant overflow-hidden">
            <div class="overflow-x-auto table-scroll">
                <table class="w-full text-sm min-w-[560px]">
                <thead class="bg-surface-container-low border-b border-outline-variant">
                    <tr>
                        <th class="text-left px-4 py-3 font-medium text-on-surface-variant">Expense No</th>
                        <th class="text-left px-4 py-3 font-medium text-on-surface-variant">Date</th>
                        <th class="text-left px-4 py-3 font-medium text-on-surface-variant">Payee</th>
                        <th class="text-left px-4 py-3 font-medium text-on-surface-variant">Department</th>
                        <th class="text-right px-4 py-3 font-medium text-on-surface-variant">Amount</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-outline-variant">
                    <?php if (empty($data['rows'])): ?>
                        <tr>
                            <td class="px-4 py-12 text-center text-on-surface-variant" colspan="5">Nothing owed as of this date.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($data['rows'] as $row): ?>
                            <tr class="hover:bg-surface-container-low transition-colors">
                                <td class="px-4 py-3 text-on-surface font-medium whitespace-nowrap">
                                    <?= View::e($row['expense_no'] ?? '—') ?>
                                    <?php if ((int) $row['is_historical'] === 1): ?> <?= backfilled_badge(true) ?><?php endif; ?>
                                </td>
                                <td class="px-4 py-3 text-on-surface whitespace-nowrap"><?= View::e(date('d/m/Y', strtotime($row['date']))) ?></td>
                                <td class="px-4 py-3 text-on-surface whitespace-nowrap"><?= View::e($row['payee']) ?></td>
                                <td class="px-4 py-3 text-on-surface-variant whitespace-nowrap"><?= View::e($row['department_name'] ?? '—') ?></td>
                                <td class="px-4 py-3 text-on-surface text-right whitespace-nowrap"><?= naira($row['amount']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
                <tfoot class="bg-surface-container-low border-t border-outline-variant">
                    <tr>
                        <td class="px-4 py-3 text-on-surface font-semibold" colspan="4">Total</td>
                        <td class="px-4 py-3 text-on-surface text-right font-semibold whitespace-nowrap"><?= naira($data['total']) ?></td>
                    </tr>
                </tfoot>
            </table>
            </div>
        </div>
    </div>
</div>
