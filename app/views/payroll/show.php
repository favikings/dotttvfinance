<?php
/**
 * @var array $run
 * @var array<int, array> $items
 * @var array $totals
 * @var array<int, array> $departments
 * @var bool $canEdit
 * @var bool $canPay
 * @var bool $canApprove
 */
$grossPay = (float) $totals['total_basic'] + (float) $totals['total_allowances'];
$totalDeductions = (float) $totals['total_deductions'] + (float) $totals['total_loan_deduction'];
?>
<div class="space-y-6">
    <div class="flex items-start justify-between gap-4">
        <div>
            <h1 class="text-headline-md">
                <?= View::e(PayrollRun::monthName((int) $run['period_month'])) ?> <?= (int) $run['period_year'] ?>
            </h1>
            <div class="flex items-center gap-2 mt-1.5">
                <?= status_badge($run['status']) ?>
                <?php if ($run['approved_by_name'] !== null): ?>
                    <span class="text-body-md text-on-surface-variant">Approved by <?= View::e($run['approved_by_name']) ?></span>
                <?php endif; ?>
            </div>
        </div>
        <a href="<?= View::e(url('/payroll')) ?>"
           class="shrink-0 border border-outline text-on-surface font-medium text-sm px-4 py-2.5 rounded hover:bg-surface-container transition-colors">
            Back to Payroll
        </a>
    </div>

    <?= flash_messages() ?>

    <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-4">
        <?= metric_card('Employees', (string) $totals['item_count']) ?>
        <?= metric_card('Gross Pay', naira($grossPay)) ?>
        <?= metric_card('Deductions', naira($totalDeductions)) ?>
        <?= metric_card('Net Payroll', naira($totals['total_net_salary'])) ?>
    </div>

    <?php if ($canApprove): ?>
        <div class="bg-surface-container-lowest rounded-lg border border-outline-variant p-4 flex items-center justify-between gap-4">
            <p class="text-sm text-on-surface-variant">Once approved, employees can no longer be added or removed — only their payment status can change.</p>
            <form method="post" action="<?= View::e(url('/payroll/' . (int) $run['id'] . '/approve')) ?>"
                  onsubmit="return dottConfirmSubmit(this, 'Approve this payroll run?', '<?= View::e(PayrollRun::monthName((int) $run['period_month'])) ?> <?= (int) $run['period_year'] ?> — <?= (int) $totals['item_count'] ?> employees, <?= View::e(naira($totals['total_net_salary'])) ?> net', 'Approve', false);">
                <?= Csrf::field() ?>
                <button type="submit"
                        class="shrink-0 inline-flex items-center gap-1.5 bg-secondary-container text-on-secondary-container font-medium text-sm px-4 py-2.5 rounded hover:opacity-90 transition-opacity">
                    <svg class="w-4 h-4" stroke="currentColor" fill="none">
                        <use href="<?= View::e(url('/assets/icons/sprite.svg')) ?>#check"></use>
                    </svg>
                    Approve Run
                </button>
            </form>
        </div>
    <?php endif; ?>

    <div>
        <h2 class="text-headline-sm mb-3">Employees</h2>

        <?php if (empty($items)): ?>
            <div class="bg-surface-container-lowest rounded-lg border border-outline-variant p-8 text-center">
                <p class="text-sm font-medium text-on-surface mb-1">No employees on this run yet</p>
                <p class="text-sm text-on-surface-variant">Add employees using the form below.</p>
            </div>
        <?php else: ?>
            <div class="bg-surface-container-lowest rounded-lg border border-outline-variant overflow-hidden">
                <div class="overflow-x-auto table-scroll">
                    <table class="w-full text-sm min-w-[900px]">
                    <thead class="bg-surface-container-low border-b border-outline-variant">
                        <tr>
                            <th class="text-left px-4 py-3 font-medium text-on-surface-variant">Employee</th>
                            <th class="text-left px-4 py-3 font-medium text-on-surface-variant">Department</th>
                            <th class="text-right px-4 py-3 font-medium text-on-surface-variant">Basic</th>
                            <th class="text-right px-4 py-3 font-medium text-on-surface-variant">Allowances</th>
                            <th class="text-right px-4 py-3 font-medium text-on-surface-variant">Deductions</th>
                            <th class="text-right px-4 py-3 font-medium text-on-surface-variant">Loan Ded.</th>
                            <th class="text-right px-4 py-3 font-medium text-on-surface-variant">Net Salary</th>
                            <th class="text-left px-4 py-3 font-medium text-on-surface-variant">Payment</th>
                            <?php if ($canPay || $canEdit): ?>
                                <th class="text-right px-4 py-3 font-medium text-on-surface-variant">Actions</th>
                            <?php endif; ?>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-outline-variant">
                        <?php foreach ($items as $item): ?>
                            <tr class="hover:bg-surface-container-low transition-colors">
                                <td class="px-4 py-3 text-on-surface font-medium whitespace-nowrap"><?= View::e($item['employee_name']) ?></td>
                                <td class="px-4 py-3 text-on-surface-variant whitespace-nowrap"><?= View::e($item['department_name'] ?? '—') ?></td>
                                <td class="px-4 py-3 text-on-surface text-right whitespace-nowrap"><?= naira($item['basic_salary']) ?></td>
                                <td class="px-4 py-3 text-on-surface text-right whitespace-nowrap"><?= naira($item['allowances']) ?></td>
                                <td class="px-4 py-3 text-on-surface text-right whitespace-nowrap"><?= naira($item['deductions']) ?></td>
                                <td class="px-4 py-3 text-on-surface text-right whitespace-nowrap"><?= naira($item['loan_deduction']) ?></td>
                                <td class="px-4 py-3 text-on-surface text-right font-medium whitespace-nowrap"><?= naira($item['net_salary']) ?></td>
                                <td class="px-4 py-3"><?= status_badge($item['payment_status']) ?></td>
                                <?php if ($canPay || $canEdit): ?>
                                    <td class="px-4 py-3">
                                        <div class="flex justify-end gap-2">
                                            <?php if ($canPay && $item['payment_status'] === PayrollItemPaymentStatus::Pending->value): ?>
                                                <form method="post" action="<?= View::e(url('/payroll/items/' . (int) $item['id'] . '/pay')) ?>"
                                                      onsubmit="return dottConfirmSubmit(this, 'Mark as paid?', '<?= View::e($item['employee_name']) ?> — <?= View::e(naira($item['net_salary'])) ?>', 'Mark Paid', false);">
                                                    <?= Csrf::field() ?>
                                                    <button type="submit"
                                                            class="inline-flex items-center gap-1.5 bg-secondary-container text-on-secondary-container font-medium text-sm px-3 py-2 rounded hover:opacity-90 transition-opacity">
                                                        <svg class="w-4 h-4" stroke="currentColor" fill="none">
                                                            <use href="<?= View::e(url('/assets/icons/sprite.svg')) ?>#check"></use>
                                                        </svg>
                                                        Mark Paid
                                                    </button>
                                                </form>
                                            <?php endif; ?>
                                            <?php if ($canEdit): ?>
                                                <form method="post" action="<?= View::e(url('/payroll/items/' . (int) $item['id'] . '/delete')) ?>"
                                                      onsubmit="return dottConfirmSubmit(this, 'Remove this employee?', '<?= View::e($item['employee_name']) ?>', 'Remove');">
                                                    <?= Csrf::field() ?>
                                                    <button type="submit"
                                                            class="inline-flex items-center gap-1.5 bg-error text-on-error font-medium text-sm px-3 py-2 rounded hover:opacity-90 transition-opacity">
                                                        <svg class="w-4 h-4" stroke="currentColor" fill="none">
                                                            <use href="<?= View::e(url('/assets/icons/sprite.svg')) ?>#trash-2"></use>
                                                        </svg>
                                                        Remove
                                                    </button>
                                                </form>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                <?php endif; ?>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <?php if ($canEdit): ?>
        <div>
            <h2 class="text-headline-sm mb-3">Add Employees</h2>

            <!-- UI Component Guide §8a — single x-data scope wraps the whole table AND both buttons. -->
            <form method="post" action="<?= View::e(url('/payroll/' . (int) $run['id'] . '/items')) ?>"
                  x-ref="form"
                  x-data="payrollItemForm()">
                <?= Csrf::field() ?>

                <div class="bg-surface-container-lowest rounded-lg border border-outline-variant overflow-hidden">
                    <div class="overflow-x-auto table-scroll">
