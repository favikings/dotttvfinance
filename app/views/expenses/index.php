<?php /** @var array<int, array> $expenses */ ?>
<div class="space-y-6">
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <h1 class="text-headline-md">Expenses</h1>
            <p class="text-body-md text-on-surface-variant mt-1">Live expense entries and their approval status.</p>
        </div>
        <div class="shrink-0 flex gap-3">
            <a href="<?= View::e(url('/historical-entry/expenses')) ?>"
               class="border border-outline text-on-surface font-medium text-sm px-4 py-2.5 rounded hover:bg-surface-container transition-colors">
                Bulk Historical Entry
            </a>
            <a href="<?= View::e(url('/expenses/create')) ?>"
               class="bg-secondary-container text-on-secondary-container font-medium text-sm px-4 py-2.5 rounded hover:opacity-90 transition-opacity">
                New Expense
            </a>
        </div>
    </div>

    <?= flash_messages() ?>

    <div class="bg-surface-container-lowest rounded-lg border border-outline-variant overflow-hidden">
        <div class="overflow-x-auto table-scroll">
            <table class="w-full text-sm min-w-[760px]">
            <thead class="bg-surface-container-low border-b border-outline-variant">
                <tr>
                    <th class="text-left px-4 py-3 font-medium text-on-surface-variant">Expense No</th>
                    <th class="text-left px-4 py-3 font-medium text-on-surface-variant">Date</th>
                    <th class="text-left px-4 py-3 font-medium text-on-surface-variant">Payee</th>
                    <th class="text-left px-4 py-3 font-medium text-on-surface-variant">Description</th>
                    <th class="text-left px-4 py-3 font-medium text-on-surface-variant">Department</th>
                    <th class="text-right px-4 py-3 font-medium text-on-surface-variant">Amount</th>
                    <th class="text-left px-4 py-3 font-medium text-on-surface-variant">Status</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-outline-variant">
                <?php if (empty($expenses)): ?>
                    <tr>
                        <td class="px-4 py-12 text-center text-on-surface-variant" colspan="7">
                            No expenses yet — record your first one.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($expenses as $expense): ?>
                        <tr class="hover:bg-surface-container-low transition-colors">
                            <td class="px-4 py-3 text-on-surface font-medium whitespace-nowrap"><?= View::e($expense['expense_no'] ?? '—') ?></td>
                            <td class="px-4 py-3 text-on-surface whitespace-nowrap"><?= View::e(date('d/m/Y', strtotime($expense['date']))) ?></td>
                            <td class="px-4 py-3 text-on-surface whitespace-nowrap"><?= View::e($expense['payee']) ?></td>
                            <td class="px-4 py-3 text-on-surface-variant max-w-[240px] truncate"><?= View::e($expense['description']) ?></td>
                            <td class="px-4 py-3 text-on-surface whitespace-nowrap"><?= View::e($expense['department_name'] ?? '—') ?></td>
                            <td class="px-4 py-3 text-right whitespace-nowrap"><?= format_amount($expense['amount'], 'debit') ?></td>
                            <td class="px-4 py-3">
                                <div class="flex flex-wrap items-center gap-1.5">
                                    <?= status_badge($expense['status']) ?>
                                    <?= backfilled_badge($expense['is_historical']) ?>
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