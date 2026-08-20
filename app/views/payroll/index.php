<?php
/**
 * @var array<int, array> $runs
 * @var bool $canCreate
 */
?>
<div class="space-y-6">
    <div class="flex items-start justify-between gap-4">
        <div>
            <h1 class="text-headline-md">Payroll</h1>
            <p class="text-body-md text-on-surface-variant mt-1">One run per period. GM signs off, then payment status is tracked per employee.</p>
        </div>
        <?php if ($canCreate): ?>
            <div class="shrink-0">
                <a href="<?= View::e(url('/payroll/create')) ?>"
                   class="bg-secondary-container text-on-secondary-container font-medium text-sm px-4 py-2.5 rounded hover:opacity-90 transition-opacity">
                    New Payroll Run
                </a>
            </div>
        <?php endif; ?>
    </div>

    <?= flash_messages() ?>

    <?php if (empty($runs)): ?>
        <div class="bg-surface-container-lowest rounded-lg border border-outline-variant p-8 text-center">
            <p class="text-sm font-medium text-on-surface mb-1">No payroll runs yet</p>
            <p class="text-sm text-on-surface-variant">Create a run for a period to start adding employees.</p>
        </div>
    <?php else: ?>
        <div class="bg-surface-container-lowest rounded-lg border border-outline-variant overflow-hidden">
            <div class="overflow-x-auto table-scroll">
                <table class="w-full text-sm min-w-[640px]">
                <thead class="bg-surface-container-low border-b border-outline-variant">
                    <tr>
                        <th class="text-left px-4 py-3 font-medium text-on-surface-variant">Period</th>
                        <th class="text-left px-4 py-3 font-medium text-on-surface-variant">Status</th>
                        <th class="text-right px-4 py-3 font-medium text-on-surface-variant">Employees</th>
                        <th class="text-right px-4 py-3 font-medium text-on-surface-variant">Total Net</th>
                        <th class="text-left px-4 py-3 font-medium text-on-surface-variant">Created By</th>
                        <th class="text-left px-4 py-3 font-medium text-on-surface-variant">Approved By</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-outline-variant">
                    <?php foreach ($runs as $run): ?>
                        <tr class="hover:bg-surface-container-low transition-colors cursor-pointer"
                            onclick="window.location.href='<?= View::e(url('/payroll/' . (int) $run['id'])) ?>'">
                            <td class="px-4 py-3 text-on-surface font-medium whitespace-nowrap">
                                <?= View::e(PayrollRun::monthName((int) $run['period_month'])) ?> <?= (int) $run['period_year'] ?>
                            </td>
                            <td class="px-4 py-3"><?= status_badge($run['status']) ?></td>
                            <td class="px-4 py-3 text-on-surface text-right"><?= (int) $run['item_count'] ?></td>
                            <td class="px-4 py-3 text-on-surface text-right whitespace-nowrap"><?= naira($run['total_net_salary']) ?></td>
                            <td class="px-4 py-3 text-on-surface whitespace-nowrap"><?= View::e($run['created_by_name'] ?? '—') ?></td>
                            <td class="px-4 py-3 text-on-surface whitespace-nowrap"><?= View::e($run['approved_by_name'] ?? '—') ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            </div>
        </div>
    <?php endif; ?>
</div>
