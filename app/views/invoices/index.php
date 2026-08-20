<?php
/**
 * @var array<int, array> $invoices
 * @var array<int, array{id:int, invoice_no:string, date:string, client:string, department:string, amount:string, created_by:string}> $pending
 * @var string $filter
 * @var bool $canCreate
 * @var bool $canEdit
 * @var bool $canDelete
 * @var bool $canApprove
 * @var string $today
 */
$statusFilterOptions = [
    '' => 'All',
    'unpaid' => 'Unpaid',
    'partial' => 'Partial',
    'paid' => 'Paid',
];
$todayTs = strtotime($today);
?>
<div class="space-y-6" x-data="invoiceQueue()">
    <div class="flex items-start justify-between gap-4">
        <div>
            <h1 class="text-headline-md">Invoices</h1>
            <p class="text-body-md text-on-surface-variant mt-1">Revenue owed to DOTT TV. GM sign-off then payment tracking per invoice.</p>
        </div>
        <?php if ($canCreate): ?>
            <div class="shrink-0 flex gap-3">
                <a href="<?= View::e(url('/invoices/create')) ?>"
                   class="bg-secondary-container text-on-secondary-container font-medium text-sm px-4 py-2.5 rounded hover:opacity-90 transition-opacity">
                    New Invoice
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
<table class="w-full text-sm min-w-[640px]">
                    <thead class="bg-surface-container-low border-b border-outline-variant">
                        <tr>
                            <th class="text-left px-4 py-3 font-medium text-on-surface-variant">Invoice No</th>
                            <th class="text-left px-4 py-3 font-medium text-on-surface-variant">Date</th>
                            <th class="text-left px-4 py-3 font-medium text-on-surface-variant">Client</th>
                            <th class="text-left px-4 py-3 font-medium text-on-surface-variant">Department</th>
                            <th class="text-right px-4 py-3 font-medium text-on-surface-variant">Amount</th>
                            <th class="text-right px-4 py-3 font-medium text-on-surface-variant">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-outline-variant">
                        <template x-for="item in items" :key="item.id">
                            <tr class="hover:bg-surface-container-low transition-colors">
                                <td class="px-4 py-3 text-on-surface font-medium whitespace-nowrap" x-text="item.invoice_no"></td>
                                <td class="px-4 py-3 text-on-surface whitespace-nowrap" x-text="item.date"></td>
                                <td class="px-4 py-3 text-on-surface whitespace-nowrap" x-text="item.client"></td>
                                <td class="px-4 py-3 text-on-surface-variant whitespace-nowrap" x-text="item.department"></td>
                                <td class="px-4 py-3 text-on-surface text-right whitespace-nowrap" x-text="item.amount"></td>
                                <td class="px-4 py-3">
                                    <div class="flex justify-end gap-2">
                                        <button type="button"
                                                class="inline-flex items-center gap-1.5 bg-secondary-container text-on-secondary-container font-medium text-sm px-3 py-2 rounded hover:opacity-90 transition-opacity"
                                                x-on:click="approve(item)"
                                                :disabled="busy"
                                                :class="busy ? 'opacity-60 cursor-not-allowed' : ''">
                                            <svg class="w-4 h-4" stroke="currentColor" fill="none">
                                                <use href="<?= View::e(url('/assets/icons/sprite.svg')) ?>#check"></use>
                                            </svg>
                                            Approve
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

            <div class="bg-surface-container-lowest rounded-lg border border-outline-variant p-8 text-center" x-show="items.length === 0">
                <p class="text-sm font-medium text-on-surface mb-1">Nothing awaiting your sign-off</p>
                <p class="text-sm text-on-surface-variant">New invoices pending approval will appear here.</p>
            </div>
        </div>
    <?php endif; ?>

    <div>
        <div class="flex flex-wrap items-center gap-2 mb-3">
            <?php foreach ($statusFilterOptions as $value => $label): ?>
                <?php $active = $filter === $value; ?>
                <a href="<?= View::e(url($value === '' ? '/invoices' : '/invoices?status=' . $value)) ?>"
                   class="px-3 py-1.5 rounded text-sm font-medium transition-colors <?= $active ? 'bg-secondary-container text-on-secondary-container' : 'border border-outline text-on-surface-variant hover:bg-surface-container' ?>">
                    <?= View::e($label) ?>
                </a>
            <?php endforeach; ?>
        </div>

        <div class="bg-surface-container-lowest rounded-lg border border-outline-variant overflow-hidden">
            <div class="overflow-x-auto table-scroll">
                <table class="w-full text-sm min-w-[940px]">
                <thead class="bg-surface-container-low border-b border-outline-variant">
                    <tr>
                        <th class="text-left px-4 py-3 font-medium text-on-surface-variant">Invoice No</th>
                        <th class="text-left px-4 py-3 font-medium text-on-surface-variant">Date</th>
                        <th class="text-left px-4 py-3 font-medium text-on-surface-variant">Client</th>
                        <th class="text-left px-4 py-3 font-medium text-on-surface-variant">Description</th>
                        <th class="text-left px-4 py-3 font-medium text-on-surface-variant">Department</th>
                        <th class="text-right px-4 py-3 font-medium text-on-surface-variant">Amount</th>
                        <th class="text-left px-4 py-3 font-medium text-on-surface-variant">Due</th>
                        <th class="text-left px-4 py-3 font-medium text-on-surface-variant">Payment</th>
                        <th class="text-left px-4 py-3 font-medium text-on-surface-variant">Approval</th>
                        <?php if ($canEdit || $canDelete): ?>
                            <th class="text-right px-4 py-3 font-medium text-on-surface-variant">Actions</th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody class="divide-y divide-outline-variant">
                    <?php if (empty($invoices)): ?>
                        <tr>
                            <td class="px-4 py-12 text-center text-on-surface-variant" colspan="<?= ($canEdit || $canDelete) ? 10 : 9 ?>">
                                No invoices yet — record your first one.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($invoices as $invoice): ?>
                            <?php
                                $isOverdue = $invoice['due_date'] !== null
                                    && strtotime($invoice['due_date']) < $todayTs
                                    && $invoice['payment_status'] !== 'paid';
                                $rowClasses = $isOverdue ? 'bg-error-container/20' : 'hover:bg-surface-container-low';
                            ?>
                            <tr class="<?= $rowClasses ?> transition-colors">
                                <td class="px-4 py-3 text-on-surface font-medium whitespace-nowrap"><?= View::e($invoice['invoice_no']) ?></td>
                                <td class="px-4 py-3 text-on-surface whitespace-nowrap"><?= View::e(date('d/m/Y', strtotime($invoice['date']))) ?></td>
                                <td class="px-4 py-3 text-on-surface whitespace-nowrap"><?= View::e($invoice['client']) ?></td>
                                <td class="px-4 py-3 text-on-surface-variant max-w-[240px] truncate"><?= View::e($invoice['description'] ?? '—') ?></td>
                                <td class="px-4 py-3 text-on-surface whitespace-nowrap"><?= View::e($invoice['department_name'] ?? '—') ?></td>
                                <td class="px-4 py-3 text-on-surface text-right whitespace-nowrap"><?= naira($invoice['amount']) ?></td>
                                <td class="px-4 py-3 whitespace-nowrap">
                                    <?php if ($invoice['due_date'] !== null): ?>
                                        <span class="text-on-surface"><?= View::e(date('d/m/Y', strtotime($invoice['due_date']))) ?></span>
                                        <?php if ($isOverdue): ?>
                                            <?= status_badge('overdue') ?>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="text-on-surface-variant">—</span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-4 py-3">
                                    <div class="flex flex-wrap items-center gap-1.5">
                                        <?= status_badge($invoice['payment_status']) ?>
                                        <?php if ($invoice['payment_date'] !== null): ?>
                                            <span class="text-label-sm text-on-surface-variant"><?= View::e(date('d/m/Y', strtotime($invoice['payment_date']))) ?></span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td class="px-4 py-3">
                                    <div class="flex flex-wrap items-center gap-1.5">
                                        <?= status_badge($invoice['approval_status']) ?>
                                        <?php if ($invoice['approval_status'] === 'rejected' && !empty($invoice['rejected_reason'])): ?>
                                            <span class="text-label-sm text-on-surface-variant" title="<?= View::e($invoice['rejected_reason']) ?>">ⓘ</span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <?php if ($canEdit || $canDelete): ?>
                                    <td class="px-4 py-3">
                                        <div class="flex justify-end gap-2">
                                            <?php if ($canEdit): ?>
                                                <a href="<?= View::e(url('/invoices/edit/' . (int) $invoice['id'])) ?>"
                                                   class="inline-flex items-center gap-1.5 border border-outline text-on-surface font-medium text-sm px-3 py-2 rounded hover:bg-surface-container transition-colors">
                                                    <svg class="w-4 h-4" stroke="currentColor" fill="none">
                                                        <use href="<?= View::e(url('/assets/icons/sprite.svg')) ?>#pencil"></use>
                                                    </svg>
                                                    Edit
                                                </a>
                                            <?php endif; ?>
                                            <?php if ($canDelete): ?>
                                                <form method="post" action="<?= View::e(url('/invoices/delete/' . (int) $invoice['id'])) ?>"
                                                      onsubmit="return dottConfirmSubmit(this, 'Delete invoice?', '<?= View::e($invoice['invoice_no']) ?> — <?= View::e($invoice['client']) ?>', 'Delete');">
                                                    <?= Csrf::field() ?>
                                                    <button type="submit"
                                                            class="inline-flex items-center gap-1.5 bg-error text-on-error font-medium text-sm px-3 py-2 rounded hover:opacity-90 transition-opacity">
                                                        <svg class="w-4 h-4" stroke="currentColor" fill="none">
                                                            <use href="<?= View::e(url('/assets/icons/sprite.svg')) ?>#trash-2"></use>
                                                        </svg>
                                                        Delete
                                                    </button>
                                                </form>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                <?php endif; ?>
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
    window.INVOICE_QUEUE_ITEMS = <?= json_encode($pending) ?>;
    window.INVOICE_CSRF_TOKEN = <?= json_encode(Csrf::token()) ?>;

    function invoiceQueue() {
        return {
            items: window.INVOICE_QUEUE_ITEMS || [],
            busy: false,

            approve(item) {
                const self = this;
                dottConfirm(
                    'Approve this invoice?',
                    item.invoice_no + ' — ' + item.client + ' · ' + item.amount,
                    'Approve'
                ).then((result) => {
                    if (result.isConfirmed) {
                        self.postAction('/invoices/approve', { invoice_id: item.id }, (json) => {
                            self.removeItem(item.id);
                            dottToast.fire({ icon: 'success', title: json.message });
                        });
                    }
                });
            },

            reject(item) {
                const self = this;
                dottAlert.fire({
                    title: 'Reject this invoice?',
                    text: item.invoice_no + ' — ' + item.client + ' · ' + item.amount,
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
                        self.postAction('/invoices/reject', { invoice_id: item.id, reason: result.value.trim() }, (json) => {
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
                        body: JSON.stringify(Object.assign({ _csrf: window.INVOICE_CSRF_TOKEN }, payload)),
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