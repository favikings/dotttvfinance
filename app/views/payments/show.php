<?php /** @var array $voucher */ ?>
<div class="max-w-2xl mx-auto space-y-6">
    <div class="flex items-start justify-between gap-4">
        <div>
            <h1 class="text-headline-md">Voucher <?= View::e($voucher['voucher_no']) ?></h1>
            <p class="text-body-md text-on-surface-variant mt-1">Payment released against expense <?= View::e($voucher['expense_no'] ?? '—') ?>.</p>
        </div>
        <a href="<?= View::e(url('/payments')) ?>"
           class="shrink-0 border border-outline text-on-surface font-medium text-sm px-4 py-2.5 rounded hover:bg-surface-container transition-colors">
            Back to Payments
        </a>
    </div>

    <?= flash_messages() ?>

    <div class="bg-surface-container-lowest rounded-lg border border-outline-variant p-6 shadow-[var(--shadow-ambient)] space-y-6">
        <div>
            <h2 class="text-headline-sm mb-3">Expense</h2>
            <div class="grid grid-cols-2 gap-4 text-sm">
                <div>
                    <p class="text-label-md text-on-surface-variant mb-1">Expense No</p>
                    <p class="text-on-surface font-medium"><?= View::e($voucher['expense_no'] ?? '—') ?></p>
                </div>
                <div>
                    <p class="text-label-md text-on-surface-variant mb-1">Amount</p>
                    <p class="text-on-surface font-medium"><?= naira($voucher['amount']) ?></p>
                </div>
                <div>
                    <p class="text-label-md text-on-surface-variant mb-1">Payee</p>
                    <p class="text-on-surface"><?= View::e($voucher['payee']) ?></p>
                </div>
                <div>
                    <p class="text-label-md text-on-surface-variant mb-1">Department</p>
                    <p class="text-on-surface"><?= View::e($voucher['department_name'] ?? '—') ?></p>
                </div>
                <div class="col-span-2">
                    <p class="text-label-md text-on-surface-variant mb-1">Description</p>
                    <p class="text-on-surface"><?= View::e($voucher['description']) ?></p>
                </div>
            </div>
        </div>

        <div class="pt-6 border-t border-outline-variant">
            <h2 class="text-headline-sm mb-3">Payment Details</h2>
            <div class="grid grid-cols-2 gap-4 text-sm">
                <div>
                    <p class="text-label-md text-on-surface-variant mb-1">Payment Method</p>
                    <p class="text-on-surface"><?= View::e(PaymentMethod::tryFromString($voucher['payment_method'])?->label() ?? ucfirst($voucher['payment_method'])) ?></p>
                </div>
                <div>
                    <p class="text-label-md text-on-surface-variant mb-1">Paid At</p>
                    <p class="text-on-surface"><?= View::e(date('d/m/Y H:i', strtotime($voucher['paid_at']))) ?></p>
                </div>
                <div>
                    <p class="text-label-md text-on-surface-variant mb-1">Bank Account</p>
                    <p class="text-on-surface"><?= View::e($voucher['bank_account'] ?? '—') ?></p>
                </div>
                <div>
                    <p class="text-label-md text-on-surface-variant mb-1">Payment Reference</p>
                    <p class="text-on-surface"><?= View::e($voucher['payment_reference'] ?? '—') ?></p>
                </div>
                <div>
                    <p class="text-label-md text-on-surface-variant mb-1">Paid By</p>
                    <p class="text-on-surface"><?= View::e($voucher['paid_by_name'] ?? '—') ?></p>
                </div>
                <div>
                    <p class="text-label-md text-on-surface-variant mb-1">Supporting Document</p>
                    <p class="text-on-surface"><?= $voucher['supporting_doc_path'] !== null ? 'Attached' : '—' ?></p>
                </div>
            </div>
        </div>
    </div>
</div>
