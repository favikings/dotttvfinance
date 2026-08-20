<?php

declare(strict_types=1);

class Setting
{
    public static function get(string $key, mixed $default = null): mixed
    {
        $stmt = Database::connection()->prepare('SELECT `value` FROM settings WHERE `key` = ? LIMIT 1');
        $stmt->execute([$key]);
        $value = $stmt->fetchColumn();

        return $value === false ? $default : $value;
    }

    /** Upsert by the unique `key` column — used for admin-configurable values with no dedicated table (e.g. report budgets). */
    public static function set(string $key, string $value, ?int $updatedBy = null): void
    {
        $stmt = Database::connection()->prepare(
            'INSERT INTO settings (`key`, `value`, updated_by) VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE `value` = VALUES(`value`), updated_by = VALUES(updated_by)'
        );
        $stmt->execute([$key, $value, $updatedBy]);
    }

    /** All settings whose key starts with $prefix, as [key => value] — used for grouped config like per-department budgets. */
    public static function allByPrefix(string $prefix): array
    {
        $stmt = Database::connection()->prepare(
            "SELECT `key`, `value` FROM settings WHERE `key` LIKE CONCAT(?, '%')"
        );
        $stmt->execute([$prefix]);
        $map = [];
        foreach ($stmt->fetchAll() as $row) {
            $map[$row['key']] = $row['value'];
        }
        return $map;
    }
}
