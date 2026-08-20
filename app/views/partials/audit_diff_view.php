<?php

declare(strict_types=1);

// UI Component Guide §8b — field-level diff for the audit log's "View
// details" expansion: before_json/after_json are full snapshots, not
// changesets, so most keys are identical between the two and only a
// handful actually differ per action. Showing the raw dump made every
// row look equally busy regardless of what actually changed; this shows
// only the fields where old !== new, as old -(struck through)-> new, with
// foreign-key ids resolved to the names they actually mean (a bare
// "approved_by: 13" tells a GM/Chairman nothing without a name).
//
// Deviates from the guide's literal example: the "new value" pill uses
// this project's own success/success-container tokens (design-system-
// dotttv.md) instead of the guide's raw bg-emerald-100/text-emerald-700,
// for the same reason status_badge() already deviates — emerald isn't a
// named design-system token and would violate guide §10's "no arbitrary
// Tailwind color" rule.
//
// Also extends the guide's literal (string) cast (both in the old
// audit_diff_view() and in resolve_audit_value()'s "not a known FK"/
// fallback branches) to handle array values (e.g. approval_rules'
// required_roles) and booleans, which stringify to "Array" / "1"
// respectively if cast bare — neither is what the intended audit reader
// needs to see.
if (!function_exists('audit_format_scalar')) {
    /** Null return means "no value" (renders as the em-dash placeholder). */
    function audit_format_scalar(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (is_bool($value)) {
            return $value ? 'Yes' : 'No';
        }
        if (is_array($value)) {
            return $value === [] ? null : implode(', ', array_map('strval', $value));
        }
        return (string) $value;
    }
}

if (!function_exists('resolve_audit_value')) {
    /**
     * Resolves a raw audit field value to something readable: a foreign-key
     * id (department_id, role_id, approved_by, ...) becomes the referenced
     * record's name (falling back to the raw id if that record was since
     * deleted, or if the value isn't actually an id — audit_log rows are
     * never re-validated against current schema); anything else (amounts,
     * dates, free text, arrays of role names) passes through unchanged.
     *
     * Per-request cached (static $cache) so the same id resolved for both
     * an old and a new value, or across multiple rows referencing the same
     * user/department, only hits the DB once per id.
     */
    function resolve_audit_value(PDO $db, string $key, mixed $value): string
    {
        static $cache = [];

        $formatted = audit_format_scalar($value);
        if ($formatted === null) {
            return '<span class="text-outline italic">—</span>';
        }

        // Fixed internal whitelist only — table/column names here are never derived
        // from user input, so string interpolation into SQL below is safe. Only the
        // id VALUE is a bound parameter.
        $fkMap = [
            'department_id'    => ['departments', 'name'],
            'category_id'      => ['expense_categories', 'name'],
            'fund_account_id'  => ['fund_account', 'name'],
            'role_id'          => ['roles', 'name'],
            'required_role_id' => ['roles', 'name'],
        ];

        $isUserRef = str_ends_with($key, '_by') || $key === 'approver_id';

        if ($isUserRef) {
            [$table, $column] = ['users', 'name'];
        } elseif (isset($fkMap[$key])) {
            [$table, $column] = $fkMap[$key];
        } else {
            return htmlspecialchars($formatted, ENT_QUOTES, 'UTF-8'); // not a known FK — show as-is
        }

        // A recognized FK key name is still only actually a lookup-able id when
        // the value itself is one (arrays like required_roles never hit this,
        // since that key isn't in $fkMap, but guard defensively regardless).
        if (!is_int($value) && !(is_string($value) && ctype_digit($value))) {
            return htmlspecialchars($formatted, ENT_QUOTES, 'UTF-8');
        }

        $cacheKey = "{$table}:{$value}";
        if (!array_key_exists($cacheKey, $cache)) {
            $stmt = $db->prepare("SELECT {$column} FROM {$table} WHERE id = :id LIMIT 1");
            $stmt->execute(['id' => $value]);
            $cache[$cacheKey] = $stmt->fetchColumn() ?: null;
        }

        $resolved = $cache[$cacheKey];
        return $resolved
            ? htmlspecialchars((string) $resolved, ENT_QUOTES, 'UTF-8') . ' <span class="text-outline text-xs">(#' . htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8') . ')</span>'
            : htmlspecialchars($formatted, ENT_QUOTES, 'UTF-8'); // fallback if the referenced record was deleted or id not found
    }
}

if (!function_exists('audit_diff_view')) {
    function audit_diff_view(PDO $db, ?string $beforeJson, ?string $afterJson): string
    {
        $before = $beforeJson !== null ? json_decode($beforeJson, true) : [];
        $after  = $afterJson  !== null ? json_decode($afterJson, true)  : [];
        $keys = array_unique(array_merge(array_keys($before ?? []), array_keys($after ?? [])));

        $spriteUrl = url('/assets/icons/sprite.svg');

        $rows = '';
        foreach ($keys as $key) {
            $oldVal = $before[$key] ?? null;
            $newVal = $after[$key] ?? null;
            if ($oldVal === $newVal) {
                continue; // only show fields that actually changed
            }

            $label = htmlspecialchars(ucwords(str_replace('_', ' ', (string) $key)), ENT_QUOTES, 'UTF-8');
            $oldDisplay = resolve_audit_value($db, (string) $key, $oldVal);
            $newDisplay = resolve_audit_value($db, (string) $key, $newVal);

            $rows .= <<<HTML
            <div class="flex items-center justify-between py-2.5 border-b border-outline-variant last:border-0">
              <span class="text-sm font-medium text-on-surface-variant">{$label}</span>
              <div class="flex items-center gap-2 text-sm">
                <span class="bg-error-container text-on-error-container px-2 py-0.5 rounded line-through">{$oldDisplay}</span>
                <svg class="w-3.5 h-3.5 text-outline" stroke="currentColor" fill="none"><use href="{$spriteUrl}#arrow-right"></use></svg>
                <span class="bg-success-container text-success px-2 py-0.5 rounded font-medium">{$newDisplay}</span>
              </div>
            </div>
            HTML;
        }

        if ($rows === '') {
            return '<p class="text-sm text-on-surface-variant italic">No field changes recorded.</p>';
        }

        return '<div class="divide-y divide-outline-variant">' . $rows . '</div>';
    }
}
