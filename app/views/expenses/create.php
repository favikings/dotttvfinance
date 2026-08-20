<?php /** @var array<int, array> $departments @var array<int, array> $categories @var array<int, array> $fundAccounts @var string $today */ ?>
<div class="max-w-2xl mx-auto space-y-6">
    <div>
        <h1 class="text-headline-md">Record Expense</h1>
        <p class="text-body-md text-on-surface-variant mt-1">Saves the expense and submits it for approval based on its amount.</p>
    </div>

    <?= flash_messages() ?>

    <div class="bg-surface-container-lowest rounded-lg border border-outline-variant p-6 shadow-[var(--shadow-ambient)]">
        <form method="post" action="<?= View::e(url('/expenses/create')) ?>" enctype="multipart/form-data">
            <?= Csrf::field() ?>

            <div class="grid grid-cols-2 gap-4 mb-4">
                <div>
                    <label class="block text-sm font-medium text-on-surface mb-1.5" for="expense-date">Date</label>
                    <input type="date" id="expense-date" name="date" value="<?= View::e($today) ?>" required
                           class="w-full px-3 py-2.5 rounded border border-outline bg-surface-container-lowest text-on-surface text-sm focus:outline-none focus:ring-2 focus:ring-secondary-container focus:border-transparent">
                </div>
                <div>
                    <label class="block text-sm font-medium text-on-surface mb-1.5" for="expense-document-no">Document No</label>
                    <input type="text" id="expense-document-no" name="document_no" placeholder="e.g. INV-1042"
                           required class="w-full px-3 py-2.5 rounded border border-outline bg-surface-container-lowest text-on-surface text-sm focus:outline-none focus:ring-2 focus:ring-secondary-container focus:border-transparent">
                </div>
            </div>

            <div class="mb-4">
                <label class="block text-sm font-medium text-on-surface mb-1.5" for="expense-payee">Payee</label>
                <input type="text" id="expense-payee" name="payee" placeholder="e.g. Ifeoma Okafor"
                       required class="w-full px-3 py-2.5 rounded border border-outline bg-surface-container-lowest text-on-surface text-sm focus:outline-none focus:ring-2 focus:ring-secondary-container focus:border-transparent">
            </div>

            <div class="mb-4">
                <label class="block text-sm font-medium text-on-surface mb-1.5" for="expense-description">Description</label>
                <textarea id="expense-description" name="description" rows="3" required
                          class="w-full px-3 py-2.5 rounded border border-outline bg-surface-container-lowest text-on-surface text-sm focus:outline-none focus:ring-2 focus:ring-secondary-container focus:border-transparent"></textarea>
            </div>

            <div class="grid grid-cols-2 gap-4 mb-4">
                <div>
                    <label class="block text-sm font-medium text-on-surface mb-1.5" for="expense-department">Department</label>
                    <select id="expense-department" name="department_id" required
                            class="w-full px-3 py-2.5 rounded border border-outline bg-surface-container-lowest text-on-surface text-sm focus:outline-none focus:ring-2 focus:ring-secondary-container focus:border-transparent">
                        <option value="" disabled selected>Select a department</option>
                        <?php foreach ($departments as $department): ?>
                            <option value="<?= (int) $department['id'] ?>"><?= View::e($department['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-on-surface mb-1.5" for="expense-category">Category</label>
                    <select id="expense-category" name="category_id"
                            class="w-full px-3 py-2.5 rounded border border-outline bg-surface-container-lowest text-on-surface text-sm focus:outline-none focus:ring-2 focus:ring-secondary-container focus:border-transparent">
                        <option value="" selected>No category</option>
                        <?php foreach ($categories as $category): ?>
                            <option value="<?= (int) $category['id'] ?>"><?= View::e($category['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="grid grid-cols-2 gap-4 mb-4">
                <div>
                    <label class="block text-sm font-medium text-on-surface mb-1.5" for="expense-amount">Amount (₦)</label>
                    <input type="number" id="expense-amount" name="amount" min="0.01" step="0.01" inputmode="decimal" required
                           class="w-full px-3 py-2.5 rounded border border-outline bg-surface-container-lowest text-on-surface text-sm focus:outline-none focus:ring-2 focus:ring-secondary-container focus:border-transparent">
                </div>
                <div>
                    <label class="block text-sm font-medium text-on-surface mb-1.5" for="expense-fund">Fund Account</label>
                    <select id="expense-fund" name="fund_account_id" required
                            class="w-full px-3 py-2.5 rounded border border-outline bg-surface-container-lowest text-on-surface text-sm focus:outline-none focus:ring-2 focus:ring-secondary-container focus:border-transparent">
                        <?php foreach ($fundAccounts as $account): ?>
                            <option value="<?= (int) $account['id'] ?>"><?= View::e($account['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="mb-6">
                <label class="block text-sm font-medium text-on-surface mb-1.5" for="expense-doc">Supporting Document</label>
                <input type="file" id="expense-doc" name="supporting_doc" accept=".pdf,.jpg,.jpeg,.png"
                       class="block w-full text-sm text-on-surface file:mr-3 file:rounded file:border-0 file:bg-secondary-container file:px-4 file:py-2.5 file:text-sm file:font-medium file:text-on-secondary-container hover:file:opacity-90">
                <p class="text-label-sm text-on-surface-variant mt-1.5">PDF, JPG, or PNG. Max 10MB. Optional.</p>
            </div>

            <div class="flex gap-3">
                <button type="submit"
                        class="bg-secondary-container text-on-secondary-container font-medium text-sm px-4 py-2.5 rounded hover:opacity-90 transition-opacity">
                    Save Expense
                </button>
                <a href="<?= View::e(url('/expenses')) ?>"
                   class="border border-outline text-on-surface font-medium text-sm px-4 py-2.5 rounded hover:bg-surface-container transition-colors">
                    Cancel
                </a>
            </div>
        </form>
    </div>
</div>