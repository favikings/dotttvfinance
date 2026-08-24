<?php /** @var array $expense @var string $now */ ?>
<div class="max-w-2xl mx-auto space-y-6">
    <div>
        <h1 class="text-headline-md">Create Payment Voucher</h1>
        <p class="text-body-md text-on-surface-variant mt-1">Releasing payment for this approved expense marks it paid.</p>
    </div>

    <?= flash_messages() ?>

    <div class="bg-surface-container-lowest rounded-lg border border-outline-variant p-6 shadow-[var(--shadow-ambient)]">
        <div class="mb-6 pb-6 border-b border-outline-variant grid grid-cols-2 gap-4 text-sm">
            <div>
                <p class="text-label-md text-on-surface-variant mb-1">Expense No</p>
                <p class="text-on-surface font-medium"><?= View::e($expense['expense_no'] ?? '—') ?></p>
            </div>
            <div>
                <p class="text-label-md text-on-surface-variant mb-1">Amount</p>
                <p class="text-on-surface font-medium"><?= naira($expense['amount']) ?></p>
            </div>
            <div>
                <p class="text-label-md text-on-surface-variant mb-1">Payee</p>
                <p class="text-on-surface"><?= View::e($expense['payee']) ?></p>
            </div>
            <div>
                <p class="text-label-md text-on-surface-variant mb-1">Department</p>
                <p class="text-on-surface"><?= View::e($expense['department_name'] ?? '—') ?></p>
            </div>
            <div class="col-span-2">
                <p class="text-label-md text-on-surface-variant mb-1">Description</p>
                <p class="text-on-surface"><?= View::e($expense['description']) ?></p>
            </div>
        </div>

        <form method="post" action="<?= View::e(url('/payments/create/' . (int) $expense['id'])) ?>" enctype="multipart/form-data"
              x-data="{ loading: false }" x-on:submit="loading = true">
            <?= Csrf::field() ?>

            <div class="grid grid-cols-2 gap-4 mb-4">
                <div>
                    <label class="block text-sm font-medium text-on-surface mb-1.5" for="payment-method">Payment Method</label>
                    <select id="payment-method" name="payment_method" required
                            class="w-full px-3 py-2.5 rounded border border-outline bg-surface-container-lowest text-on-surface text-sm focus:outline-none focus:ring-2 focus:ring-secondary-container focus:border-transparent">
                        <option value="" disabled selected>Select a method</option>
                        <?php foreach (PaymentMethod::cases() as $method): ?>
                            <option value="<?= View::e($method->value) ?>"><?= View::e($method->label()) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-on-surface mb-1.5" for="paid-at">Paid At</label>
                    <input type="datetime-local" id="paid-at" name="paid_at" value="<?= View::e($now) ?>" required
                           class="w-full px-3 py-2.5 rounded border border-outline bg-surface-container-lowest text-on-surface text-sm focus:outline-none focus:ring-2 focus:ring-secondary-container focus:border-transparent">
                </div>
            </div>

            <div class="grid grid-cols-2 gap-4 mb-4">
                <div>
                    <label class="block text-sm font-medium text-on-surface mb-1.5" for="bank-account">Bank Account</label>
                    <input type="text" id="bank-account" name="bank_account" placeholder="e.g. GTB - 0123456789"
                           class="w-full px-3 py-2.5 rounded border border-outline bg-surface-container-lowest text-on-surface text-sm focus:outline-none focus:ring-2 focus:ring-secondary-container focus:border-transparent">
                </div>
                <div>
                    <label class="block text-sm font-medium text-on-surface mb-1.5" for="payment-reference">Payment Reference</label>
                    <input type="text" id="payment-reference" name="payment_reference" placeholder="e.g. transaction ID"
                           class="w-full px-3 py-2.5 rounded border border-outline bg-surface-container-lowest text-on-surface text-sm focus:outline-none focus:ring-2 focus:ring-secondary-container focus:border-transparent">
                </div>
            </div>

            <div class="mb-6">
                <label class="block text-sm font-medium text-on-surface mb-1.5" for="payment-doc">Supporting Document</label>
                <input type="file" id="payment-doc" name="supporting_doc" accept=".pdf,.jpg,.jpeg,.png"
                       class="block w-full text-sm text-on-surface file:mr-3 file:rounded file:border-0 file:bg-secondary-container file:px-4 file:py-2.5 file:text-sm file:font-medium file:text-on-secondary-container hover:file:opacity-90">
                <p class="text-label-sm text-on-surface-variant mt-1.5">PDF, JPG, or PNG. Max 10MB. Optional — e.g. a bank transfer confirmation.</p>
            </div>

            <div class="flex gap-3">
                <button type="submit" :disabled="loading"
                        class="bg-secondary-container text-on-secondary-container font-medium text-sm px-4 py-2.5 rounded hover:opacity-90 transition-opacity disabled:opacity-60 disabled:cursor-not-allowed flex items-center justify-center gap-2">
                    <svg x-show="loading" x-cloak class="animate-spin w-4 h-4" viewBox="0 0 24 24" fill="none">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                    </svg>
                    <span x-text="loading ? 'Releasing...' : 'Release Payment'"></span>
                </button>
                <a href="<?= View::e(url('/payments')) ?>"
                   class="border border-outline text-on-surface font-medium text-sm px-4 py-2.5 rounded hover:bg-surface-container transition-colors">
                    Cancel
                </a>
            </div>
        </form>
    </div>
</div>
