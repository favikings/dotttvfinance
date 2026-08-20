<?php /** @var array<int, array> $users @var array<int, array> $roles @var array<int, array> $departments @var ?array $editUser */ ?>
<div class="space-y-6">
    <div>
        <h1 class="text-headline-md">Settings</h1>
        <p class="text-body-md text-on-surface-variant mt-1">Departments, categories, approval rules, and users.</p>
    </div>

    <?= settings_tabs('users') ?>
    <?= flash_messages() ?>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 items-start">
        <div class="lg:col-span-2 bg-surface-container-lowest rounded-lg border border-outline-variant overflow-hidden">
            <div class="overflow-x-auto table-scroll">
                <table class="w-full text-sm min-w-[700px]">
                <thead class="bg-surface-container-low border-b border-outline-variant">
                    <tr>
                        <th class="text-left px-4 py-3 font-medium text-on-surface-variant">Name</th>
                        <th class="text-left px-4 py-3 font-medium text-on-surface-variant">Email</th>
                        <th class="text-left px-4 py-3 font-medium text-on-surface-variant">Role</th>
                        <th class="text-left px-4 py-3 font-medium text-on-surface-variant">Department</th>
                        <th class="text-left px-4 py-3 font-medium text-on-surface-variant">Status</th>
                        <th class="text-right px-4 py-3 font-medium text-on-surface-variant">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-outline-variant">
                    <?php if (empty($users)): ?>
                        <tr>
                            <td class="px-4 py-12 text-center text-on-surface-variant" colspan="6">
                                No users yet — add the first one.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($users as $user): ?>
                            <tr class="hover:bg-surface-container-low transition-colors">
                                <td class="px-4 py-3 text-on-surface font-medium whitespace-nowrap"><?= View::e($user['name']) ?></td>
                                <td class="px-4 py-3 text-on-surface whitespace-nowrap"><?= View::e($user['email']) ?></td>
                                <td class="px-4 py-3"><?= role_badge($user['role_name'] ?? '') ?></td>
                                <td class="px-4 py-3 text-on-surface-variant whitespace-nowrap"><?= View::e($user['department_name'] ?? '—') ?></td>
                                <td class="px-4 py-3"><?= status_badge($user['status']) ?></td>
                                <td class="px-4 py-3">
                                    <div class="flex items-center justify-end gap-4">
                                        <a href="<?= View::e(url('/settings/users?edit=' . (int) $user['id'])) ?>"
                                           class="text-sm font-medium text-on-secondary hover:underline">Edit</a>
                                        <form method="post" action="<?= View::e(url('/settings/users/delete')) ?>"
                                              class="inline" onsubmit="return dottConfirmSubmit(this, 'Delete this user?', 'This cannot be undone. The user will lose access immediately.', 'Delete', true);">
                                            <?= Csrf::field() ?>
                                            <input type="hidden" name="id" value="<?= (int) $user['id'] ?>">
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
            <?php if ($editUser !== null): ?>
                <h2 class="text-headline-sm mb-1">Edit user</h2>
                <p class="text-body-md text-on-surface-variant mb-4">
                    Update "<?= View::e($editUser['name']) ?>". Leave the password blank to keep it unchanged.
                </p>
                <form method="post" action="<?= View::e(url('/settings/users/update')) ?>">
                    <?= Csrf::field() ?>
                    <input type="hidden" name="id" value="<?= (int) $editUser['id'] ?>">

                    <div class="mb-4">
                        <label class="block text-sm font-medium text-on-surface mb-1.5" for="user-name">Name</label>
                        <input type="text" id="user-name" name="name" value="<?= View::e($editUser['name']) ?>" required
                               class="w-full px-3 py-2.5 rounded border border-outline bg-surface-container-lowest text-on-surface text-sm focus:outline-none focus:ring-2 focus:ring-secondary-container focus:border-transparent">
                    </div>

                    <div class="mb-4">
                        <label class="block text-sm font-medium text-on-surface mb-1.5" for="user-email">Email</label>
                        <input type="email" id="user-email" name="email" value="<?= View::e($editUser['email']) ?>" required
                               class="w-full px-3 py-2.5 rounded border border-outline bg-surface-container-lowest text-on-surface text-sm focus:outline-none focus:ring-2 focus:ring-secondary-container focus:border-transparent">
                    </div>

                    <div class="grid grid-cols-2 gap-4 mb-4">
                        <div>
                            <label class="block text-sm font-medium text-on-surface mb-1.5" for="user-role">Role</label>
                            <select id="user-role" name="role_id" required
                                    class="w-full px-3 py-2.5 rounded border border-outline bg-surface-container-lowest text-on-surface text-sm focus:outline-none focus:ring-2 focus:ring-secondary-container focus:border-transparent">
                                <?php foreach ($roles as $role): ?>
                                    <option value="<?= (int) $role['id'] ?>" <?= (int) $role['id'] === (int) $editUser['role_id'] ? 'selected' : '' ?>>
                                        <?= View::e($role['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-on-surface mb-1.5" for="user-status">Status</label>
                            <select id="user-status" name="status"
                                    class="w-full px-3 py-2.5 rounded border border-outline bg-surface-container-lowest text-on-surface text-sm focus:outline-none focus:ring-2 focus:ring-secondary-container focus:border-transparent">
                                <option value="active" <?= $editUser['status'] === 'active' ? 'selected' : '' ?>>Active</option>
                                <option value="inactive" <?= $editUser['status'] === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                            </select>
                        </div>
                    </div>

                    <div class="mb-4">
                        <label class="block text-sm font-medium text-on-surface mb-1.5" for="user-dept">Department</label>
                        <select id="user-dept" name="department_id"
                                class="w-full px-3 py-2.5 rounded border border-outline bg-surface-container-lowest text-on-surface text-sm focus:outline-none focus:ring-2 focus:ring-secondary-container focus:border-transparent">
                            <option value="">— None —</option>
                            <?php foreach ($departments as $dept): ?>
                                <option value="<?= (int) $dept['id'] ?>" <?= $editUser['department_id'] !== null && (int) $dept['id'] === (int) $editUser['department_id'] ? 'selected' : '' ?>>
                                    <?= View::e($dept['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-4">
                        <label class="block text-sm font-medium text-on-surface mb-1.5" for="user-password">New password</label>
                        <input type="password" id="user-password" name="password" placeholder="Leave blank to keep current" autocomplete="new-password"
                               class="w-full px-3 py-2.5 rounded border border-outline bg-surface-container-lowest text-on-surface text-sm focus:outline-none focus:ring-2 focus:ring-secondary-container focus:border-transparent">
                    </div>

                    <div class="flex gap-3">
                        <button type="submit"
                                class="bg-secondary-container text-on-secondary-container font-medium text-sm px-4 py-2.5 rounded hover:opacity-90 transition-opacity">
                            Save changes
                        </button>
                        <a href="<?= View::e(url('/settings/users')) ?>"
                           class="border border-outline text-on-surface font-medium text-sm px-4 py-2.5 rounded hover:bg-surface-container transition-colors">
                            Cancel
                        </a>
                    </div>
                </form>
            <?php else: ?>
                <h2 class="text-headline-sm mb-1">Add user</h2>
                <p class="text-body-md text-on-surface-variant mb-4">Create a login for a new team member.</p>
                <form method="post" action="<?= View::e(url('/settings/users/create')) ?>">
                    <?= Csrf::field() ?>

                    <div class="mb-4">
                        <label class="block text-sm font-medium text-on-surface mb-1.5" for="user-name">Name</label>
                        <input type="text" id="user-name" name="name" placeholder="e.g. Ifeoma Okafor" required
                               class="w-full px-3 py-2.5 rounded border border-outline bg-surface-container-lowest text-on-surface text-sm focus:outline-none focus:ring-2 focus:ring-secondary-container focus:border-transparent">
                    </div>

                    <div class="mb-4">
                        <label class="block text-sm font-medium text-on-surface mb-1.5" for="user-email">Email</label>
                        <input type="email" id="user-email" name="email" placeholder="name@dotttv.tv" required
                               class="w-full px-3 py-2.5 rounded border border-outline bg-surface-container-lowest text-on-surface text-sm focus:outline-none focus:ring-2 focus:ring-secondary-container focus:border-transparent">
                    </div>

                    <div class="mb-4">
                        <label class="block text-sm font-medium text-on-surface mb-1.5" for="user-role">Role</label>
                        <select id="user-role" name="role_id" required
                                class="w-full px-3 py-2.5 rounded border border-outline bg-surface-container-lowest text-on-surface text-sm focus:outline-none focus:ring-2 focus:ring-secondary-container focus:border-transparent">
                            <?php foreach ($roles as $role): ?>
                                <option value="<?= (int) $role['id'] ?>"><?= View::e($role['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-4">
                        <label class="block text-sm font-medium text-on-surface mb-1.5" for="user-dept">Department</label>
                        <select id="user-dept" name="department_id"
                                class="w-full px-3 py-2.5 rounded border border-outline bg-surface-container-lowest text-on-surface text-sm focus:outline-none focus:ring-2 focus:ring-secondary-container focus:border-transparent">
                            <option value="">— None —</option>
                            <?php foreach ($departments as $dept): ?>
                                <option value="<?= (int) $dept['id'] ?>"><?= View::e($dept['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-4">
                        <label class="block text-sm font-medium text-on-surface mb-1.5" for="user-password">Password</label>
                        <input type="password" id="user-password" name="password" placeholder="At least 8 characters" required autocomplete="new-password"
                               class="w-full px-3 py-2.5 rounded border border-outline bg-surface-container-lowest text-on-surface text-sm focus:outline-none focus:ring-2 focus:ring-secondary-container focus:border-transparent">
                    </div>

                    <button type="submit"
                            class="bg-secondary-container text-on-secondary-container font-medium text-sm px-4 py-2.5 rounded hover:opacity-90 transition-opacity">
                        Add user
                    </button>
                </form>
            <?php endif; ?>
        </div>
    </div>
</div>
