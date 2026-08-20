<?php /** @var array<int, array> $departments @var string $today */ ?>
<div class="space-y-6">
    <div>
        <h1 class="text-headline-md">Historical Entry</h1>
        <p class="text-body-md text-on-surface-variant mt-1">Backfill paper-book records. Saved rows are marked <span class="font-medium text-on-surface">Backfilled</span> and skip the approval workflow — they're already approved.</p>
    </div>

    <?= historical_tabs('expenses') ?>
    <?= flash_messages() ?>

    <!-- UI Component Guide §8a — single x-data scope wraps the whole table AND both buttons. -->
    <form method="post" action="<?= View::e(url('/historical-entry/expenses')) ?>"
          x-ref="form"
          x-data="historicalExpenseForm()">
        <?= Csrf::field() ?>

        <div class="bg-surface-container-lowest rounded-lg border border-outline-variant overflow-hidden">
            <div class="overflow-x-auto table-scroll">
                <table class="w-full text-sm min-w-[900px]">
                <thead class="bg-surface-container-low border-b border-outline-variant">
                    <tr>
                        <th class="text-left px-3 py-3 font-medium text-on-surface-variant w-[150px]">Date</th>
                        <th class="text-left px-3 py-3 font-medium text-on-surface-variant w-[150px]">Document No</th>
                        <th class="text-left px-3 py-3 font-medium text-on-surface-variant">Payee</th>
                        <th class="text-left px-3 py-3 font-medium text-on-surface-variant">Description</th>
                        <th class="text-left px-3 py-3 font-medium text-on-surface-variant w-[190px]">Department</th>
                        <th class="text-right px-3 py-3 font-medium text-on-surface-variant w-[160px]">Amount (₦)</th>
                        <th class="w-10"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-outline-variant">
                    <template x-for="row in rows" :key="row.id">
                        <tr>
                            <td class="px-2 py-2">
                                <input type="date"
                                       :id="'row-date-' + row.id" :name="'date[' + row.id + ']'"
                                       x-model="row.date" required
                                       class="w-full px-2.5 py-2 rounded border border-outline bg-surface-container-lowest text-on-surface text-sm focus:outline-none focus:ring-2 focus:ring-secondary-container focus:border-transparent">
                            </td>
                            <td class="px-2 py-2">
                                <input type="text"
                                       :name="'document_no[' + row.id + ']'"
                                       x-model="row.document_no" placeholder="Optional"
                                       class="w-full px-2.5 py-2 rounded border border-outline bg-surface-container-lowest text-on-surface text-sm focus:outline-none focus:ring-2 focus:ring-secondary-container focus:border-transparent">
                            </td>
                            <td class="px-2 py-2">
                                <input type="text"
                                       :name="'payee[' + row.id + ']'"
                                       x-model="row.payee" required
                                       class="w-full px-2.5 py-2 rounded border border-outline bg-surface-container-lowest text-on-surface text-sm focus:outline-none focus:ring-2 focus:ring-secondary-container focus:border-transparent">
                            </td>
                            <td class="px-2 py-2">
                                <input type="text"
                                       :name="'description[' + row.id + ']'"
                                       x-model="row.description" required
                                       class="w-full px-2.5 py-2 rounded border border-outline bg-surface-container-lowest text-on-surface text-sm focus:outline-none focus:ring-2 focus:ring-secondary-container focus:border-transparent">
                            </td>
                            <td class="px-2 py-2">
                                <select :name="'department_id[' + row.id + ']'"
                                        x-model="row.department_id" required
                                        class="w-full px-2.5 py-2 rounded border border-outline bg-surface-container-lowest text-on-surface text-sm focus:outline-none focus:ring-2 focus:ring-secondary-container focus:border-transparent">
                                    <option value="">Select</option>
                                    <?php foreach ($departments as $department): ?>
                                        <option value="<?= (int) $department['id'] ?>"><?= View::e($department['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                            <td class="px-2 py-2">
                                <input type="number" min="0.01" step="0.01" inputmode="decimal"
                                       :name="'amount[' + row.id + ']'"
                                       x-model="row.amount" required
                                       x-on:keydown.enter.prevent="addRow()"
                                       class="w-full px-2.5 py-2 rounded border border-outline bg-surface-container-lowest text-on-surface text-sm text-right focus:outline-none focus:ring-2 focus:ring-secondary-container focus:border-transparent">
                            </td>
                            <td class="px-2 py-2 text-center">
                                <button type="button"
                                        x-on:click="removeRow(row.id)"
                                        x-show="rows.length > 1"
                                        class="text-on-surface-variant hover:text-error transition-colors"
                                        aria-label="Remove row">
                                    &times;
                                </button>
                            </td>
                        </tr>
                    </template>
                </tbody>
            </table>
            </div>
        </div>

        <div class="flex items-center justify-between gap-4 mt-4">
            <button type="button" x-on:click="addRow()"
                    class="border border-outline text-on-surface font-medium text-sm px-4 py-2.5 rounded hover:bg-surface-container transition-colors">
                + Add Row
            </button>
            <button type="button" x-on:click="saveAll()"
                    class="bg-secondary-container text-on-secondary-container font-medium text-sm px-4 py-2.5 rounded hover:opacity-90 transition-opacity">
                Save All (<span x-text="rows.length"></span> <span x-text="rows.length === 1 ? 'row' : 'rows'"></span>)
            </button>
        </div>
    </form>
</div>

<script>
    function historicalExpenseForm() {
        return {
            nextId: 1,
            rows: [{ id: 0, date: '<?= View::e($today) ?>', document_no: '', payee: '', description: '', department_id: '', amount: '' }],
            addRow() {
                const id = this.nextId++;
                this.rows.push({ id, date: '<?= View::e($today) ?>', document_no: '', payee: '', description: '', department_id: '', amount: '' });
                this.$nextTick(() => {
                    const el = document.getElementById('row-date-' + id);
                    if (el) {
                        el.focus();
                    }
                });
            },
            removeRow(id) {
                if (this.rows.length > 1) {
                    this.rows = this.rows.filter((row) => row.id !== id);
                }
            },
            saveAll() {
                this.$refs.form.requestSubmit();
            },
        };
    }
</script>