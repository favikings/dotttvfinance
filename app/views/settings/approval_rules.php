<?php /** @var array<int, array> $rules */ ?>
<?php $roleOptions = ['accountant' => 'Accountant', 'gm' => 'GM', 'chairman' => 'Chairman']; ?>
<div class="space-y-6">
    <div>
        <h1 class="text-headline-md">Settings</h1>
        <p class="text-body-md text-on-surface-variant mt-1">Departments, categories, approval rules, and users.</p>
    </div>

    <?= settings_tabs('approval_rules') ?>
    <?= flash_messages() ?>

    <div class="bg-surface-container-lowest rounded-lg border border-outline-variant p-6 shadow-[var(--shadow-ambient)]">
        <h2 class="text-headline-sm mb-1">How the approval engine reads this</h2>
        <p class="text-body-md text-on-surface-variant">
            When an expense is submitted, the system picks the tier its amount falls into and generates one
            approval step per required approver, in order. Changes take effect for new expenses only — they
            never rewrite history on already-approved records. Rules enforced on save: tiers must cover every
            amount with no gaps or overlaps, only the highest tier may be open-ended, and every tier must
            include all approvers of the tiers below it, in the same order.
        </p>
    </div>

    <form method="post" action="<?= View::e(url('/settings/approval-rules/save')) ?>">
        <?= Csrf::field() ?>

        <div class="bg-surface-container-lowest rounded-lg border border-outline-variant overflow-hidden">
            <div class="overflow-x-auto table-scroll">
                <table class="w-full text-sm min-w-[640px]">
                <thead class="bg-surface-container-low border-b border-outline-variant">
                    <tr>
                        <th class="text-left px-4 py-3 font-medium text-on-surface-variant">Tier</th>
                        <th class="text-left px-4 py-3 font-medium text-on-surface-variant">Min amount</th>
                        <th class="text-left px-4 py-3 font-medium text-on-surface-variant">Max amount</th>
                        <th class="text-left px-4 py-3 font-medium text-on-surface-variant">Required approvers</th>
                        <th class="text-left px-4 py-3 font-medium text-on-surface-variant">Remove</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-outline-variant">
                    <?php $newRow = count($rules); ?>
                    <?php foreach ($rules as $i => $rule): ?>
                        <tr>
                            <td class="px-4 py-3 text-on-surface font-medium"><?= (int) $rule['tier_order'] ?></td>
                            <td class="px-4 py-3">
                                <input type="number" name="min_amount[<?= $i ?>]" min="0" step="0.01" inputmode="decimal"
                                       value="<?= View::e($rule['min_amount']) ?>" required
                                       class="w-28 px-3 py-2 rounded border border-outline bg-surface-container-lowest text-on-surface text-sm focus:outline-none focus:ring-2 focus:ring-secondary-container focus:border-transparent">
                            </td>
                            <td class="px-4 py-3">
                                <input type="number" name="max_amount[<?= $i ?>]" min="0" step="0.01" inputmode="decimal"
                                       value="<?= $rule['max_amount'] !== null ? View::e($rule['max_amount']) : '' ?>"
                                       placeholder="No max"
                                       class="w-28 px-3 py-2 rounded border border-outline bg-surface-container-lowest text-on-surface text-sm focus:outline-none focus:ring-2 focus:ring-secondary-container focus:border-transparent">
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex flex-wrap gap-x-4 gap-y-2">
                                    <?php foreach ($roleOptions as $roleKey => $roleLabel): ?>
                                        <label class="inline-flex items-center gap-1.5 text-sm text-on-surface">
                                            <input type="checkbox" name="required_roles[<?= $i ?>][]" value="<?= $roleKey ?>"
                                                   <?= in_array($roleKey, $rule['required_roles'], true) ? 'checked' : '' ?>>
                                            <?= View::e($roleLabel) ?>
                                        </label>
                                    <?php endforeach; ?>
                                </div>
                            </td>
                            <td class="px-4 py-3">
                                <label class="inline-flex items-center gap-1.5 text-sm text-on-surface-variant">
                                    <input type="checkbox" name="delete[<?= $i ?>]" value="1"> Remove
                                </label>
                            </td>
                            <input type="hidden" name="tier_id[<?= $i ?>]" value="<?= (int) $rule['id'] ?>">
                        </tr>
                    <?php endforeach; ?>
                    <tr class="bg-surface-container-low/50">
                        <td class="px-4 py-3 text-on-surface-variant font-medium">New</td>
                        <td class="px-4 py-3">
                            <input type="number" name="min_amount[<?= $newRow ?>]" min="0" step="0.01" inputmode="decimal"
                                   placeholder="0.00"
                                   class="w-28 px-3 py-2 rounded border border-outline bg-surface-container-lowest text-on-surface text-sm focus:outline-none focus:ring-2 focus:ring-secondary-container focus:border-transparent">
                        </td>
                        <td class="px-4 py-3">
                            <input type="number" name="max_amount[<?= $newRow ?>]" min="0" step="0.01" inputmode="decimal"
                                   placeholder="No max"
                                   class="w-28 px-3 py-2 rounded border border-outline bg-surface-container-lowest text-on-surface text-sm focus:outline-none focus:ring-2 focus:ring-secondary-container focus:border-transparent">
                        </td>
                        <td class="px-4 py-3">
                            <div class="flex flex-wrap gap-x-4 gap-y-2">
                                <?php foreach ($roleOptions as $roleKey => $roleLabel): ?>
                                    <label class="inline-flex items-center gap-1.5 text-sm text-on-surface">
                                        <input type="checkbox" name="required_roles[<?= $newRow ?>][]" value="<?= $roleKey ?>">
                                        <?= View::e($roleLabel) ?>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </td>
                        <td class="px-4 py-3"></td>
                        <input type="hidden" name="tier_id[<?= $newRow ?>]" value="new">
                    </tr>
                </tbody>
            </table>
            </div>
        </div>

        <div class="mt-4 flex items-center justify-between">
            <p class="text-body-md text-on-surface-variant">
                Rows are renumbered into order 1–<?= count($rules) + 1 ?> on save.
            </p>
            <button type="submit"
                    class="bg-secondary-container text-on-secondary-container font-medium text-sm px-4 py-2.5 rounded hover:opacity-90 transition-opacity">
                Save approval rules
            </button>
        </div>
    </form>
</div>
