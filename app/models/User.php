<?php

declare(strict_types=1);

class User
{
    public static function all(): array
    {
        $stmt = Database::connection()->query(
            'SELECT u.id, u.name, u.email, u.role_id, u.department_id, u.status, u.last_login_at,
                    r.name AS role_name, d.name AS department_name
             FROM users u
             LEFT JOIN roles r ON r.id = u.role_id
             LEFT JOIN departments d ON d.id = u.department_id
             ORDER BY u.name'
        );
        return $stmt->fetchAll();
    }

    public static function find(int $id): ?array
    {
        $stmt = Database::connection()->prepare(
            'SELECT u.id, u.name, u.email, u.role_id, u.department_id, u.status, u.last_login_at,
                    r.name AS role_name, d.name AS department_name
             FROM users u
             LEFT JOIN roles r ON r.id = u.role_id
             LEFT JOIN departments d ON d.id = u.department_id
             WHERE u.id = ? LIMIT 1'
        );
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    /** Active users holding a given role — used to notify all GMs/Chairmen (Tech Spec §15). */
    public static function activeByRole(string $roleName): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT u.id, u.name, u.email
             FROM users u
             JOIN roles r ON r.id = u.role_id
             WHERE r.name = ? AND u.status = ?'
        );
        $stmt->execute([$roleName, 'active']);
        return $stmt->fetchAll();
    }

    public static function emailExists(string $email, ?int $excludeId = null): bool
    {
        $sql = 'SELECT COUNT(*) FROM users WHERE email = ?';
        $params = [$email];
        if ($excludeId !== null) {
            $sql .= ' AND id != ?';
            $params[] = $excludeId;
        }
        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);
        return (int) $stmt->fetchColumn() > 0;
    }

    public static function create(array $data): int
    {
        $stmt = Database::connection()->prepare(
            'INSERT INTO users (name, email, role_id, department_id, password_hash, status)
             VALUES (?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $data['name'],
            $data['email'],
            $data['role_id'],
            $data['department_id'],
            $data['password_hash'],
            $data['status'] ?? 'active',
        ]);
        return (int) Database::connection()->lastInsertId();
    }

    /** $data keys: name, email, role_id, department_id, status, password_hash (optional — omitted keeps current). */
    public static function update(int $id, array $data): void
    {
        $sql = 'UPDATE users SET name = ?, email = ?, role_id = ?, department_id = ?, status = ?';
        $params = [
            $data['name'],
            $data['email'],
            $data['role_id'],
            $data['department_id'],
            $data['status'],
        ];
        if (!empty($data['password_hash'])) {
            $sql .= ', password_hash = ?';
            $params[] = $data['password_hash'];
        }
        $sql .= ' WHERE id = ?';
        $params[] = $id;

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);
    }

    public static function delete(int $id): void
    {
        $stmt = Database::connection()->prepare('DELETE FROM users WHERE id = ?');
        $stmt->execute([$id]);
    }

    /**
     * True when deleting this user would either violate an FK or silently
     * orphan financial/audit history. Deletion should then be refused in
     * favor of deactivating (status='inactive').
     */
    public static function hasRelatedRecords(int $id): bool
    {
        $checks = [
            'SELECT COUNT(*) FROM expenses WHERE created_by = ?',
            'SELECT COUNT(*) FROM invoices WHERE created_by = ?',
            'SELECT COUNT(*) FROM payment_vouchers WHERE paid_by = ?',
            'SELECT COUNT(*) FROM fund_topups WHERE requested_by = ? OR approved_by = ?',
            'SELECT COUNT(*) FROM payroll_runs WHERE created_by = ? OR approved_by = ?',
            'SELECT COUNT(*) FROM audit_log WHERE user_id = ?',
            'SELECT COUNT(*) FROM settings WHERE updated_by = ?',
        ];
        foreach ($checks as $sql) {
            $placeholders = substr_count($sql, '?');
            $stmt = Database::connection()->prepare($sql);
            $stmt->execute(array_fill(0, $placeholders, $id));
            if ((int) $stmt->fetchColumn() > 0) {
                return true;
            }
        }
        return false;
    }
}
