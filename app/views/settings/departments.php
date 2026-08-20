<?php /** @var array<int, array> $departments @var ?array $editDepartment */ ?>
<div class="space-y-6">
    <div>
        <h1 class="text-headline-md">Settings</h1>
        <p class="text-body-md text-on-surface-variant mt-1">Departments, categories, approval rules, and users.</p>
    </div>

    <?= settings_tabs('departments') ?>
    <?= flash_messages() ?>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 items-start">
        <div class="lg:col-span-2 bg-surface-container-lowest rounded-lg border border-outline-variant overflow-hidden">
            <div class="overflow-x-auto table-scroll">
                <table class="w-full text-sm">
                <thead class="bg-surface-container-low border-b border-outline-variant">
                    <tr>
                        <th class="text-left px-4 py-3 font-medium text-on-surface-variant">Name</th>
                        <th class="text-right px-4 py-3 font-medium text-on-surface-variant">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-outline-variant">
                    <?php if (empty($departments)): ?>
                        <tr>
                            <td class="px-4 py-12 text-center text-on-surface-variant" colspan="2">
                                No departments yet — add the first one.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($departments as $dept): ?>
                            <tr class="hover:bg-surface-container-low transition-colors">
                                <td class="px-4 py-3 text-on-surface"><?= View::e($dept['name']) ?></td>
                                <td class="px-4 py-3">
                                    <div class="flex items-center justify-end gap-4">
                                        <a href="<?= View::e(url('/settings/departments?edit=' . (int) $dept['id'])) ?>"
                                           class="text-sm font-medium text-on-secondary hover:underline">Edit</a>
                                        <form method="post" action="<?= View::e(url('/settings/departments/delete')) ?>"
                                              class="inline" onsubmit="return dottConfirmSubmit(this, 'Delete this department?', 'This cannot be undone.', 'Delete', true);">
                                            <?= Csrf::field() ?>
                                            <input type="hidden" name="id" value="<?= (int) $dept['id'] ?>">
                                            <button type="submit" class="text-sm font-medium text-error hover:underline">Delete</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
            </div>
        </div>

        <div class="bg-surface-container-lowest rounded-lg border border-outline-variant p-6 shadow-[var(--shadow-ambient)]">
            <?php if ($editDepartment !== null): ?>
                <h2 class="text-headline-sm mb-1">Edit department</h2>
                <p class="text-body-md text-on-surface-variant mb-4">Rename "<?= View::e($editDepartment['name']) ?>".</p>
                <form method="post" action="<?= View::e(url('/settings/departments/update')) ?>">
                    <?= Csrf::field() ?>
                    <input type="hidden" name="id" value="<?= (int) $editDepartment['id'] ?>">
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-on-surface mb-1.5" for="dept-name">Name</label>
                        <input type="text" id="dept-name" name="name" value="<?= View::e($editDepartment['name']) ?>"
                               required class="w-full px-3 py-2.5 rounded border border-outline bg-surface-container-lowest text-on-surface text-sm focus:outline-none focus:ring-2 focus:ring-secondary-container focus:border-transparent">
                    </div>
                    <div class="flex gap-3">
                        <button type="submit"
                                class="bg-secondary-container text-on-secondary-container font-medium text-sm px-4 py-2.5 rounded hover:opacity-90 transition-opacity">
                            Save changes
                        </button>
                        <a href="<?= View::e(url('/settings/departments')) ?>"
                           class="border border-outline text-on-surface font-medium text-sm px-4 py-2.5 rounded hover:bg-surface-container transition-colors">
                            Cancel
                        </a>
                    </div>
                </form>
            <?php else: ?>
                <h2 class="text-headline-sm mb-1">Add department</h2>
                <p class="text-body-md text-on-surface-variant mb-4">Departments tag expenses, invoices, and payroll records.</p>
                <form method="post" action="<?= View::e(url('/settings/departments/create')) ?>">
                    <?= Csrf::field() ?>
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-on-surface mb-1.5" for="dept-name">Name</label>
                        <input type="text" id="dept-name" name="name" placeholder="e.g. Newsroom"
                               required class="w-full px-3 py-2.5 rounded border border-outline bg-surface-container-lowest text-on-surface text-sm focus:outline-none focus:ring-2 focus:ring-secondary-container focus:border-transparent">
                    </div>
                    <button type="submit"
                            class="bg-secondary-container text-on-secondary-container font-medium text-sm px-4 py-2.5 rounded hover:opacity-90 transition-opacity">
                        Add department
                    </button>
                </form>
            <?php endif; ?>
        </div>
    </div>
</div>
