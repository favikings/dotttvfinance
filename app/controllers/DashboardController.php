<?php

declare(strict_types=1);

/**
 * Phase 1 dashboard (Build Prompt 1.6 / Build Order Phase 1): current balance
 * card from the fund_balances VIEW (never a stored number), reusable KPI
 * metric cards, and a recent-activity feed interleaving expenses + top-ups.
 * Deliberately no pending-approval counts or P&L snapshots — those arrive in
 * Phase 2/3 per the Build Order.
 */
class DashboardController
{
    private const PERIODS = ['month', 'quarter', 'all'];

    public function index(): void
    {
        Permission::require(Auth::user(), 'dashboard', 'view');

        $period = $this->period();
        [$from, $to] = self::periodRange($period);

        View::render('dashboard/index', [
            'title'    => 'Dashboard',
            'accounts' => FundAccount::allWithBalances($from, $to),
            'activity' => Dashboard::recentActivity(10),
            'period'   => $period,
        ]);
    }

    private function period(): string
    {
        $period = $_GET['period'] ?? 'month';
        return in_array($period, self::PERIODS, true) ? $period : 'month';
    }

    /**
     * @return array{0: ?string, 1: ?string} null/null means all-time — no
     * date filter, so allWithBalances() keeps its unfiltered totals.
     */
    private static function periodRange(string $period): array
    {
        if ($period === 'all') {
            return [null, null];
        }

        $now = new DateTimeImmutable();
        $to = $now->format('Y-m-d');

        if ($period === 'quarter') {
            $quarterStartMonth = (int) floor(((int) $now->format('n') - 1) / 3) * 3 + 1;
            $from = $now->setDate((int) $now->format('Y'), $quarterStartMonth, 1)->format('Y-m-d');
        } else {
            $from = $now->format('Y-m-01');
        }

        return [$from, $to];
    }
}
