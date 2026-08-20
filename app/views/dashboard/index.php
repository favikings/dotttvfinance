<?php /** @var array<int, array> $accounts @var array<int, array> $activity @var string $period */ ?>
<div class="space-y-6">
    <div>
        <h1 class="text-headline-md">Dashboard</h1>
        <p class="text-body-md text-on-surface-variant mt-1">Fund balance and recent activity across the float.</p>
    </div>

    <?= flash_messages() ?>

    <?php if (empty($accounts)): ?>
        <div class="bg-surface-container-lowest rounded-lg border border-outline-variant p-12 text-center">
            <p class="text-sm font-medium text-on-surface mb-1">No fund account configured</p>
            <p class="text-sm text-on-surface-variant">Ask your Super Admin to set up the operating float in Settings.</p>
        </div>
    <?php else: ?>
        <div class="flex items-center justify-between gap-3 flex-wrap">
            <p class="text-label-sm uppercase text-on-surface-variant">Funds Received &amp; Funds Spent period</p>
            <form method="get" action="<?= View::e(url('/')) ?>" class="flex items-center gap-2">
                <label for="period" class="sr-only">Period</label>
                <select id="period" name="period" onchange="this.form.submit()"
                        class="px-3 py-2 rounded border border-outline bg-surface-container-lowest text-on-surface text-sm focus:outline-none focus:ring-2 focus:ring-secondary-container focus:border-transparent">
                    <option value="month" <?= $period === 'month' ? 'selected' : '' ?>>This Month</option>
                    <option value="quarter" <?= $period === 'quarter' ? 'selected' : '' ?>>This Quarter</option>
                    <option value="all" <?= $period === 'all' ? 'selected' : '' ?>>All Time</option>
                </select>
            </form>
        </div>

        <?php foreach ($accounts as $account): ?>
            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-4">
                <?= metric_card('Current Balance', naira($account['current_balance'])) ?>
                <?= metric_card('Funds Received', naira($account['total_topups'])) ?>
                <?= metric_card('Funds Spent', naira($account['total_spent'])) ?>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>

    <div class="bg-surface-container-lowest rounded-lg border border-outline-variant overflow-hidden">
        <div class="px-4 py-3 border-b border-outline-variant">
            <h2 class="text-label-sm uppercase text-on-surface-variant">Recent activity</h2>
        </div>
        <div class="overflow-x-auto table-scroll">
            <table class="w-full text-sm min-w-[640px]">
            <thead class="bg-surface-container-low border-b border-outline-variant">
                <tr>
                    <th class="text-left px-4 py-3 font-medium text-on-surface-variant">Date</th>
                    <th class="text-left px-4 py-3 font-medium text-on-surface-variant">Type</th>
                    <th class="text-left px-4 py-3 font-medium text-on-surface-variant">Details</th>
                    <th class="text-right px-4 py-3 font-medium text-on-surface-variant">Amount</th>
                    <th class="text-left px-4 py-3 font-medium text-on-surface-variant">Status</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-outline-variant">
                <?php if (empty($activity)): ?>
                    <tr>
                        <td class="px-4 py-12 text-center text-on-surface-variant" colspan="5">
                            No activity yet — record an expense or top-up to see it here.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($activity as $row): ?>
                        <?php $isTopup = $row['type'] === 'topup'; ?>
                        <tr class="hover:bg-surface-container-low transition-colors">
                            <td class="px-4 py-3 text-on-surface whitespace-nowrap"><?= View::e(date('d/m/Y', strtotime($row['date']))) ?></td>
                            <td class="px-4 py-3">
                                <span class="text-label-md uppercase whitespace-nowrap <?= $isTopup ? 'text-success' : 'text-on-surface-variant' ?>">
                                    <?= $isTopup ? 'Top-up' : 'Expense' ?>
                                </span>
                            </td>
                            <td class="px-4 py-3">
                                <p class="text-on-surface">
                                    <?= View::e($isTopup ? ($row['reference'] !== null && $row['reference'] !== '' ? $row['reference'] : 'Fund top-up') : $row['payee']) ?>
                                </p>
                                <?php if ($row['note'] !== null && $row['note'] !== ''): ?>
                                    <p class="text-on-surface-variant text-xs mt-0.5 max-w-[260px] truncate"><?= View::e($row['note']) ?></p>
                                <?php endif; ?>
                            </td>
                            <td class="px-4 py-3 text-right whitespace-nowrap <?= $isTopup ? 'text-success' : 'text-on-surface' ?>">
                                <?= $isTopup ? '+' : '−' ?><?= naira($row['amount']) ?>
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex flex-wrap items-center gap-1.5">
                                    <?= status_badge($row['status']) ?>
                                    <?= backfilled_badge($row['is_historical']) ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
        </div>
    </div>
</div>
