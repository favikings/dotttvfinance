<?php /** @var string $today */ ?>
<div class="max-w-2xl mx-auto space-y-6">
    <div>
        <h1 class="text-headline-md">Request Fund Account Top-Up</h1>
        <p class="text-body-md text-on-surface-variant mt-1">Submits for a single GM or Chairman sign-off — whoever actions it first.</p>
    </div>

    <?= flash_messages() ?>

    <div class="bg-surface-container-lowest rounded-lg border border-outline-variant p-6 shadow-[var(--shadow-ambient)]">
        <form method="post" action="<?= View::e(url('/fund-topups/create')) ?>"
              x-data="{ loading: false }" x-on:submit="loading = true">
            <?= Csrf::field() ?>

            <div class="grid grid-cols-2 gap-4 mb-4">
                <div>
                    <label class="block text-sm font-medium text-on-surface mb-1.5" for="topup-date">Date</label>
                    <input type="date" id="topup-date" name="date" value="<?= View::e($today) ?>" required
                           class="w-full px-3 py-2.5 rounded border border-outline bg-surface-container-lowest text-on-surface text-sm focus:outline-none focus:ring-2 focus:ring-secondary-container focus:border-transparent">
                </div>
                <div>
                    <label class="block text-sm font-medium text-on-surface mb-1.5" for="topup-amount">Amount (₦)</label>
                    <input type="number" id="topup-amount" name="amount" min="0.01" step="0.01" inputmode="decimal" required
                           class="w-full px-3 py-2.5 rounded border border-outline bg-surface-container-lowest text-on-surface text-sm focus:outline-none focus:ring-2 focus:ring-secondary-container focus:border-transparent">
                </div>
            </div>

            <div class="mb-4">
                <label class="block text-sm font-medium text-on-surface mb-1.5" for="topup-reference">Reference</label>
                <input type="text" id="topup-reference" name="reference" placeholder="e.g. bank teller number"
                       class="w-full px-3 py-2.5 rounded border border-outline bg-surface-container-lowest text-on-surface text-sm focus:outline-none focus:ring-2 focus:ring-secondary-container focus:border-transparent">
            </div>

            <div class="mb-6">
                <label class="block text-sm font-medium text-on-surface mb-1.5" for="topup-note">Note</label>
                <textarea id="topup-note" name="note" rows="3"
                          class="w-full px-3 py-2.5 rounded border border-outline bg-surface-container-lowest text-on-surface text-sm focus:outline-none focus:ring-2 focus:ring-secondary-container focus:border-transparent"></textarea>
            </div>

            <div class="flex gap-3">
                <button type="submit" :disabled="loading"
                        class="bg-secondary-container text-on-secondary-container font-medium text-sm px-4 py-2.5 rounded hover:opacity-90 transition-opacity disabled:opacity-60 disabled:cursor-not-allowed flex items-center justify-center gap-2">
                    <svg x-show="loading" x-cloak class="animate-spin w-4 h-4" viewBox="0 0 24 24" fill="none">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                    </svg>
                    <span x-text="loading ? 'Submitting...' : 'Submit Request'"></span>
                </button>
                <a href="<?= View::e(url('/fund-topups')) ?>"
                   class="border border-outline text-on-surface font-medium text-sm px-4 py-2.5 rounded hover:bg-surface-container transition-colors">
                    Cancel
                </a>
            </div>
        </form>
    </div>
</div>
