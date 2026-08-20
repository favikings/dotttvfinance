<?php
/**
 * @var array<int, array> $topups
 * @var array<int, array{id:int, date:string, amount:string, reference:string, requested_by:string}> $pending
 * @var bool $canCreate
 * @var bool $canApprove
 */
?>
<div class="space-y-6" x-data="fundTopupQueue()">
    <div class="flex items-start justify-between gap-4">
        <div>
            <h1 class="text-headline-md">Fund Top-Ups</h1>
            <p class="text-body-md text-on-surface-variant mt-1">Requests to top up the operating float. Either GM or Chairman can clear a pending request.</p>
        </div>
        <?php if ($canCreate): ?>
            <div class="shrink-0 flex gap-3">
                <a href="<?= View::e(url('/fund-topups/create')) ?>"
                   class="bg-secondary-container text-on-secondary-container font-medium text-sm px-4 py-2.5 rounded hover:opacity-90 transition-opacity">
                    Request Top-Up
                </a>
            </div>
        <?php endif; ?>
    </div>

    <?= flash_messages() ?>

    <?php if ($canApprove): ?>
        <div>
            <h2 class="text-headline-sm mb-3">Awaiting Your Sign-Off</h2>

            <div class="bg-surface-container-lowest rounded-lg border border-outline-variant overflow-hidden" x-show="items.length > 0">
                <div class="overflow-x-auto table-scroll">
<table class="w-full text-sm min-w-[560px]">
                    <thead class="bg-surface-container-low border-b border-outline-variant">
                        <tr>
                            <th class="text-left px-4 py-3 font-medium text-on-surface-variant">Date</th>
                            <th class="text-right px-4 py-3 font-medium text-on-surface-variant">Amount</th>
                            <th class="text-left px-4 py-3 font-medium text-on-surface-variant">Reference</th>
                            <th class="text-left px-4 py-3 font-medium text-on-surface-variant">Requested by</th>
                            <th class="text-right px-4 py-3 font-medium text-on-surface-variant">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-outline-variant">
                        <template x-for="item in items" :key="item.id">
                            <tr class="hover:bg-surface-container-low transition-colors">
                                <td class="px-4 py-3 text-on-surface whitespace-nowrap" x-text="item.date"></td>
                                <td class="px-4 py-3 text-on-surface text-right whitespace-nowrap" x-text="item.amount"></td>
                                <td class="px-4 py-3 text-on-surface-variant whitespace-nowrap" x-text="item.reference"></td>
                                <td class="px-4 py-3 text-on-surface whitespace-nowrap" x-text="item.requested_by"></td>
                                <td class="px-4 py-3">
                                    <div class="flex justify-end gap-2">
                                        <button type="button"
                                                class="inline-flex items-center gap-1.5 bg-secondary-container text-on-secondary-container font-medium text-sm px-3 py-2 rounded hover:opacity-90 transition-opacity"
                                                x-on:click="approve(item)"
                                                :disabled="busy"
                                                :class="busy ? 'opacity-60 cursor-not-allowed' : ''">
                                            Approve
                                        </button>
                                        <button type="button"
                                                class="inline-flex items-center gap-1.5 bg-error text-on-error font-medium text-sm px-3 py-2 rounded hover:opacity-90 transition-opacity"
                                                x-on:click="reject(item)"
                                                :disabled="busy"
                                                :class="busy ? 'opacity-60 cursor-not-allowed' : ''">
                                            Reject
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
                </div>
            </div>

            <div class="bg-surface-container-lowest rounded-lg border border-outline-variant p-8 text-center" x-show="items.length === 0">
                <p class="text-sm font-medium text-on-surface mb-1">Nothing awaiting your sign-off</p>
                <p class="text-sm text-on-surface-variant">New top-up requests will appear here.</p>
            </div>
        </div>
    <?php endif; ?>

    <div>
        <h2 class="text-headline-sm mb-3">All Top-Ups</h2>
        <div class="bg-surface-container-lowest rounded-lg border border-outline-variant overflow-hidden">
            <div class="overflow-x-auto table-scroll">
                <table class="w-full text-sm min-w-[640px]">
                <thead class="bg-surface-container-low border-b border-outline-variant">
                    <tr>
                        <th class="text-left px-4 py-3 font-medium text-on-surface-variant">Date</th>
                        <th class="text-right px-4 py-3 font-medium text-on-surface-variant">Amount</th>
                        <th class="text-left px-4 py-3 font-medium text-on-surface-variant">Reference</th>
                        <th class="text-left px-4 py-3 font-medium text-on-surface-variant">Requested by</th>
                        <th class="text-left px-4 py-3 font-medium text-on-surface-variant">Handled by</th>
                        <th class="text-left px-4 py-3 font-medium text-on-surface-variant">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-outline-variant">
                    <?php if (empty($topups)): ?>
                        <tr>
                            <td class="px-4 py-12 text-center text-on-surface-variant" colspan="6">
                                No fund top-ups yet.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($topups as $topup): ?>
                            <tr class="hover:bg-surface-container-low transition-colors">
                                <td class="px-4 py-3 text-on-surface whitespace-nowrap"><?= View::e(date('d/m/Y', strtotime($topup['date']))) ?></td>
                                <td class="px-4 py-3 text-on-surface text-right whitespace-nowrap"><?= naira($topup['amount']) ?></td>
                                <td class="px-4 py-3 text-on-surface-variant whitespace-nowrap"><?= View::e($topup['reference'] ?? '—') ?></td>
                                <td class="px-4 py-3 text-on-surface whitespace-nowrap"><?= View::e($topup['requested_by_name'] ?? '—') ?></td>
                                <td class="px-4 py-3 text-on-surface whitespace-nowrap">
                                    <?= View::e($topup['status'] === 'approved' ? ($topup['approved_by_name'] ?? '—') : '—') ?>
                                </td>
                                <td class="px-4 py-3">
                                    <div class="flex flex-wrap items-center gap-1.5">
                                        <?= status_badge($topup['status']) ?>
                                        <?= backfilled_badge($topup['is_historical']) ?>
                                        <?php if ($topup['status'] === 'rejected' && !empty($topup['rejected_reason'])): ?>
                                            <span class="text-label-sm text-on-surface-variant" title="<?= View::e($topup['rejected_reason']) ?>">ⓘ</span>
                                        <?php endif; ?>
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
</div>

