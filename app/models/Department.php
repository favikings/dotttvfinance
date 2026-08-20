<?php

declare(strict_types=1);

class Department
{
    public static function all(): array
    {
        $stmt = Database::connection()->query(
            'SELECT id, name, created_at FROM departments ORDER BY name'
        );
        return $stmt->fetchAll();
    }

    public static function find(int $id): ?array
    {
        $stmt = Database::connection()->prepare(
            'SELECT id, name, created_at FROM departments WHERE id = ? LIMIT 1'
        );
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    public static function exists(int $id): bool
    {
        $stmt = Database::connection()->prepare('SELECT COUNT(*) FROM departments WHERE id = ?');
        $stmt->execute([$id]);
        return (int) $stmt->fetchColumn() > 0;
    }

    public static function nameExists(string $name, ?int $excludeId = null): bool
    {
        $sql = 'SELECT COUNT(*) FROM departments WHERE name = ?';
        $params = [$name];
        if ($excludeId !== null) {
            $sql .= ' AND id != ?';
            $params[] = $excludeId;
        }
        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);
        return (int) $stmt->fetchColumn() > 0;
    }

    public static function create(string $name): int
    {
        $stmt = Database::connection()->prepare('INSERT INTO departments (name) VALUES (?)');
        $stmt->execute([$name]);
        return (int) Database::connection()->lastInsertId();
    }

    public static function update(int $id, string $name): void
    {
        $stmt = Database::connection()->prepare('UPDATE departments SET name = ? WHERE id = ?');
        $stmt->execute([$name, $id]);
    }

    public static function delete(int $id): void
    {
        $stmt = Database::connection()->prepare('DELETE FROM departments WHERE id = ?');
        $stmt->execute([$id]);
    }

    /** True when any table that references departments would break on delete. */
    public static function inUse(int $id): bool
    {
        $checks = [
            'SELECT COUNT(*) FROM expenses WHERE department_id = ?',
            'SELECT COUNT(*) FROM invoices WHERE department_id = ?',
            'SELECT COUNT(*) FROM users WHERE department_id = ?',
            'SELECT COUNT(*) FROM payroll_items WHERE department_id = ?',
        ];
        foreach ($checks as $sql) {
            $stmt = Database::connection()->prepare($sql);
            $stmt->execute([$id]);
            if ((int) $stmt->fetchColumn() > 0) {
                return true;
            }
        }
        return false;
    }
}
