<?php

declare(strict_types=1);

/**
 * Hash-chained audit writer per Tech Spec §11 and schema.sql §9.
 *
 * MUST be called from inside the SAME open DB transaction as the state
 * change it logs (the controller opens the transaction, does the change,
 * calls record(), then commits) — if the audit write fails, the whole
 * transaction rolls back. A financial action that isn't logged is treated
 * as an action that didn't happen. record() deliberately does NOT open or
 * commit a transaction itself: the caller owns that lifecycle, which is
 * what lets a single transaction cover both the change and its audit row.
 *
 * Hash convention (locked in here for the Prompt 2.5 verification script):
 *   row_hash = sha256(implode('|', [user_id, action, entity_type,
 *              entity_id, before_json, after_json, created_at_iso,
 *              prev_hash]))
 * where before_json/after_json are the raw JSON strings (or '' when the
 * column is NULL), created_at_iso is the exact 'Y-m-d H:i:s' string this
 * class inserts (so the verifier recomputing against the stored value
 * reproduces the same hash), and prev_hash for the very first row ever is
 * a genesis string of 64 zeros.
 */
class AuditLogger
{
    public const GENESIS_HASH = '0000000000000000000000000000000000000000000000000000000000000000';

    /**
     * @param array<int|string, mixed> $before pre-change values (may be empty)
     * @param array<int|string, mixed> $after  post-change values (may be empty)
     */
    public static function record(?int $userId, string $action, string $entityType, ?int $entityId, array $before = [], array $after = []): void
    {
        $pdo = Database::connection();

        // FOR UPDATE on the latest row serializes concurrent appenders: a
        // second writer blocks until the first commits, then reads the new
        // last row_hash — so two children can never claim the same parent.
        $stmt = $pdo->prepare('SELECT row_hash FROM audit_log ORDER BY id DESC LIMIT 1 FOR UPDATE');
        $stmt->execute();
        $prevHash = $stmt->fetchColumn();
        $prevHash = ($prevHash === false || $prevHash === null) ? self::GENESIS_HASH : (string) $prevHash;

        // Africa/Lagos is the app timezone (config.php); inserting the exact
        // string we hash keeps the chain verifiable against stored data.
        $createdAt = date('Y-m-d H:i:s');

        $beforeJson = $before === [] ? null : json_encode($before, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $afterJson  = $after  === [] ? null : json_encode($after,  JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $rowHash = hash('sha256', implode('|', [
            (string) ($userId ?? ''),
            $action,
            $entityType,
            (string) ($entityId ?? ''),
            $beforeJson ?? '',
            $afterJson ?? '',
            $createdAt,
            $prevHash,
        ]));

        $insert = $pdo->prepare(
            'INSERT INTO audit_log
                (user_id, action, entity_type, entity_id, before_json, after_json, ip_address, prev_hash, row_hash, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $insert->execute([
            $userId,
            $action,
            $entityType,
            $entityId,
            $beforeJson,
            $afterJson,
            $_SERVER['REMOTE_ADDR'] ?? null,
            $prevHash,
            $rowHash,
            $createdAt,
        ]);
    }
}
