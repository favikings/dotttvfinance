<?php

declare(strict_types=1);

class ApprovalRule
{
    public static function all(): array
    {
        $stmt = Database::connection()->query(
            'SELECT id, tier_order, min_amount, max_amount, required_roles, is_active
             FROM approval_rules ORDER BY tier_order'
        );
        return array_map([self::class, 'decorate'], $stmt->fetchAll());
    }

    public static function find(int $id): ?array
    {
        $stmt = Database::connection()->prepare(
            'SELECT id, tier_order, min_amount, max_amount, required_roles, is_active
             FROM approval_rules WHERE id = ? LIMIT 1'
        );
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row === false ? null : self::decorate($row);
    }

    /**
     * Decode the JSON roles column and normalize amounts to '0.00' strings
     * so every code path (forms, audit before/after, comparisons) sees the
     * same shape. DECIMAL comes back from mysqlnd as a string already.
     */
    private static function decorate(array $row): array
    {
        $row['required_roles'] = json_decode((string) ($row['required_roles'] ?? '[]'), true) ?: [];
        $row['min_amount'] = number_format((float) $row['min_amount'], 2, '.', '');
        $row['max_amount'] = $row['max_amount'] === null ? null : number_format((float) $row['max_amount'], 2, '.', '');

        return $row;
    }

    public static function insert(string $minAmount, ?string $maxAmount, array $requiredRoles, int $tierOrder): int
    {
        $stmt = Database::connection()->prepare(
            'INSERT INTO approval_rules (tier_order, min_amount, max_amount, required_roles, is_active)
             VALUES (?, ?, ?, ?, 1)'
        );
        $stmt->execute([$tierOrder, $minAmount, $maxAmount, json_encode(array_values($requiredRoles))]);
        return (int) Database::connection()->lastInsertId();
    }

    public static function update(int $id, string $minAmount, ?string $maxAmount, array $requiredRoles, int $tierOrder): void
    {
        $stmt = Database::connection()->prepare(
            'UPDATE approval_rules
             SET tier_order = ?, min_amount = ?, max_amount = ?, required_roles = ?
             WHERE id = ?'
        );
        $stmt->execute([$tierOrder, $minAmount, $maxAmount, json_encode(array_values($requiredRoles)), $id]);
    }

    public static function delete(int $id): void
    {
        $stmt = Database::connection()->prepare('DELETE FROM approval_rules WHERE id = ?');
        $stmt->execute([$id]);
    }

    /**
     * Tech Spec §7 step 1: the single active rule bracket containing $amount.
     * No gaps/overlaps are guaranteed by the Settings sanity checks, so at
     * most one row can match; ORDER BY tier_order keeps it deterministic.
     */
    public static function findActiveForAmount(string $amount): ?array
    {
        $stmt = Database::connection()->prepare(
            'SELECT id, tier_order, min_amount, max_amount, required_roles, is_active
             FROM approval_rules
             WHERE is_active = 1
               AND min_amount <= ?
               AND (max_amount IS NULL OR max_amount >= ?)
             ORDER BY tier_order
             LIMIT 1'
        );
        $stmt->execute([$amount, $amount]);
        $row = $stmt->fetch();
        return $row === false ? null : self::decorate($row);
    }
}
