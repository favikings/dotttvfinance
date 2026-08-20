<?php

declare(strict_types=1);

/**
 * Fund top-up single-approver sign-off per Tech Spec §8: deliberately lighter
 * than the expense chain (ApprovalEngine) — no tiers, no chain rows. Either a
 * GM or a Chairman (both hold fund_topups.approve) can clear a pending
 * request; whoever gets there first wins, no second sign-off required.
 *
 * Caller (FundTopupController) opens the DB transaction; approve()/reject()
 * run on that open transaction and write the audit row inside it, so an
 * unlogged decision is a decision that didn't happen (CLAUDE.md rule 3).
 */
class FundTopupEngine
{
    /**
     * @return array{amount: string, date: string}
     * @throws InvalidArgumentException when the request isn't genuinely pending
     */
    public static function approve(int $topupId, int $approverId): array
    {
        $topup = FundTopup::lockPending($topupId);
        if ($topup === null) {
            throw new InvalidArgumentException(
                'This top-up request is no longer awaiting approval — it may have already been decided.'
            );
        }

        FundTopup::approve($topupId, $approverId);

        AuditLogger::record($approverId, 'approve', 'fund_topups', $topupId, [
            'status' => $topup['status'],
        ], [
            'status'      => TopupStatus::Approved->value,
            'approved_by' => $approverId,
        ]);

        return ['amount' => $topup['amount'], 'date' => $topup['date']];
    }

    /**
     * @return array{amount: string, date: string}
     * @throws InvalidArgumentException when a reason is missing or the request isn't genuinely pending
     */
    public static function reject(int $topupId, int $rejecterId, string $reason): array
    {
        $reason = trim($reason);
        if ($reason === '') {
            throw new InvalidArgumentException('A reason is required to reject a fund top-up request.');
        }

        $topup = FundTopup::lockPending($topupId);
        if ($topup === null) {
            throw new InvalidArgumentException(
                'This top-up request is no longer awaiting approval — it may have already been decided.'
            );
        }

        FundTopup::reject($topupId, $reason);

        AuditLogger::record($rejecterId, 'reject', 'fund_topups', $topupId, [
            'status' => $topup['status'],
        ], [
            'status'          => TopupStatus::Rejected->value,
            'rejected_reason' => $reason,
        ]);

        return ['amount' => $topup['amount'], 'date' => $topup['date']];
    }
}
