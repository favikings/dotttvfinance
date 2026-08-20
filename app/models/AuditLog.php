<?php

declare(strict_types=1);

/**
 * Read-side queries for the append-only audit_log table (schema.sql §9,
 * Tech Spec §11). Nothing here ever writes — AuditLogger::record() owns
 * every insert, and there is deliberately no update()/delete() on this
 * model, matching the "no update/delete permission on audit_log for any
 * role" rule (PRD §3.2 notes).
 */
class AuditLog
{
    private const PER_PAGE = 50;

    /**
     * @param array{entity_type?: ?string, date_from?: ?string, date_to?: ?string,
     *              user_id?: ?int, restrict_user_id?: ?int, page?: int} $filters
     * @return array{rows: array<int, array>, total: int, page: int, perPage: int, totalPages: int}
     */
    public static function search(array $filters): array
    {
        $where = [];
        $params = [];

        if (!empty($filters['entity_type'])) {
            $where[] = 'a.entity_type = ?';
            $params[] = $filters['entity_type'];
        }
        if (!empty($filters['date_from'])) {
            $where[] = 'a.created_at >= ?';
            $params[] = $filters['date_from'] . ' 00:00:00';
        }
        if (!empty($filters['date_to'])) {
            $where[] = 'a.created_at <= ?';
            $params[] = $filters['date_to'] . ' 23:59:59';
        }
        // GM restriction (PRD §3.2: "own approvals only") takes precedence
        // over — and is never loosened by — the general user filter below.
        if (!empty($filters['restrict_user_id'])) {
            $where[] = 'a.user_id = ?';
            $params[] = $filters['restrict_user_id'];
        } elseif (!empty($filters['user_id'])) {
            $where[] = 'a.user_id = ?';
            $params[] = $filters['user_id'];
        }

        $whereSql = $where === [] ? '' : ('WHERE ' . implode(' AND ', $where));

        $countStmt = Database::connection()->prepare(
            "SELECT COUNT(*) FROM audit_log a {$whereSql}"
        );
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        $perPage = self::PER_PAGE;
        $totalPages = max(1, (int) ceil($total / $perPage));
        $page = max(1, min((int) ($filters['page'] ?? 1), $totalPages));
        $offset = ($page - 1) * $perPage;

        $stmt = Database::connection()->prepare(
            "SELECT a.id, a.user_id, a.action, a.entity_type, a.entity_id,
                    a.before_json, a.after_json, a.ip_address,
                    a.prev_hash, a.row_hash, a.created_at,
                    u.name AS user_name
             FROM audit_log a
             LEFT JOIN users u ON u.id = a.user_id
             {$whereSql}
             ORDER BY a.id DESC
             LIMIT {$perPage} OFFSET {$offset}"
        );
        $stmt->execute($params);

        return [
            'rows'       => $stmt->fetchAll(),
            'total'      => $total,
            'page'       => $page,
            'perPage'    => $perPage,
            'totalPages' => $totalPages,
        ];
    }

    /** Distinct entity types actually present in the table, for the filter dropdown. */
    public static function distinctEntityTypes(): array
    {
        $stmt = Database::connection()->query(
            'SELECT DISTINCT entity_type FROM audit_log ORDER BY entity_type ASC'
        );
        return array_column($stmt->fetchAll(), 'entity_type');
    }

    /** Every row in id order — the shape the verifier needs to walk the chain. */
    public static function allInOrder(): array
    {
        $stmt = Database::connection()->query(
            'SELECT id, user_id, action, entity_type, entity_id,
                    before_json, after_json, prev_hash, row_hash, created_at
             FROM audit_log
             ORDER BY id ASC'
        );
        return $stmt->fetchAll();
    }
}
