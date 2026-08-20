<?php

declare(strict_types=1);

/**
 * Phase 1 dashboard queries (Build Prompt 1.6). Kept intentionally thin —
 * the balance KPIs come from FundAccount::allWithBalances() (which reads the
 * fund_balances VIEW, never a stored number — CLAUDE.md rule 4); this model
 * only owns the cross-table recent-activity feed.
 */
class Dashboard
{
    /**
     * Latest expenses + top-ups interleaved by date, most recent first.
     * A UNION (not two queries merged in PHP) so the interleaving happens in
     * SQL on the DATE column itself — both tables' date columns are genuine
     * ORDER BY keys, avoiding the drift that comes from chopping up two list
     * queries and merging them in code.
     *
     * Column order must line up across both branches; each row is normalized
     * to a single shape the view renders (type discriminator, primary label,
     * secondary note, amount, status, provenance flag).
     *
     * @return array<int, array{type: string, id: int, date: string, expense_no: ?string,
     *                        payee: ?string, reference: ?string, note: ?string,
     *                        is_historical: int, status: string, amount: string}>
     */
    public static function recentActivity(int $limit = 10): array
    {
        $stmt = Database::connection()->prepare(
            "SELECT * FROM (
                SELECT
                    'topup'   AS type,
                    t.id      AS id,
                    t.date    AS date,
                    NULL      AS expense_no,
                    NULL      AS payee,
                    t.reference AS reference,
                    t.note    AS note,
                    t.is_historical AS is_historical,
                    t.status  AS status,
                    t.amount  AS amount
                FROM fund_topups t
                UNION ALL
                SELECT
                    'expense' AS type,
                    e.id      AS id,
                    e.date    AS date,
                    e.expense_no AS expense_no,
                    e.payee   AS payee,
                    NULL      AS reference,
                    e.description AS note,
                    e.is_historical AS is_historical,
                    e.status  AS status,
                    e.amount  AS amount
                FROM expenses e
            ) activity
            ORDER BY date DESC, id DESC
            LIMIT ?"
        );
        $stmt->execute([$limit]);
        return $stmt->fetchAll();
    }
}