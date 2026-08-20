<?php
/**
 * @var string $from
 * @var string $to
 * @var array{rows: array<int, array{id: int, name: string, total: float, historical_total: float,
 *            has_historical: bool, cnt: int, pct: float}>, grand_total: float} $data
 */
?>
<div class="space-y-6">
    <div>
        <h1 class="text-headline-md">Reports</h1>
        <p class="text-body-md text-on-surface-variant mt-1">Board-ready financial reports, generated straight from the ledger.</p>
    </div>

    <?= report_tabs('departmental-expenses', $from, $to) ?>
    <?= date_range_filter('/reports/departmental-expenses', $from, $to, '/reports/departmental-expenses/pdf', excelAction: '/reports/departmental-expenses/excel') ?>
    <?= sync_indicator() ?>

    <div class="bg-surface-container-lowest rounded-lg border border-outline-variant p-6">
        <div class="mb-6">
            <h2 class="text-headline-sm">Departmental Expenses</h2>
            <p class="text-body-md text-on-surface-variant mt-1">
                <?= View::e(date('d M Y', strtotime($from))) ?> &ndash; <?= View::e(date('d M Y', strtotime($to))) ?>
            </p>
        </div>

        <div class="bg-surface-container-lowest rounded-lg border border-outline-variant overflow-hidden">
            <div class="overflow-x-auto table-scroll">
                <table class="w-full text-sm min-w-[520px]">
                <thead class="bg-surface-container-low border-b border-outline-variant">
                    <tr>
                        <th class="text-left px-4 py-3 font-medium text-on-surface-variant">Department</th>
                        <th class="text-right px-4 py-3 font-medium text-on-surface-variant">Expenses</th>
                        <th class="text-right px-4 py-3 font-medium text-on-surface-variant">Amount</th>
                        <th class="text-right px-4 py-3 font-medium text-on-surface-variant">% of Total</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-outline-variant">
                    <?php if (empty($data['rows'])): ?>
                        <tr>
                            <td class="px-4 py-12 text-center text-on-surface-variant" colspan="4">No departments configured.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($data['rows'] as $row): ?>
                            <tr class="hover:bg-surface-container-low transition-colors">
                                <td class="px-4 py-3 text-on-surface font-medium whitespace-nowrap">
                                    <?= View::e($row['name']) ?>
                                    <?php if ($row['has_historical']): ?> <?= backfilled_badge(true) ?><?php endif; ?>
                                </td>
                                <td class="px-4 py-3 text-on-surface-variant text-right whitespace-nowrap"><?= $row['cnt'] ?></td>
                                <td class="px-4 py-3 text-on-surface text-right whitespace-nowrap"><?= naira($row['total']) ?></td>
                                <td class="px-4 py-3 text-on-surface-variant text-right whitespace-nowrap"><?= number_format($row['pct'], 1) ?>%</td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
                <tfoot class="bg-surface-container-low border-t border-outline-variant">
                    <tr>
                        <td class="px-4 py-3 text-on-surface font-semibold">Total</td>
                        <td class="px-4 py-3"></td>
                        <td class="px-4 py-3 text-on-surface text-right font-semibold whitespace-nowrap"><?= naira($data['grand_total']) ?></td>
                        <td class="px-4 py-3 text-on-surface-variant text-right font-semibold">100.0%</td>
                    </tr>
                </tfoot>
            </table>
            </div>
        </div>
    </div>
</div>
