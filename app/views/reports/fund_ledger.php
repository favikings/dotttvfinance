<?php
/**
 * @var string $from
 * @var string $to
 * @var array{rows: array<int, array{date: string, created_at: string, type: string,
 *            document_no: ?string, payee: ?string, department_id: ?int,
 *            department_name: ?string, description: ?string, amount_in: float,
 *            amount_out: float, is_historical: int, running_balance: float}>,
 *            opening_balance: float, opening_as_of: string, total_in: float,
 *            total_out: float, has_historical: bool} $data
 */
$closing = $data['opening_balance'] + $data['total_in'] - $data['total_out'];
?>
<div class="space-y-6">
    <div>
        <h1 class="text-headline-md">Reports</h1>
        <p class="text-body-md text-on-surface-variant mt-1">Board-ready financial reports, generated straight from the ledger.</p>
    </div>

    <?= report_tabs('fund-ledger', $from, $to) ?>
    <?= date_range_filter('/reports/fund-ledger', $from, $to, '/reports/fund-ledger/pdf', excelAction: '/reports/fund-ledger/excel') ?>
    <?= sync_indicator() ?>

    <div class="bg-surface-container-lowest rounded-lg border border-outline-variant p-6">
        <div class="mb-6">
            <h2 class="text-headline-sm">Fund Ledger</h2>
            <p class="text-body-md text-on-surface-variant mt-1">
                <?= View::e(date('d M Y', strtotime($from))) ?> &ndash; <?= View::e(date('d M Y', strtotime($to))) ?>
                &middot; every approved top-up and expense, all departments, in one chronological list
            </p>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-4 mb-6">
            <?= metric_card('Opening Balance', format_amount($data['opening_balance'], 'neutral'), 'brought forward as of ' . View::e(date('d/m/Y', strtotime($from . ' -1 day')))) ?>
            <?= metric_card('Total In', format_amount($data['total_in'], 'credit')) ?>
            <?= metric_card('Total Out', format_amount($data['total_out'], 'debit')) ?>
            <?= metric_card('Closing Balance', format_amount($closing, 'neutral')) ?>
        </div>

        <div class="bg-surface-container-lowest rounded-lg border border-outline-variant overflow-hidden">
            <div class="overflow-x-auto table-scroll">
                <table class="w-full text-sm min-w-[880px]">
                <thead class="bg-surface-container-low border-b border-outline-variant">
                    <tr>
                        <th class="text-left px-4 py-3 font-medium text-on-surface-variant">Date</th>
                        <th class="text-left px-4 py-3 font-medium text-on-surface-variant">Type</th>
                        <th class="text-left px-4 py-3 font-medium text-on-surface-variant">Doc No</th>
                        <th class="text-left px-4 py-3 font-medium text-on-surface-variant">Payee</th>
                        <th class="text-left px-4 py-3 font-medium text-on-surface-variant">Description</th>
                        <th class="text-left px-4 py-3 font-medium text-on-surface-variant">Department</th>
                        <th class="text-right px-4 py-3 font-medium text-on-surface-variant">In</th>
                        <th class="text-right px-4 py-3 font-medium text-on-surface-variant">Out</th>
                        <th class="text-right px-4 py-3 font-medium text-on-surface-variant">Balance</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-outline-variant">
                    <?php if (empty($data['rows'])): ?>
                        <tr>
                            <td class="px-4 py-12 text-center text-on-surface-variant" colspan="9">No fund activity in this date range.</td>
                        </tr>
                    <?php else: ?>
                        <tr class="bg-surface-container-low">
                            <td class="px-4 py-3 text-on-surface-variant whitespace-nowrap"><?= View::e(date('d/m/Y', strtotime($from . ' -1 day'))) ?></td>
                            <td class="px-4 py-3 text-on-surface-variant font-medium" colspan="4">Opening Balance brought forward</td>
                            <td class="px-4 py-3"></td>
                            <td class="px-4 py-3"></td>
                            <td class="px-4 py-3"></td>
                            <td class="px-4 py-3 text-right font-medium"><?= format_amount($data['opening_balance'], 'neutral') ?></td>
                        </tr>
                        <?php foreach ($data['rows'] as $row): ?>
                            <tr class="hover:bg-surface-container-low transition-colors">
                                <td class="px-4 py-3 text-on-surface whitespace-nowrap"><?= View::e(date('d/m/Y', strtotime($row['date']))) ?></td>
                                <td class="px-4 py-3 whitespace-nowrap">
                                    <?php if ($row['type'] === 'topup'): ?>
                                        <span class="text-success font-medium">Top-up</span>
                                    <?php else: ?>
                                        <span class="text-on-surface font-medium">Expense</span>
                                    <?php endif; ?>
                                    <?= backfilled_badge($row['is_historical']) ?>
                                </td>
                                <td class="px-4 py-3 text-on-surface-variant whitespace-nowrap"><?= View::e($row['document_no'] ?? '—') ?></td>
                                <td class="px-4 py-3 text-on-surface whitespace-nowrap"><?= View::e($row['payee'] ?? '—') ?></td>
                                <td class="px-4 py-3 text-on-surface whitespace-nowrap"><?= View::e($row['description'] ?? '—') ?></td>
                                <td class="px-4 py-3 text-on-surface-variant whitespace-nowrap"><?= View::e($row['department_name'] ?? '—') ?></td>
                                <td class="px-4 py-3 text-right whitespace-nowrap"><?= $row['amount_in'] > 0 ? format_amount($row['amount_in'], 'credit') : '—' ?></td>
                                <td class="px-4 py-3 text-right whitespace-nowrap"><?= $row['amount_out'] > 0 ? format_amount($row['amount_out'], 'debit') : '—' ?></td>
                                <td class="px-4 py-3 text-right whitespace-nowrap"><?= format_amount($row['running_balance'], 'neutral') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
                <tfoot class="bg-surface-container-low border-t border-outline-variant">
                    <tr>
                        <td class="px-4 py-3 text-on-surface font-semibold" colspan="6">Total</td>
                        <td class="px-4 py-3 text-right whitespace-nowrap"><?= format_amount($data['total_in'], 'credit') ?></td>
                        <td class="px-4 py-3 text-right whitespace-nowrap"><?= format_amount($data['total_out'], 'debit') ?></td>
                        <td class="px-4 py-3 text-right whitespace-nowrap"><?= format_amount($closing, 'neutral') ?></td>
                    </tr>
                </tfoot>
            </table>
            </div>
        </div>

        <p class="text-label-sm text-on-surface-variant mt-4">
            The running balance begins from the opening balance (balance as of the day before the range start, Tech Spec §10) carried
            into each row via a SQL window function over this date range (Tech Spec §14a) &mdash;
            the same balance predicates used everywhere else in the app, never recalculated in PHP.
            <?php if ($data['has_historical']): ?> Rows marked Backfilled are reconstructed paper-book entries.<?php endif; ?>
        </p>
    </div>
</div>