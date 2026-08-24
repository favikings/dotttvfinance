<?php
/**
 * Sidebar footer Account Menu (UI Component Guide §2a) — logged-in user's
 * name/role, dropdown with Change Password + Logout. Usage:
 *   <?= View::renderPartial('partials/account_menu', ['currentUser' => $currentUser]) ?>
 * Rendered from both the fixed desktop sidebar and the mobile drawer in
 * layouts/app.php, each with its own local Alpine scope.
 * @var array $currentUser
 */
$initial = mb_strtoupper(mb_substr($currentUser['name'] ?? '', 0, 1));
?>
<div class="mx-3 mb-3" x-data="{ open: false }">
    <button type="button" x-on:click="open = !open"
            class="w-full flex items-center gap-3 px-3 py-2.5 rounded-md bg-white/5 hover:bg-white/10 transition-colors">
        <div class="w-8 h-8 rounded-full bg-white/10 flex items-center justify-center text-on-primary text-sm font-semibold shrink-0">
            <?= View::e($initial) ?>
        </div>
        <div class="flex-1 min-w-0 text-left">
            <p class="text-body-md truncate"><?= View::e($currentUser['name']) ?></p>
            <p class="text-label-sm text-on-primary-container uppercase truncate"><?= View::e($currentUser['role_name'] ?? '') ?></p>
        </div>
    </button>

    <div x-show="open" x-cloak x-on:click.outside="open = false"
         class="mt-1 bg-surface-container-lowest border border-outline-variant rounded-lg shadow-[0_12px_24px_rgba(0,0,0,0.08)] overflow-hidden">
        <a href="<?= View::e(url('/account/password')) ?>" class="block px-4 py-2.5 text-sm text-on-surface hover:bg-surface-container-low">
            Change Password
        </a>
        <form method="post" action="<?= View::e(url('/logout')) ?>" x-data="{ loading: false }" x-on:submit="loading = true">
            <?= Csrf::field() ?>
            <button type="submit" :disabled="loading"
                    class="block w-full text-left px-4 py-2.5 text-sm text-error hover:bg-surface-container-low disabled:opacity-60 disabled:cursor-not-allowed">
                <span x-text="loading ? 'Logging out...' : 'Logout'"></span>
            </button>
        </form>
    </div>
</div>
