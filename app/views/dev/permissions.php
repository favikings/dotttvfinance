<div class="space-y-6">
    <div>
        <h1 class="text-headline-md">Permission Check (Dev)</h1>
        <p class="text-body-md text-on-surface-variant mt-1">
            Super Admin-only dev tool, no sidebar entry (direct URL only). To verify another role's permission set
            against PRD §3.2's matrix, query <code>role_permissions</code> directly rather than logging in as that role here.
        </p>
    </div>

    <div class="bg-surface-container-lowest rounded-lg border border-outline-variant p-card-padding shadow-[var(--shadow-ambient)]">
        <p class="text-label-sm uppercase text-on-surface-variant">Logged in as</p>
        <p class="text-display-lg mt-1"><?= View::e($user['name'] ?? '') ?></p>
        <p class="text-body-md text-on-surface-variant mt-2">
            Role: <span class="font-medium text-on-surface"><?= View::e($user['role_name'] ?? 'unknown') ?></span>
            &middot; <?= count($permissions) ?> permission<?= count($permissions) === 1 ? '' : 's' ?>
        </p>
    </div>

    <?php if (empty($permissions)): ?>
        <div class="bg-surface-container-lowest rounded-lg border border-outline-variant p-12 text-center">
            <p class="text-sm font-medium text-on-surface mb-1">No permissions loaded</p>
            <p class="text-sm text-on-surface-variant">This role has no rows in role_permissions, or the session predates login-time loading — log out and back in.</p>
        </div>
    <?php else: ?>
        <div class="bg-surface-container-lowest rounded-lg border border-outline-variant overflow-hidden">
            <div class="overflow-x-auto table-scroll">
                <table class="w-full text-sm">
                <thead class="bg-surface-container-low border-b border-outline-variant">
                    <tr>
                        <th class="text-left px-4 py-3 font-medium text-on-surface-variant">Module</th>
                        <th class="text-left px-4 py-3 font-medium text-on-surface-variant">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-outline-variant">
                    <?php foreach ($permissions as $permission): ?>
                        <?php [$module, $action] = array_pad(explode('.', $permission, 2), 2, ''); ?>
                        <tr class="hover:bg-surface-container-low transition-colors">
                            <td class="px-4 py-3 text-on-surface"><?= View::e($module) ?></td>
                            <td class="px-4 py-3 text-on-surface"><?= View::e($action) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            </div>
        </div>
    <?php endif; ?>
</div>
