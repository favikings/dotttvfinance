<?php
/**
 * @var string $from
 * @var string $to
 * @var array{days_in_range: int, rows: array<int, array{id: int, name: string, total: float,
 *            has_historical: bool, cnt: int, monthly_budget: ?float, budget: ?float,
 *            variance: ?float}>, grand_total_actual: float, grand_total_budget: float} $data
 * @var bool $canEditBudgets
 */
?>
<div class="space-y-6" x-data="{ editing: false }">
    <div>
        <h1 class="text-headline-md">Reports</h1>
        <p class="text-body-md text-on-surface-variant mt-1">Board-ready financial reports, generated straight from the ledger.</p>
    </div>

    <?= report_tabs('budget-vs-actual', $from, $to) ?>
    <?= date_range_filter('/reports/budget-vs-actual', $from, $to, '/reports/budget-vs-actual/pdf', excelAction: '/reports/budget-vs-actual/excel') ?>
    <?= sync_indicator() ?>

    <?= flash_messages() ?>

    <div class="bg-surface-container-lowest rounded-lg border border-outline-variant p-6">
        <div class="flex items-start justify-between gap-4 mb-6">
            <div>
                <h2 class="text-headline-sm">Budget vs. Actual</h2>
                <p class="text-body-md text-on-surface-variant mt-1">
                    <?= View::e(date('d M Y', strtotime($from))) ?> &ndash; <?= View::e(date('d M Y', strtotime($to))) ?>
                    &middot; budgets are configured per month, prorated here for <?= $data['days_in_range'] ?> day(s)
                </p>
            </div>
            <?php if ($canEditBudgets): ?>
                <button type="button" x-on:click="editing = !editing"
                        class="shrink-0 border border-outline text-on-surface font-medium text-sm px-4 py-2.5 rounded hover:bg-surface-container transition-colors">
                    <span x-text="editing ? 'Cancel' : 'Edit Budgets'"></span>
                </button>
            <?php endif; ?>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-4 mb-6">
            <?= metric_card('Actual Spend', naira($data['grand_total_actual'])) ?>
            <?= metric_card('Total Budget', $data['grand_total_budget'] > 0 ? naira($data['grand_total_budget']) : 'Not set') ?>
            <?= metric_card('Variance', $data['grand_total_budget'] > 0 ? naira($data['grand_total_budget'] - $data['grand_total_actual']) : '—') ?>
        </div>

        <?php if ($canEditBudgets): ?>
            <form method="post" action="<?= View::e(url('/reports/budget-vs-actual/save')) ?>" x-show="editing" x-cloak class="mb-4">
                <?= Csrf::field() ?>
                <input type="hidden" name="from" value="<?= View::e($from) ?>">
                <input type="hidden" name="to" value="<?= View::e($to) ?>">
        <?php endif; ?>

        <div class="bg-surface-container-lowest rounded-lg border border-outline-variant overflow-hidden">
            <div class="overflow-x-auto table-scroll">
                <table class="w-full text-sm min-w-[520px]">
                <thead class="bg-surface-container-low border-b border-outline-variant">
                    <tr>
                        <th class="text-left px-4 py-3 font-medium text-on-surface-variant">Department</th>
                        <th class="text-right px-4 py-3 font-medium text-on-surface-variant">Actual</th>
                        <th class="text-right px-4 py-3 font-medium text-on-surface-variant">Budget (prorated)</th>
                        <th class="text-right px-4 py-3 font-medium text-on-surface-variant">Variance</th>
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
                                <td class="px-4 py-3 text-on-surface text-right whitespace-nowrap"><?= naira($row['total']) ?></td>
                                <td class="px-4 py-3 text-right whitespace-nowrap">
                                    <?php if ($canEditBudgets): ?>
                                        <div class="flex items-center justify-end gap-2">
                                            <span class="text-on-surface-variant" x-show="!editing"><?= $row['budget'] !== null ? naira($row['budget']) : '—' ?></span>
                                            <template x-if="editing">
                                                <div class="flex items-center gap-1.5">
                                                    <span class="text-on-surface-variant text-xs">₦/mo</span>
                                                    <input type="number" step="0.01" min="0" name="budget[<?= (int) $row['id'] ?>]"
                                                           value="<?= $row['monthly_budget'] !== null ? View::e(number_format($row['monthly_budget'], 2, '.', '')) : '' ?>"
                                                           placeholder="Not set"
                                                           class="w-32 px-2 py-1.5 rounded border border-outline bg-surface-container-lowest text-on-surface text-sm text-right
                                                                  focus:outline-none focus:ring-2 focus:ring-secondary-container focus:border-transparent">
                                                </div>
                                            </template>
                                        </div>
                                    <?php else: ?>
                                        <?= $row['budget'] !== null ? naira($row['budget']) : '—' ?>
                                    <?php endif; ?>
                                </td>
                                <td class="px-4 py-3 text-right whitespace-nowrap <?= $row['variance'] !== null && $row['variance'] < 0 ? 'text-error' : 'text-on-surface' ?>">
                                    <?= $row['variance'] !== null ? naira($row['variance']) : '—' ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
                <tfoot class="bg-surface-container-low border-t border-outline-variant">
                    <tr>
                        <td class="px-4 py-3 text-on-surface font-semibold">Total</td>
                        <td class="px-4 py-3 text-on-surface text-right font-semibold whitespace-nowrap"><?= naira($data['grand_total_actual']) ?></td>
                        <td class="px-4 py-3 text-on-surface text-right font-semibold whitespace-nowrap"><?= $data['grand_total_budget'] > 0 ? naira($data['grand_total_budget']) : '—' ?></td>
                        <td class="px-4 py-3 text-on-surface text-right font-semibold whitespace-nowrap"><?= $data['grand_total_budget'] > 0 ? naira($data['grand_total_budget'] - $data['grand_total_actual']) : '—' ?></td>
                    </tr>
                </tfoot>
            </table>
            </div>
        </div>

        <?php if ($canEditBudgets): ?>
                <div class="mt-4">
                    <button type="submit"
                            class="bg-primary text-on-primary font-medium text-sm px-4 py-2.5 rounded hover:opacity-90 transition-opacity">
                        Save Budgets
                    </button>
                </div>
            </form>
        <?php endif; ?>

        <p class="text-label-sm text-on-surface-variant mt-4">
            Department budgets are stored as a monthly figure and prorated for the selected date range
            (<?= $data['days_in_range'] ?> of an assumed 30.44-day month) — a department with no budget configured shows "Not set" rather than a false ₦0 variance.
        </p>
    </div>
</div>
