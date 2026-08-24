<?php
/** @var string $tierLabel @var array<int, array{id:int, expense_no:string, date:string, payee:string, description:string, department:string, amount:string, submitted_by:string}> $items */
?>
<div class="space-y-6" x-data="approvalQueue()">
    <div class="flex items-start justify-between gap-4">
        <div>
            <h1 class="text-headline-md">Approval Queue</h1>
            <p class="text-body-md text-on-surface-variant mt-1">
                Expenses awaiting your sign-off
                (<span class="font-medium text-on-surface"><?= View::e($tierLabel) ?></span>).
                Approve or reject — no page reload needed.
            </p>
        </div>
        <div class="shrink-0 flex gap-3">
            <a href="<?= View::e(url('/expenses')) ?>"
               class="border border-outline text-on-surface font-medium text-sm px-4 py-2.5 rounded hover:bg-surface-container transition-colors">
                All Expenses
            </a>
        </div>
    </div>

    <?= flash_messages() ?>

    <!-- UI Component Guide §8 table — x-show so the empty state below takes over cleanly. -->
    <div class="bg-surface-container-lowest rounded-lg border border-outline-variant overflow-hidden" x-show="items.length > 0">
        <div class="overflow-x-auto table-scroll">
            <table class="w-full text-sm min-w-[820px]">
            <thead class="bg-surface-container-low border-b border-outline-variant">
                <tr>
                    <th class="text-left px-4 py-3 font-medium text-on-surface-variant">Expense No</th>
                    <th class="text-left px-4 py-3 font-medium text-on-surface-variant">Date</th>
                    <th class="text-left px-4 py-3 font-medium text-on-surface-variant">Payee</th>
                    <th class="text-left px-4 py-3 font-medium text-on-surface-variant">Description</th>
                    <th class="text-left px-4 py-3 font-medium text-on-surface-variant">Department</th>
                    <th class="text-right px-4 py-3 font-medium text-on-surface-variant">Amount</th>
                    <th class="text-left px-4 py-3 font-medium text-on-surface-variant">Submitted by</th>
                    <th class="text-right px-4 py-3 font-medium text-on-surface-variant">Action</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-outline-variant">
                <template x-for="item in items" :key="item.id">
                    <tr class="hover:bg-surface-container-low transition-colors">
                        <td class="px-4 py-3 text-on-surface font-medium whitespace-nowrap" x-text="item.expense_no"></td>
                        <td class="px-4 py-3 text-on-surface whitespace-nowrap" x-text="item.date"></td>
                        <td class="px-4 py-3 text-on-surface whitespace-nowrap" x-text="item.payee"></td>
                        <td class="px-4 py-3 text-on-surface-variant max-w-[240px] truncate" x-text="item.description"></td>
                        <td class="px-4 py-3 text-on-surface whitespace-nowrap" x-text="item.department"></td>
                        <td class="px-4 py-3 text-on-surface text-right whitespace-nowrap" x-text="item.amount"></td>
                        <td class="px-4 py-3 text-on-surface whitespace-nowrap" x-text="item.submitted_by"></td>
                        <td class="px-4 py-3">
                            <div class="flex justify-end gap-2">
                                <button type="button"
                                        class="inline-flex items-center gap-1.5 bg-secondary-container text-on-secondary-container font-medium text-sm px-3 py-2 rounded hover:opacity-90 transition-opacity"
                                        x-on:click="approve(item)"
                                        :disabled="busy"
                                        :class="busy ? 'opacity-60 cursor-not-allowed' : ''">
                                    <svg x-show="busy && actingId === item.id" x-cloak class="animate-spin w-4 h-4" viewBox="0 0 24 24" fill="none">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                                    </svg>
                                    <svg x-show="!(busy && actingId === item.id)" class="w-4 h-4" stroke="currentColor" fill="none">
                                        <use href="<?= View::e(url('/assets/icons/sprite.svg')) ?>#check"></use>
                                    </svg>
                                    <span x-text="busy && actingId === item.id ? 'Approving...' : 'Approve'"></span>
                                </button>
                                <button type="button"
                                        class="inline-flex items-center gap-1.5 bg-error text-on-error font-medium text-sm px-3 py-2 rounded hover:opacity-90 transition-opacity"
                                        x-on:click="reject(item)"
                                        :disabled="busy"
                                        :class="busy ? 'opacity-60 cursor-not-allowed' : ''">
                                    <svg class="w-4 h-4" stroke="currentColor" fill="none">
                                        <use href="<?= View::e(url('/assets/icons/sprite.svg')) ?>#x"></use>
                                    </svg>
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

    <!-- UI Component Guide §9 empty state -->
    <div class="bg-surface-container-lowest rounded-lg border border-outline-variant p-12 text-center" x-show="items.length === 0">
        <p class="text-sm font-medium text-on-surface mb-1">Nothing awaiting your approval</p>
        <p class="text-sm text-on-surface-variant">You're all caught up. New expenses that need your sign-off will appear here.</p>
    </div>