<table class="w-full text-sm min-w-[880px]">
                        <thead class="bg-surface-container-low border-b border-outline-variant">
                            <tr>
                                <th class="text-left px-3 py-3 font-medium text-on-surface-variant">Employee</th>
                                <th class="text-left px-3 py-3 font-medium text-on-surface-variant w-[190px]">Department</th>
                                <th class="text-right px-3 py-3 font-medium text-on-surface-variant w-[140px]">Basic (₦)</th>
                                <th class="text-right px-3 py-3 font-medium text-on-surface-variant w-[140px]">Allowances (₦)</th>
                                <th class="text-right px-3 py-3 font-medium text-on-surface-variant w-[140px]">Deductions (₦)</th>
                                <th class="text-right px-3 py-3 font-medium text-on-surface-variant w-[140px]">Loan Ded. (₦)</th>
                                <th class="w-10"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-outline-variant">
                            <template x-for="row in rows" :key="row.id">
                                <tr>
                                    <td class="px-2 py-2">
                                        <input type="text"
                                               :id="'row-name-' + row.id" :name="'employee_name[' + row.id + ']'"
                                               x-model="row.employee_name" required
                                               class="w-full px-2.5 py-2 rounded border border-outline bg-surface-container-lowest text-on-surface text-sm focus:outline-none focus:ring-2 focus:ring-secondary-container focus:border-transparent">
                                    </td>
                                    <td class="px-2 py-2">
                                        <select :name="'department_id[' + row.id + ']'"
                                                x-model="row.department_id"
                                                class="w-full px-2.5 py-2 rounded border border-outline bg-surface-container-lowest text-on-surface text-sm focus:outline-none focus:ring-2 focus:ring-secondary-container focus:border-transparent">
                                            <option value="">No department</option>
                                            <?php foreach ($departments as $department): ?>
                                                <option value="<?= (int) $department['id'] ?>"><?= View::e($department['name']) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </td>
                                    <td class="px-2 py-2">
                                        <input type="number" min="0.01" step="0.01" inputmode="decimal"
                                               :name="'basic_salary[' + row.id + ']'"
                                               x-model="row.basic_salary" required
                                               class="w-full px-2.5 py-2 rounded border border-outline bg-surface-container-lowest text-on-surface text-sm text-right focus:outline-none focus:ring-2 focus:ring-secondary-container focus:border-transparent">
                                    </td>
                                    <td class="px-2 py-2">
                                        <input type="number" min="0" step="0.01" inputmode="decimal"
                                               :name="'allowances[' + row.id + ']'"
                                               x-model="row.allowances"
                                               class="w-full px-2.5 py-2 rounded border border-outline bg-surface-container-lowest text-on-surface text-sm text-right focus:outline-none focus:ring-2 focus:ring-secondary-container focus:border-transparent">
                                    </td>
                                    <td class="px-2 py-2">
                                        <input type="number" min="0" step="0.01" inputmode="decimal"
                                               :name="'deductions[' + row.id + ']'"
                                               x-model="row.deductions"
                                               class="w-full px-2.5 py-2 rounded border border-outline bg-surface-container-lowest text-on-surface text-sm text-right focus:outline-none focus:ring-2 focus:ring-secondary-container focus:border-transparent">
                                    </td>
                                    <td class="px-2 py-2">
                                        <input type="number" min="0" step="0.01" inputmode="decimal"
                                               :name="'loan_deduction[' + row.id + ']'"
                                               x-model="row.loan_deduction"
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
                        Save All (<span x-text="rows.length"></span> <span x-text="rows.length === 1 ? 'employee' : 'employees'"></span>)
                    </button>
                </div>
            </form>
        </div>
    <?php endif; ?>
</div>

<script>
    function payrollItemForm() {
        return {
            nextId: 1,
            rows: [{ id: 0, employee_name: '', department_id: '', basic_salary: '', allowances: '', deductions: '', loan_deduction: '' }],
            addRow() {
                const id = this.nextId++;
                this.rows.push({ id, employee_name: '', department_id: '', basic_salary: '', allowances: '', deductions: '', loan_deduction: '' });
                this.$nextTick(() => {
                    const el = document.getElementById('row-name-' + id);
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