<script>
    window.TOPUP_QUEUE_ITEMS = <?= json_encode($pending) ?>;
    window.TOPUP_CSRF_TOKEN = <?= json_encode(Csrf::token()) ?>;

    function fundTopupQueue() {
        return {
            items: window.TOPUP_QUEUE_ITEMS || [],
            busy: false,

            approve(item) {
                const self = this;
                dottConfirm(
                    'Approve this top-up?',
                    item.date + ' — ' + item.amount + ' · ' + item.requested_by,
                    'Approve'
                ).then((result) => {
                    if (result.isConfirmed) {
                        self.postAction('/fund-topups/approve', { topup_id: item.id }, (json) => {
                            self.removeItem(item.id);
                            dottToast.fire({ icon: 'success', title: json.message });
                        });
                    }
                });
            },

            reject(item) {
                const self = this;
                dottAlert.fire({
                    title: 'Reject this top-up?',
                    text: item.date + ' — ' + item.amount + ' · ' + item.requested_by,
                    input: 'textarea',
                    inputPlaceholder: 'Reason for rejection (required)',
                    inputValidator: (value) => (!value || !value.trim()) && 'A reason is required',
                    showCancelButton: true,
                    confirmButtonText: 'Reject',
                    cancelButtonText: 'Cancel',
                    background: 'var(--color-surface-container-lowest)',
                    color: 'var(--color-on-surface)',
                    confirmButtonColor: 'var(--color-error)',
                    customClass: {
                        popup: 'rounded-xl border border-outline-variant',
                        confirmButton: 'bg-error text-on-error text-sm font-semibold px-4 py-2.5 rounded mx-1',
                        cancelButton: 'border border-outline text-on-surface text-sm font-medium px-4 py-2.5 rounded mx-1',
                    },
                }).then((result) => {
                    if (result.isConfirmed) {
                        self.postAction('/fund-topups/reject', { topup_id: item.id, comment: result.value.trim() }, (json) => {
                            self.removeItem(item.id);
                            dottToast.fire({ icon: 'success', title: json.message });
                        });
                    }
                });
            },

            removeItem(id) {
                this.items = this.items.filter((row) => row.id !== id);
            },

            async postAction(path, payload, onSuccess) {
                if (this.busy) {
                    return;
                }
                this.busy = true;
                try {
                    const res = await fetch(window.APP_BASE_PATH + path, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                        },
                        body: JSON.stringify(Object.assign({ _csrf: window.TOPUP_CSRF_TOKEN }, payload)),
                    });
                    const json = await res.json().catch(() => null);
                    if (!json || !json.ok) {
                        dottToast.fire({ icon: 'error', title: (json && json.message) || 'Something went wrong. Please try again.' });
                        return;
                    }
                    onSuccess(json);
                } catch (err) {
                    dottToast.fire({ icon: 'error', title: 'Could not reach the server. Check your connection and try again.' });
                } finally {
                    this.busy = false;
                }
            },
        };
    }
</script>
