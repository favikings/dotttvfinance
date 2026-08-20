<?php
/**
 * @var int $currentYear
 * @var int $currentMonth
 */
?>
<div class="max-w-2xl mx-auto space-y-6">
    <div>
        <h1 class="text-headline-md">New Payroll Run</h1>
        <p class="text-body-md text-on-surface-variant mt-1">One run per period — you'll add employees on the next screen.</p>
    </div>

    <?= flash_messages() ?>

    <div class="bg-surface-container-lowest rounded-lg border border-outline-variant p-6 shadow-[var(--shadow-ambient)]">
        <form method="post" action="<?= View::e(url('/payroll/create')) ?>">
            <?= Csrf::field() ?>

            <div class="grid grid-cols-2 gap-4 mb-6">
                <div>
                    <label class="block text-sm font-medium text-on-surface mb-1.5" for="payroll-month">Month</label>
                    <select id="payroll-month" name="period_month" required
                            class="w-full px-3 py-2.5 rounded border border-outline bg-surface-container-lowest text-on-surface text-sm focus:outline-none focus:ring-2 focus:ring-secondary-container focus:border-transparent">
                        <?php for ($m = 1; $m <= 12; $m++): ?>
                            <option value="<?= $m ?>" <?= $m === $currentMonth ? 'selected' : '' ?>><?= View::e(PayrollRun::monthName($m)) ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-on-surface mb-1.5" for="payroll-year">Year</label>
                    <input type="number" id="payroll-year" name="period_year" value="<?= $currentYear ?>" min="2000" max="2100" required
                           class="w-full px-3 py-2.5 rounded border border-outline bg-surface-container-lowest text-on-surface text-sm focus:outline-none focus:ring-2 focus:ring-secondary-container focus:border-transparent">
                </div>
            </div>

            <div class="flex gap-3">
                <button type="submit"
                        class="bg-secondary-container text-on-secondary-container font-medium text-sm px-4 py-2.5 rounded hover:opacity-90 transition-opacity">
                    Create Run
                </button>
                <a href="<?= View::e(url('/payroll')) ?>"
                   class="border border-outline text-on-surface font-medium text-sm px-4 py-2.5 rounded hover:bg-surface-container transition-colors">
                    Cancel
                </a>
            </div>
        </form>
    </div>
</div>