</div>

<script>
    window.APPROVAL_QUEUE_ITEMS = <?= json_encode($items) ?>;
    window.APPROVAL_CSRF_TOKEN = <?= json_encode(Csrf::token()) ?>;

    function approvalQueue() {
        return {
            items: window.APPROVAL_QUEUE_ITEMS || [],
            busy: false,
            actingId: null,

            approve(item) {
                const self = this;
                dottConfirm(
                    'Approve this expense?',
                    item.expense_no + ' — ' + item.payee + ' · ' + item.amount,
                    'Approve'
                ).then((result) => {
                    if (result.isConfirmed) {
                        self.actingId = item.id;
                        self.postAction('/approvals/approve', { expense_id: item.id }, (json) => {
                            self.removeItem(item.id);
                            dottToast.fire({ icon: 'success', title: json.message });
                        }).finally(() => { self.actingId = null; });
                    }
                });
            },

            // UI Component Guide §4a Pattern C — the async work (the fetch)
            // runs inside preConfirm itself, so SweetAlert2's built-in
            // showLoaderOnConfirm handles the loading state on the dialog's
            // confirm button; no separate loading flag needed here.
            reject(item) {
                const self = this;
                dottAlert.fire({
                    title: 'Reject ' + item.expense_no + '?',
                    text: item.payee + ' · ' + item.amount,
                    input: 'textarea',
                    inputPlaceholder: 'Reason for rejection (required)',
                    inputValidator: (value) => (!value || !value.trim()) && 'A reason is required',
                    showCancelButton: true,
                    confirmButtonText: 'Reject',
                    cancelButtonText: 'Cancel',
                    showLoaderOnConfirm: true,
                    preConfirm: async (comment) => {
                        try {
                            const res = await fetch(window.APP_BASE_PATH + '/approvals/reject', {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/json',
                                    'Accept': 'application/json',
                                },
                                body: JSON.stringify({ _csrf: window.APPROVAL_CSRF_TOKEN, expense_id: item.id, comment: comment.trim() }),
                            });
                            const json = await res.json().catch(() => null);
                            if (!json || !json.ok) {
                                Swal.showValidationMessage((json && json.message) || 'Something went wrong. Please try again.');
                                return false;
                            }
                            return json;
                        } catch (err) {
                            Swal.showValidationMessage(
                                navigator.onLine
                                    ? 'Could not reach the server. Check your connection and try again.'
                                    : "You're offline — reconnect to reject expenses."
                            );
                            return false;
                        }
                    },
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
                        self.removeItem(item.id);
                        dottToast.fire({ icon: 'success', title: result.value.message });
                    }
                });
            },

            removeItem(id) {
                this.items = this.items.filter((row) => row.id !== id);
            },

            // Tech Spec §16: JSON round-trip to the controller endpoint, DOM
            // updated from the response — no page reload for a queue decision.
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
                        body: JSON.stringify(Object.assign({ _csrf: window.APPROVAL_CSRF_TOKEN }, payload)),
                    });
                    const json = await res.json().catch(() => null);
                    if (!json || !json.ok) {
                        dottToast.fire({ icon: 'error', title: (json && json.message) || 'Something went wrong. Please try again.' });
                        return;
                    }
                    onSuccess(json);
                } catch (err) {
                    // Failed loudly on purpose — PWA spec: financial writes
                    // never queue offline, the user sees the failure (Tech Spec §17).
                    dottToast.fire({
                        icon: 'error',
                        title: navigator.onLine
                            ? 'Could not reach the server. Check your connection and try again.'
                            : "You're offline — reconnect to approve or reject expenses.",
                    });
                } finally {
                    this.busy = false;
                }
            },
        };
    }
</script>
