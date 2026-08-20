<?php
/**
 * @var array<int, array> $rows
 * @var int $total
 * @var int $page
 * @var int $totalPages
 * @var array<int, string> $entityTypes
 * @var array<int, array> $users
 * @var bool $isGm
 * @var array{entity_type:string, date_from:string, date_to:string, user_id:int} $filters
 */
$db = Database::connection(); // audit_diff_view()'s resolve_audit_value() resolves FK ids (department_id, approved_by, ...) to names
?>
<div class="space-y-6">
    <div>
        <h1 class="text-headline-md">Audit Log</h1>
        <p class="text-body-md text-on-surface-variant mt-1">
            <?= $isGm
                ? 'Every action you have taken — hash-chained and tamper-evident.'
                : 'Every state-changing action in the system — hash-chained and tamper-evident.' ?>
        </p>
    </div>

    <form method="get" action="<?= View::e(url('/audit-log')) ?>"
          class="bg-surface-container-lowest rounded-lg border border-outline-variant p-4 flex flex-wrap items-end gap-3">
        <div>
            <label class="block text-label-md text-on-surface-variant mb-1.5" for="filter-entity-type">Entity type</label>
            <select id="filter-entity-type" name="entity_type"
                    class="px-3 py-2 rounded border border-outline bg-surface-container-lowest text-on-surface text-sm focus:outline-none focus:ring-2 focus:ring-secondary-container focus:border-transparent">
                <option value="">All entity types</option>
                <?php foreach ($entityTypes as $type): ?>
                    <option value="<?= View::e($type) ?>" <?= $filters['entity_type'] === $type ? 'selected' : '' ?>>
                        <?= View::e(ucwords(str_replace('_', ' ', $type))) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label class="block text-label-md text-on-surface-variant mb-1.5" for="filter-date-from">From</label>
            <input type="date" id="filter-date-from" name="date_from" value="<?= View::e($filters['date_from']) ?>"
                   class="px-3 py-2 rounded border border-outline bg-surface-container-lowest text-on-surface text-sm focus:outline-none focus:ring-2 focus:ring-secondary-container focus:border-transparent">
        </div>
        <div>
            <label class="block text-label-md text-on-surface-variant mb-1.5" for="filter-date-to">To</label>
            <input type="date" id="filter-date-to" name="date_to" value="<?= View::e($filters['date_to']) ?>"
                   class="px-3 py-2 rounded border border-outline bg-surface-container-lowest text-on-surface text-sm focus:outline-none focus:ring-2 focus:ring-secondary-container focus:border-transparent">
        </div>
        <?php if (!$isGm): ?>
            <div>
                <label class="block text-label-md text-on-surface-variant mb-1.5" for="filter-user">User</label>
                <select id="filter-user" name="user_id"
                        class="px-3 py-2 rounded border border-outline bg-surface-container-lowest text-on-surface text-sm focus:outline-none focus:ring-2 focus:ring-secondary-container focus:border-transparent">
                    <option value="0">All users</option>
                    <?php foreach ($users as $u): ?>
                        <option value="<?= (int) $u['id'] ?>" <?= $filters['user_id'] === (int) $u['id'] ? 'selected' : '' ?>>
                            <?= View::e($u['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        <?php endif; ?>
        <div class="flex gap-2">
            <button type="submit"
                    class="bg-secondary-container text-on-secondary-container font-medium text-sm px-4 py-2.5 rounded hover:opacity-90 transition-opacity">
                Filter
            </button>
            <a href="<?= View::e(url('/audit-log')) ?>"
               class="border border-outline text-on-surface font-medium text-sm px-4 py-2.5 rounded hover:bg-surface-container transition-colors">
                Clear
            </a>
        </div>
    </form>

    <div class="bg-surface-container-lowest rounded-lg border border-outline-variant overflow-hidden">
        <div class="overflow-x-auto table-scroll">
            <table class="w-full text-sm min-w-[700px]">
            <thead class="bg-surface-container-low border-b border-outline-variant">
                <tr>
                    <th class="text-left px-4 py-3 font-medium text-on-surface-variant whitespace-nowrap">When</th>
                    <th class="text-left px-4 py-3 font-medium text-on-surface-variant">User</th>
                    <th class="text-left px-4 py-3 font-medium text-on-surface-variant">Action</th>
                    <th class="text-left px-4 py-3 font-medium text-on-surface-variant">Entity</th>
                    <th class="text-left px-4 py-3 font-medium text-on-surface-variant">Before / After</th>
                    <th class="text-left px-4 py-3 font-medium text-on-surface-variant">Hash</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-outline-variant">
                <?php if (empty($rows)): ?>
                    <tr>
                        <td class="px-4 py-12 text-center text-on-surface-variant" colspan="6">
                            No audit log entries match these filters.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($rows as $row): ?>
                        <tr class="hover:bg-surface-container-low transition-colors align-top">
                            <td class="px-4 py-3 text-on-surface whitespace-nowrap"><?= View::e(date('d/m/Y H:i:s', strtotime($row['created_at']))) ?></td>
                            <td class="px-4 py-3 text-on-surface whitespace-nowrap"><?= View::e($row['user_name'] ?? 'System') ?></td>
                            <td class="px-4 py-3 text-on-surface whitespace-nowrap"><?= View::e(ucwords(str_replace('_', ' ', $row['action']))) ?></td>
                            <td class="px-4 py-3 text-on-surface-variant whitespace-nowrap">
                                <?= View::e(ucwords(str_replace('_', ' ', $row['entity_type']))) ?><?= $row['entity_id'] !== null ? ' #' . (int) $row['entity_id'] : '' ?>
                            </td>
                            <td class="px-4 py-3 text-on-surface-variant">
                                <?php if ($row['before_json'] === null && $row['after_json'] === null): ?>
                                    <span>—</span>
                                <?php else: ?>
                                    <details>
                                        <summary class="cursor-pointer text-secondary hover:underline">View details</summary>
                                        <!-- UI Component Guide §8b: audit_diff_view() is the primary content
                                             (only fields that actually changed, old -> new); the full
                                             before/after JSON stays available underneath, collapsed, since
                                             exact values matter for compliance and should never be fully
                                             hidden. -->
                                        <div class="mt-2 max-w-md" x-data="{ showRaw: false }">
                                            <?= audit_diff_view($db, $row['before_json'], $row['after_json']) ?>

                                            <button type="button" x-on:click="showRaw = !showRaw"
                                                    class="text-xs text-outline mt-3 hover:text-on-surface">
                                                <span x-show="!showRaw">Show raw JSON</span>
                                                <span x-show="showRaw" x-cloak>Hide raw JSON</span>
                                            </button>
                                            <pre x-show="showRaw" x-cloak class="mt-2 p-3 bg-surface-container rounded text-xs overflow-x-auto"><?= View::e((string) json_encode([
                                                'before' => json_decode($row['before_json'] ?? 'null'),
                                                'after'  => json_decode($row['after_json'] ?? 'null'),
                                            ], JSON_PRETTY_PRINT)) ?></pre>
                                        </div>
                                    </details>
                                <?php endif; ?>
                            </td>
                            <td class="px-4 py-3 text-on-surface-variant font-mono text-xs whitespace-nowrap" title="<?= View::e($row['row_hash']) ?>">
                                <?= View::e(substr($row['row_hash'], 0, 10)) ?>&hellip;
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
        </div>
    </div>

    <?php if ($totalPages > 1): ?>
        <div class="flex items-center justify-between gap-4">
            <p class="text-label-md text-on-surface-variant">
                Page <?= (int) $page ?> of <?= (int) $totalPages ?> (<?= (int) $total ?> entries)
            </p>
            <div class="flex gap-2">
                <?php
                    $baseParams = array_filter([
                        'entity_type' => $filters['entity_type'],
                        'date_from'   => $filters['date_from'],
                        'date_to'     => $filters['date_to'],
                        'user_id'     => $filters['user_id'] > 0 ? $filters['user_id'] : null,
                    ], static fn ($v) => $v !== null && $v !== '');
                ?>
                <?php if ($page > 1): ?>
                    <a href="<?= View::e(url('/audit-log') . '?' . http_build_query($baseParams + ['page' => $page - 1])) ?>"
                       class="border border-outline text-on-surface font-medium text-sm px-4 py-2.5 rounded hover:bg-surface-container transition-colors">
                        Previous
                    </a>
                <?php endif; ?>
                <?php if ($page < $totalPages): ?>
                    <a href="<?= View::e(url('/audit-log') . '?' . http_build_query($baseParams + ['page' => $page + 1])) ?>"
                       class="border border-outline text-on-surface font-medium text-sm px-4 py-2.5 rounded hover:bg-surface-container transition-colors">
                        Next
                    </a>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>
</div>
