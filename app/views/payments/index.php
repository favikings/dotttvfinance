<?php
/**
 * @var array<int, array> $vouchers
 * @var array<int, array> $awaitingPayment
 * @var array<int, array> $departments
 * @var bool $canCreate
 * @var array{date_from:string, date_to:string, department_id:int} $filters
 */
?>
<div class="space-y-6">
    <div>
        <h1 class="text-headline-md">Payments</h1>
        <p class="text-body-md text-on-surface-variant mt-1">Payment vouchers released against approved expenses.</p>
    </div>

    <?= flash_messages() ?>

    <?php if ($canCreate): ?>
        <div>
            <h2 class="text-headline-sm mb-3">Approved — Awaiting Payment</h2>

            <?php if (empty($awaitingPayment)): ?>
                <div class="bg-surface-container-lowest rounded-lg border border-outline-variant p-8 text-center">
                    <p class="text-sm font-medium text-on-surface mb-1">Nothing awaiting payment</p>
                    <p class="text-sm text-on-surface-variant">Approved expenses without a voucher yet will appear here.</p>
                </div>
            <?php else: ?>
                <div class="bg-surface-container-lowest rounded-lg border border-outline-variant overflow-hidden">
                    <div class="overflow-x-auto table-scroll">
<table class="w-full text-sm min-w-[640px]">
                        <thead class="bg-surface-container-low border-b border-outline-variant">
                            <tr>
                                <th class="text-left px-4 py-3 font-medium text-on-surface-variant">Expense No</th>
                                <th class="text-left px-4 py-3 font-medium text-on-surface-variant">Date</th>
                                <th class="text-left px-4 py-3 font-medium text-on-surface-variant">Payee</th>
                                <th class="text-left px-4 py-3 font-medium text-on-surface-variant">Department</th>
                                <th class="text-right px-4 py-3 font-medium text-on-surface-variant">Amount</th>
                                <th class="text-right px-4 py-3 font-medium text-on-surface-variant">Action</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-outline-variant">
                            <?php foreach ($awaitingPayment as $expense): ?>
                                <tr class="hover:bg-surface-container-low transition-colors">
                                    <td class="px-4 py-3 text-on-surface font-medium whitespace-nowrap"><?= View::e($expense['expense_no'] ?? '—') ?></td>
                                    <td class="px-4 py-3 text-on-surface whitespace-nowrap"><?= View::e(date('d/m/Y', strtotime($expense['date']))) ?></td>
                                    <td class="px-4 py-3 text-on-surface whitespace-nowrap"><?= View::e($expense['payee']) ?></td>
                                    <td class="px-4 py-3 text-on-surface whitespace-nowrap"><?= View::e($expense['department_name'] ?? '—') ?></td>
                                    <td class="px-4 py-3 text-on-surface text-right whitespace-nowrap"><?= naira($expense['amount']) ?></td>
                                    <td class="px-4 py-3 text-right">
                                        <a href="<?= View::e(url('/payments/create/' . (int) $expense['id'])) ?>"
                                           class="inline-flex items-center gap-1.5 bg-secondary-container text-on-secondary-container font-medium text-sm px-3 py-2 rounded hover:opacity-90 transition-opacity">
                                            Create Voucher
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <div>
        <h2 class="text-headline-sm mb-3">Payment Vouchers</h2>

        <form method="get" action="<?= View::e(url('/payments')) ?>"
              class="bg-surface-container-lowest rounded-lg border border-outline-variant p-4 mb-4 flex flex-wrap items-end gap-3">
            <div>
                <label class="block text-label-md text-on-surface-variant mb-1.5" for="filter-date-from">From</label>
                <input type="date" id="filter-date-from" name="date_from" value="<?= View::e($filters['date_from']) ?>"
                       class="px-3 py-2 rounded border border-outline bg-surface-container-lowest text-on-surface text-sm focus:outline-none focus:ring-2 focus:ring-secondary-container focus:border-transparent">
            </div>
            <div>
                <label class="block text-label-md text-on-surface-variant mb-1.5" for="filter-date-to">To</label>
                <input type="date" id="filter-date-to" name="date_to" value="<?= View::e($filters['date_to']) ?>"
                       class="px-3 py-2 rounded border border-outline bg-surface-container-lowest text-on-surface text-sm focus:outline-none focus:ring-2 focus:ring-secondary-container focus:border-transparent">
            </div>
            <div>
                <label class="block text-label-md text-on-surface-variant mb-1.5" for="filter-department">Department</label>
                <select id="filter-department" name="department_id"
                        class="px-3 py-2 rounded border border-outline bg-surface-container-lowest text-on-surface text-sm focus:outline-none focus:ring-2 focus:ring-secondary-container focus:border-transparent">
                    <option value="0">All departments</option>
                    <?php foreach ($departments as $department): ?>
                        <option value="<?= (int) $department['id'] ?>" <?= $filters['department_id'] === (int) $department['id'] ? 'selected' : '' ?>>
                            <?= View::e($department['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="flex gap-2">
                <button type="submit"
                        class="bg-secondary-container text-on-secondary-container font-medium text-sm px-4 py-2.5 rounded hover:opacity-90 transition-opacity">
                    Filter
                </button>
                <a href="<?= View::e(url('/payments')) ?>"
                   class="border border-outline text-on-surface font-medium text-sm px-4 py-2.5 rounded hover:bg-surface-container transition-colors">
                    Clear
                </a>
            </div>
        </form>

        <div class="bg-surface-container-lowest rounded-lg border border-outline-variant overflow-hidden">
            <div class="overflow-x-auto table-scroll">
                <table class="w-full text-sm min-w-[760px]">
                <thead class="bg-surface-container-low border-b border-outline-variant">
                    <tr>
                        <th class="text-left px-4 py-3 font-medium text-on-surface-variant">Voucher No</th>
                        <th class="text-left px-4 py-3 font-medium text-on-surface-variant">Paid At</th>
                        <th class="text-left px-4 py-3 font-medium text-on-surface-variant">Expense</th>
                        <th class="text-left px-4 py-3 font-medium text-on-surface-variant">Department</th>
                        <th class="text-right px-4 py-3 font-medium text-on-surface-variant">Amount</th>
                        <th class="text-left px-4 py-3 font-medium text-on-surface-variant">Method</th>
                        <th class="text-left px-4 py-3 font-medium text-on-surface-variant">Paid by</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-outline-variant">
                    <?php if (empty($vouchers)): ?>
                        <tr>
                            <td class="px-4 py-12 text-center text-on-surface-variant" colspan="7">
                                No payment vouchers match these filters.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($vouchers as $voucher): ?>
                            <tr class="hover:bg-surface-container-low transition-colors cursor-pointer"
                                onclick="window.location.href='<?= View::e(url('/payments/' . (int) $voucher['id'])) ?>'">
                                <td class="px-4 py-3 text-on-surface font-medium whitespace-nowrap"><?= View::e($voucher['voucher_no']) ?></td>
                                <td class="px-4 py-3 text-on-surface whitespace-nowrap"><?= View::e(date('d/m/Y H:i', strtotime($voucher['paid_at']))) ?></td>
                                <td class="px-4 py-3 text-on-surface whitespace-nowrap">
                                    <?= View::e($voucher['expense_no'] ?? '—') ?> — <?= View::e($voucher['payee']) ?>
                                </td>
                                <td class="px-4 py-3 text-on-surface whitespace-nowrap"><?= View::e($voucher['department_name'] ?? '—') ?></td>
                                <td class="px-4 py-3 text-on-surface text-right whitespace-nowrap"><?= naira($voucher['amount']) ?></td>
                                <td class="px-4 py-3 text-on-surface whitespace-nowrap"><?= View::e(PaymentMethod::tryFromString($voucher['payment_method'])?->label() ?? ucfirst($voucher['payment_method'])) ?></td>
                                <td class="px-4 py-3 text-on-surface whitespace-nowrap"><?= View::e($voucher['paid_by_name'] ?? '—') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
            </div>
        </div>
    </div>
</div>
