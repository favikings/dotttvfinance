<?php
/** @var array<string, mixed> $invoice @var array<int, array> $departments @var string $today */
?>
<div class="max-w-2xl mx-auto space-y-6">
    <div>
        <h1 class="text-headline-md">Edit Invoice</h1>
        <p class="text-body-md text-on-surface-variant mt-1">Update the invoice details and its payment tracking.</p>
    </div>

    <?= flash_messages() ?>

    <div class="bg-surface-container-lowest rounded-lg border border-outline-variant p-6 shadow-[var(--shadow-ambient)]">
        <form method="post" action="<?= View::e(url('/invoices/edit/' . (int) $invoice['id'])) ?>">
            <?= Csrf::field() ?>

            <div class="grid grid-cols-2 gap-4 mb-4">
                <div>
                    <label class="block text-sm font-medium text-on-surface mb-1.5" for="invoice-no">Invoice No</label>
                    <input type="text" id="invoice-no" name="invoice_no" value="<?= View::e($invoice['invoice_no']) ?>" required maxlength="30"
                           class="w-full px-3 py-2.5 rounded border border-outline bg-surface-container-lowest text-on-surface text-sm focus:outline-none focus:ring-2 focus:ring-secondary-container focus:border-transparent">
                </div>
                <div>
                    <label class="block text-sm font-medium text-on-surface mb-1.5" for="invoice-date">Date</label>
                    <input type="date" id="invoice-date" name="date" value="<?= View::e($invoice['date']) ?>" required
                           class="w-full px-3 py-2.5 rounded border border-outline bg-surface-container-lowest text-on-surface text-sm focus:outline-none focus:ring-2 focus:ring-secondary-container focus:border-transparent">
                </div>
            </div>

            <div class="mb-4">
                <label class="block text-sm font-medium text-on-surface mb-1.5" for="invoice-client">Client</label>
                <input type="text" id="invoice-client" name="client" value="<?= View::e($invoice['client']) ?>" required maxlength="150"
                       class="w-full px-3 py-2.5 rounded border border-outline bg-surface-container-lowest text-on-surface text-sm focus:outline-none focus:ring-2 focus:ring-secondary-container focus:border-transparent">
            </div>

            <div class="mb-4">
                <label class="block text-sm font-medium text-on-surface mb-1.5" for="invoice-description">Description</label>
                <textarea id="invoice-description" name="description" rows="3"
                          class="w-full px-3 py-2.5 rounded border border-outline bg-surface-container-lowest text-on-surface text-sm focus:outline-none focus:ring-2 focus:ring-secondary-container focus:border-transparent"><?= View::e($invoice['description'] ?? '') ?></textarea>
            </div>

            <div class="grid grid-cols-2 gap-4 mb-4">
                <div>
                    <label class="block text-sm font-medium text-on-surface mb-1.5" for="invoice-department">Department</label>
                    <select id="invoice-department" name="department_id"
                            class="w-full px-3 py-2.5 rounded border border-outline bg-surface-container-lowest text-on-surface text-sm focus:outline-none focus:ring-2 focus:ring-secondary-container focus:border-transparent">
                        <option value="">No department</option>
                        <?php foreach ($departments as $department): ?>
                            <option value="<?= (int) $department['id'] ?>" <?= (int) $department['id'] === (int) ($invoice['department_id'] ?? 0) ? 'selected' : '' ?>>
                                <?= View::e($department['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-on-surface mb-1.5" for="invoice-due-date">Due Date</label>
                    <input type="date" id="invoice-due-date" name="due_date" value="<?= View::e($invoice['due_date'] ?? '') ?>"
                           class="w-full px-3 py-2.5 rounded border border-outline bg-surface-container-lowest text-on-surface text-sm focus:outline-none focus:ring-2 focus:ring-secondary-container focus:border-transparent">
                </div>
            </div>

            <div class="grid grid-cols-3 gap-4 mb-4">
                <div>
                    <label class="block text-sm font-medium text-on-surface mb-1.5" for="invoice-amount">Amount (₦)</label>
                    <input type="number" id="invoice-amount" name="amount" min="0.01" step="0.01" inputmode="decimal" required
                           value="<?= View::e($invoice['amount']) ?>"
                           class="w-full px-3 py-2.5 rounded border border-outline bg-surface-container-lowest text-on-surface text-sm focus:outline-none focus:ring-2 focus:ring-secondary-container focus:border-transparent">
                </div>
                <div>
                    <label class="block text-sm font-medium text-on-surface mb-1.5" for="invoice-vat">VAT (₦)</label>
                    <input type="number" id="invoice-vat" name="vat_amount" min="0" step="0.01" inputmode="decimal"
                           value="<?= View::e($invoice['vat_amount']) ?>"
                           class="w-full px-3 py-2.5 rounded border border-outline bg-surface-container-lowest text-on-surface text-sm focus:outline-none focus:ring-2 focus:ring-secondary-container focus:border-transparent">
                </div>
                <div>
                    <label class="block text-sm font-medium text-on-surface mb-1.5" for="invoice-wht">WHT (₦)</label>
                    <input type="number" id="invoice-wht" name="wht_amount" min="0" step="0.01" inputmode="decimal"
                           value="<?= View::e($invoice['wht_amount']) ?>"
                           class="w-full px-3 py-2.5 rounded border border-outline bg-surface-container-lowest text-on-surface text-sm focus:outline-none focus:ring-2 focus:ring-secondary-container focus:border-transparent">
                </div>
            </div>

            <div class="grid grid-cols-2 gap-4 mb-6">
                <div>
                    <label class="block text-sm font-medium text-on-surface mb-1.5" for="invoice-payment-status">Payment Status</label>
                    <select id="invoice-payment-status" name="payment_status"
                            class="w-full px-3 py-2.5 rounded border border-outline bg-surface-container-lowest text-on-surface text-sm focus:outline-none focus:ring-2 focus:ring-secondary-container focus:border-transparent">
                        <option value="unpaid" <?= $invoice['payment_status'] === 'unpaid' ? 'selected' : '' ?>>Unpaid</option>
                        <option value="partial" <?= $invoice['payment_status'] === 'partial' ? 'selected' : '' ?>>Partial</option>
                        <option value="paid" <?= $invoice['payment_status'] === 'paid' ? 'selected' : '' ?>>Paid</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-on-surface mb-1.5" for="invoice-payment-date">Payment Date</label>
                    <input type="date" id="invoice-payment-date" name="payment_date" value="<?= View::e($invoice['payment_date'] ?? '') ?>"
                           class="w-full px-3 py-2.5 rounded border border-outline bg-surface-container-lowest text-on-surface text-sm focus:outline-none focus:ring-2 focus:ring-secondary-container focus:border-transparent">
                </div>
            </div>

            <div class="flex gap-3">
                <button type="submit"
                        class="bg-secondary-container text-on-secondary-container font-medium text-sm px-4 py-2.5 rounded hover:opacity-90 transition-opacity">
                    Save Changes
                </button>
                <a href="<?= View::e(url('/invoices')) ?>"
                   class="border border-outline text-on-surface font-medium text-sm px-4 py-2.5 rounded hover:bg-surface-container transition-colors">
                    Cancel
                </a>
            </div>
        </form>
    </div>
</div>