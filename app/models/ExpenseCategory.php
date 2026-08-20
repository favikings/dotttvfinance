<?php

declare(strict_types=1);

class ExpenseCategory
{
    public static function all(): array
    {
        $stmt = Database::connection()->query(
            'SELECT id, name, created_at FROM expense_categories ORDER BY name'
        );
        return $stmt->fetchAll();
    }

    public static function find(int $id): ?array
    {
        $stmt = Database::connection()->prepare(
            'SELECT id, name, created_at FROM expense_categories WHERE id = ? LIMIT 1'
        );
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    public static function exists(int $id): bool
    {
        $stmt = Database::connection()->prepare('SELECT COUNT(*) FROM expense_categories WHERE id = ?');
        $stmt->execute([$id]);
        return (int) $stmt->fetchColumn() > 0;
    }

    public static function nameExists(string $name, ?int $excludeId = null): bool
    {
        $sql = 'SELECT COUNT(*) FROM expense_categories WHERE name = ?';
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
        $stmt = Database::connection()->prepare('INSERT INTO expense_categories (name) VALUES (?)');
        $stmt->execute([$name]);
        return (int) Database::connection()->lastInsertId();
    }

    public static function update(int $id, string $name): void
    {
        $stmt = Database::connection()->prepare('UPDATE expense_categories SET name = ? WHERE id = ?');
        $stmt->execute([$name, $id]);
    }

    public static function delete(int $id): void
    {
        $stmt = Database::connection()->prepare('DELETE FROM expense_categories WHERE id = ?');
        $stmt->execute([$id]);
    }

    /** True when any expense references this category and would break on delete. */
    public static function inUse(int $id): bool
    {
        $stmt = Database::connection()->prepare('SELECT COUNT(*) FROM expenses WHERE category_id = ?');
        $stmt->execute([$id]);
        return (int) $stmt->fetchColumn() > 0;
    }
}
