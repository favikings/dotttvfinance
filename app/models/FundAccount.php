<?php

declare(strict_types=1);

/**
 * Read-only in v1 (resolved single-float decision, PRD §10.2). Balance is
 * never a stored number — always the fund_balances view (CLAUDE.md rule 4).
 */
class FundAccount
{
    /**
     * $from/$to scope only the total_topups/total_spent flow figures to a
     * date range (via Report::periodTotals(), CLAUDE.md rule 4's "equivalent
     * date-filtered query") — current_balance always comes from the
     * fund_balances view as-is, since it's a live snapshot, not a
     * period-scoped flow figure (dashboard's Current Balance card never
     * takes a period filter).
     */
    public static function allWithBalances(?string $from = null, ?string $to = null): array
    {
        $stmt = Database::connection()->query(
            'SELECT fa.id, fa.name, fa.is_active, fa.created_at,
                    COALESCE(fb.total_topups, 0.00) AS total_topups,
                    COALESCE(fb.total_spent, 0.00) AS total_spent,
                    COALESCE(fb.current_balance, 0.00) AS current_balance
             FROM fund_account fa
             LEFT JOIN fund_balances fb ON fb.fund_account_id = fa.id
             ORDER BY fa.name'
        );
        $accounts = $stmt->fetchAll();

        if ($from === null || $to === null) {
            return $accounts;
        }

        foreach ($accounts as &$account) {
            $totals = Report::periodTotals((int) $account['id'], $from, $to);
            $account['total_topups'] = $totals['total_topups'];
            $account['total_spent'] = $totals['total_spent'];
        }
        unset($account);

        return $accounts;
    }

    /** Active accounts for dropdowns (v1 has a single "Main Operating Float"). */
    public static function active(): array
    {
        $stmt = Database::connection()->query(
            'SELECT id, name FROM fund_account WHERE is_active = 1 ORDER BY name'
        );
        return $stmt->fetchAll();
    }

    public static function isActive(int $id): bool
    {
        $stmt = Database::connection()->prepare(
            'SELECT COUNT(*) FROM fund_account WHERE id = ? AND is_active = 1'
        );
        $stmt->execute([$id]);
        return (int) $stmt->fetchColumn() > 0;
    }
}
