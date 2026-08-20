<?php

declare(strict_types=1);

class Role
{
    public static function all(): array
    {
        $stmt = Database::connection()->query('SELECT id, name FROM roles ORDER BY id');
        return $stmt->fetchAll();
    }

    public static function find(int $id): ?array
    {
        $stmt = Database::connection()->prepare('SELECT id, name FROM roles WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    public static function exists(int $id): bool
    {
        $stmt = Database::connection()->prepare('SELECT COUNT(*) FROM roles WHERE id = ?');
        $stmt->execute([$id]);
        return (int) $stmt->fetchColumn() > 0;
    }
}
