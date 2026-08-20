<?php

declare(strict_types=1);

class FundTopup
{
    /** List view — joined with the labels the UI needs so views never run their own queries. */
    public static function all(): array
    {
        $stmt = Database::connection()->query(
            'SELECT t.id, t.amount, t.date, t.status, t.approved_at, t.rejected_reason,
                    t.reference, t.note, t.is_historical, t.created_at,
                    fa.name AS fund_account_name,
                    req.name AS requested_by_name,
                    app.name AS approved_by_name
             FROM fund_topups t
             LEFT JOIN fund_account fa ON fa.id = t.fund_account_id
             LEFT JOIN users req ON req.id = t.requested_by
             LEFT JOIN users app ON app.id = t.approved_by
             ORDER BY t.date DESC, t.id DESC'
        );
        return $stmt->fetchAll();
    }

    /**
     * Live top-up request insert (Tech Spec §8 step 1): 'pending', awaiting
     * either a GM or a Chairman sign-off — distinct from createHistorical(),
     * which skips this entirely and inserts already 'approved'.
     *
     * @param array{fund_account_id: int, amount: string, date: string, requested_by: int,
     *              reference: ?string, note: ?string} $data
     */
    public static function create(array $data): int
    {
        $stmt = Database::connection()->prepare(
            'INSERT INTO fund_topups
                (fund_account_id, amount, date, requested_by, status, reference, note, is_historical)
             VALUES (?, ?, ?, ?, ?, ?, ?, 0)'
        );
        $stmt->execute([
            $data['fund_account_id'],
            $data['amount'],
            $data['date'],
            $data['requested_by'],
            TopupStatus::Pending->value,
            $data['reference'],
            $data['note'],
        ]);
        return (int) Database::connection()->lastInsertId();
    }

    /**
     * The single pending request a GM/Chairman is about to action. FOR UPDATE
     * serializes two approvers racing the same request (Tech Spec §8 "whoever
     * gets there first"): the second blocks until the first commits, then
     * re-reads the now-decided row and the WHERE clause stops matching.
     */
    public static function lockPending(int $id): ?array
    {
        $stmt = Database::connection()->prepare(
            'SELECT id, amount, date, status
             FROM fund_topups
             WHERE id = ? AND status = ?
             LIMIT 1
             FOR UPDATE'
        );
        $stmt->execute([$id, TopupStatus::Pending->value]);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    /** Tech Spec §8 step 3: single sign-off, whoever actioned it. */
    public static function approve(int $id, int $approverId): void
    {
        $stmt = Database::connection()->prepare(
            'UPDATE fund_topups SET status = ?, approved_by = ?, approved_at = NOW() WHERE id = ?'
        );
        $stmt->execute([TopupStatus::Approved->value, $approverId, $id]);
    }

    /** Tech Spec §8 step 4: rejection with a required reason. */
    public static function reject(int $id, string $reason): void
    {
        $stmt = Database::connection()->prepare(
            'UPDATE fund_topups SET status = ?, rejected_reason = ? WHERE id = ?'
        );
        $stmt->execute([TopupStatus::Rejected->value, $reason, $id]);
    }

    public static function find(int $id): ?array
    {
        $stmt = Database::connection()->prepare(
            'SELECT t.id, t.fund_account_id, t.amount, t.date, t.requested_by, t.status,
                    t.approved_by, t.approved_at, t.rejected_reason, t.reference, t.note,
                    t.is_historical, t.created_at,
                    fa.name AS fund_account_name,
                    u.name AS requested_by_name,
                    u.email AS requested_by_email
             FROM fund_topups t
             LEFT JOIN fund_account fa ON fa.id = t.fund_account_id
             LEFT JOIN users u ON u.id = t.requested_by
             WHERE t.id = ? LIMIT 1'
        );
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    /**
     * Backfilled top-up insert (Tech Spec §13 / PRD §5): inserts directly as
     * approved, skipping the GM/Chairman sign-off from Tech Spec §8 — this
     * is a record of money that already came in, not a request awaiting
     * approval, so approved_by/approved_at stay NULL (no one is actioning it
     * right now).
     *
     * @param array{fund_account_id: int, amount: string, date: string, requested_by: int,
     *              reference: ?string, note: ?string} $data
     */
    public static function createHistorical(array $data): int
    {
        $stmt = Database::connection()->prepare(
            'INSERT INTO fund_topups
                (fund_account_id, amount, date, requested_by, status, reference, note, is_historical)
             VALUES (?, ?, ?, ?, ?, ?, ?, 1)'
        );
        $stmt->execute([
            $data['fund_account_id'],
            $data['amount'],
            $data['date'],
            $data['requested_by'],
            TopupStatus::Approved->value,
            $data['reference'],
            $data['note'],
        ]);
        return (int) Database::connection()->lastInsertId();
    }
}
