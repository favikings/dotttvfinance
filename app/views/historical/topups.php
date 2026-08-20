<?php /** @var string $today */ ?>
<div class="space-y-6">
    <div>
        <h1 class="text-headline-md">Historical Entry</h1>
        <p class="text-body-md text-on-surface-variant mt-1">Backfill a paper-book fund top-up. Saved records are marked <span class="font-medium text-on-surface">Backfilled</span> and count toward the balance immediately — no approval needed.</p>
    </div>

    <?= historical_tabs('topups') ?>
    <?= flash_messages() ?>

    <div class="max-w-2xl mx-auto">
        <div class="bg-surface-container-lowest rounded-lg border border-outline-variant p-6 shadow-[var(--shadow-ambient)]">
            <form method="post" action="<?= View::e(url('/historical-entry/topups')) ?>">
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
                    <button type="submit"
                            class="bg-secondary-container text-on-secondary-container font-medium text-sm px-4 py-2.5 rounded hover:opacity-90 transition-opacity">
                        Save Top-Up
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
