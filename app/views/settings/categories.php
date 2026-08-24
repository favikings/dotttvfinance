<?php /** @var array<int, array> $categories @var ?array $editCategory */ ?>
<div class="space-y-6">
    <div>
        <h1 class="text-headline-md">Settings</h1>
        <p class="text-body-md text-on-surface-variant mt-1">Departments, categories, approval rules, and users.</p>
    </div>

    <?= settings_tabs('categories') ?>
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
                    <?php if (empty($categories)): ?>
                        <tr>
                            <td class="px-4 py-12 text-center text-on-surface-variant" colspan="2">
                                No expense categories yet — add the first one.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($categories as $category): ?>
                            <tr class="hover:bg-surface-container-low transition-colors">
                                <td class="px-4 py-3 text-on-surface"><?= View::e($category['name']) ?></td>
                                <td class="px-4 py-3">
                                    <div class="flex items-center justify-end gap-4">
                                        <a href="<?= View::e(url('/settings/categories?edit=' . (int) $category['id'])) ?>"
                                           class="text-sm font-medium text-on-secondary hover:underline">Edit</a>
                                        <form method="post" action="<?= View::e(url('/settings/categories/delete')) ?>"
                                              class="inline" onsubmit="return dottConfirmSubmit(this, 'Delete this category?', 'This cannot be undone.', 'Delete', true);">
                                            <?= Csrf::field() ?>
                                            <input type="hidden" name="id" value="<?= (int) $category['id'] ?>">
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
            <?php if ($editCategory !== null): ?>
                <h2 class="text-headline-sm mb-1">Edit category</h2>
                <p class="text-body-md text-on-surface-variant mb-4">Rename "<?= View::e($editCategory['name']) ?>".</p>
                <form method="post" action="<?= View::e(url('/settings/categories/update')) ?>"
                      x-data="{ loading: false }" x-on:submit="loading = true">
                    <?= Csrf::field() ?>
                    <input type="hidden" name="id" value="<?= (int) $editCategory['id'] ?>">
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-on-surface mb-1.5" for="cat-name">Name</label>
                        <input type="text" id="cat-name" name="name" value="<?= View::e($editCategory['name']) ?>"
                               required class="w-full px-3 py-2.5 rounded border border-outline bg-surface-container-lowest text-on-surface text-sm focus:outline-none focus:ring-2 focus:ring-secondary-container focus:border-transparent">
                    </div>
                    <div class="flex gap-3">
                        <button type="submit" :disabled="loading"
                                class="bg-secondary-container text-on-secondary-container font-medium text-sm px-4 py-2.5 rounded hover:opacity-90 transition-opacity disabled:opacity-60 disabled:cursor-not-allowed flex items-center justify-center gap-2">
                            <svg x-show="loading" x-cloak class="animate-spin w-4 h-4" viewBox="0 0 24 24" fill="none">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                            </svg>
                            <span x-text="loading ? 'Saving...' : 'Save changes'"></span>
                        </button>
                        <a href="<?= View::e(url('/settings/categories')) ?>"
                           class="border border-outline text-on-surface font-medium text-sm px-4 py-2.5 rounded hover:bg-surface-container transition-colors">
                            Cancel
                        </a>
                    </div>
                </form>
            <?php else: ?>
                <h2 class="text-headline-sm mb-1">Add category</h2>
                <p class="text-body-md text-on-surface-variant mb-4">Categories classify what expenses were for.</p>
                <form method="post" action="<?= View::e(url('/settings/categories/create')) ?>"
                      x-data="{ loading: false }" x-on:submit="loading = true">
                    <?= Csrf::field() ?>
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-on-surface mb-1.5" for="cat-name">Name</label>
                        <input type="text" id="cat-name" name="name" placeholder="e.g. Fuel & transport"
                               required class="w-full px-3 py-2.5 rounded border border-outline bg-surface-container-lowest text-on-surface text-sm focus:outline-none focus:ring-2 focus:ring-secondary-container focus:border-transparent">
                    </div>
                    <button type="submit" :disabled="loading"
                            class="bg-secondary-container text-on-secondary-container font-medium text-sm px-4 py-2.5 rounded hover:opacity-90 transition-opacity disabled:opacity-60 disabled:cursor-not-allowed flex items-center justify-center gap-2">
                        <svg x-show="loading" x-cloak class="animate-spin w-4 h-4" viewBox="0 0 24 24" fill="none">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                        </svg>
                        <span x-text="loading ? 'Adding...' : 'Add category'"></span>
                    </button>
                </form>
            <?php endif; ?>
        </div>
    </div>
</div>
