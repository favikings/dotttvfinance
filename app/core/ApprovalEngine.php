<?php

declare(strict_types=1);

/**
 * Approval chain generator per Tech Spec §7 steps 1-5.
 *
 * Caller (ExpenseController) opens a DB transaction, inserts the expense as
 * draft, then calls generateChain() and audits the result — all inside the
 * same transaction. generateChain() itself performs no BEGIN/COMMIT; it runs
 * its writes on the caller's open transaction so the audit row and the chain
 * are atomic with the expense insert.
 */
class ApprovalEngine
{
    /**
     * Build the expense_approvals chain for a live expense and set its status.
     *
     * @param array{
     *     id: int, amount: string, resubmission_count: int, created_by: int
     * } $expense
     */
    public static function generateChain(array $expense): ExpenseStatus
    {
        $rule = ApprovalRule::findActiveForAmount($expense['amount']);
        if ($rule === null) {
            throw new RuntimeException(
                'No active approval rule covers this amount. Ask a Super Admin to check the approval-rule tiers.'
            );
        }

        // Step 2: ordered role names for this bracket, e.g. ["accountant","gm","chairman"].
        $requiredRoles = $rule['required_roles'];
        if ($requiredRoles === []) {
            throw new RuntimeException('The matching approval rule has no required approvers.');
        }

        // Resolve role names -> ids once (chain rows FK to roles.id).
        $roleIds = [];
        foreach (Role::all() as $role) {
            $roleIds[$role['name']] = (int) $role['id'];
        }

        $creatorRoleName = self::creatorRoleName((int) $expense['created_by']);

        $cycleNumber    = (int) $expense['resubmission_count'];
        $ruleId         = (int) $rule['id'];
        $tierOrder      = 0;
        $firstPending   = null; // role id of the first non-auto-approved step

        // Steps 3-4: one expense_approvals row per role, in order. The
        // Accountant's own tier auto-approves (creator is the sole approver
        // at that step) — approved immediately with approver_id = creator.
        foreach ($requiredRoles as $name) {
            if (!isset($roleIds[$name])) {
                throw new RuntimeException("Unknown approver role \"{$name}\" on this approval rule.");
            }
            $tierOrder++;

            $autoApprove = $name === $creatorRoleName;

            ExpenseApproval::create(
                (int) $expense['id'],
                $cycleNumber,
                $ruleId,
                $tierOrder,
                $roleIds[$name],
                $autoApprove ? ExpenseApprovalAction::Approved->value : ExpenseApprovalAction::Pending->value,
                $autoApprove ? (int) $expense['created_by'] : null
            );

            if (!$autoApprove && $firstPending === null) {
                $firstPending = $name;
            }
        }

        // Step 5: status = next pending tier, or approved if the chain is done.
        $status = match ($firstPending) {
            'gm'       => ExpenseStatus::PendingGm,
            'chairman' => ExpenseStatus::PendingChairman,
            default    => ExpenseStatus::Approved,
        };

        Expense::updateStatus((int) $expense['id'], $status);
        return $status;
    }

    private static function creatorRoleName(int $userId): ?string
    {
        $stmt = Database::connection()->prepare(
            'SELECT r.name FROM users u JOIN roles r ON r.id = u.role_id WHERE u.id = ? LIMIT 1'
        );
        $stmt->execute([$userId]);
        $name = $stmt->fetchColumn();
        return $name === false ? null : (string) $name;
    }

    /**
     * Tech Spec §7 "On approval action": a GM or Chairman approves their own
     * tier. Marks the acting expense_approvals row approved, then advances
     * expenses.status to the next still-pending tier — or to 'approved' if
     * this was the last step in the chain. Runs on the caller's open DB
     * transaction (same lifecycle as generateChain); the audit row is written
     * inside that transaction so an unlogged approval is an approval that
     * didn't happen (CLAUDE.md rule 3).
     *
     * @return array{status: string, expense_no: string, acted_on_role: string}
     * @throws InvalidArgumentException when the expense isn't genuinely awaiting this user
     */
    public static function approve(int $expenseId, int $approverId, string $approverRoleName): array
    {
        $expense = Expense::find($expenseId);
        if ($expense === null) {
            throw new InvalidArgumentException('This expense no longer exists.');
        }

        $currentStatus = ExpenseStatus::tryFromString($expense['status']);
        if (!in_array($currentStatus, [ExpenseStatus::PendingGm, ExpenseStatus::PendingChairman], true)) {
            throw new InvalidArgumentException('This expense is not awaiting your approval.');
        }

        $roleId = self::roleIdFor($approverRoleName);
        $cycle = (int) $expense['resubmission_count'];

        // FOR UPDATE inside pendingForExpenseRole: whoever grabs the pending
        // step first wins, everyone else sees no pending row and is refused.
        $pending = ExpenseApproval::pendingForExpenseRole($expenseId, $cycle, $roleId);
        if ($pending === null) {
            throw new InvalidArgumentException('This expense is no longer awaiting your approval — it may have already been decided.');
        }

        ExpenseApproval::approveRow((int) $pending['id'], $approverId);

        $next = ExpenseApproval::nextPendingForCycle($expenseId, $cycle);
        $newStatus = match (true) {
            $next === null => ExpenseStatus::Approved,
            $next['required_role'] === 'gm'       => ExpenseStatus::PendingGm,
            $next['required_role'] === 'chairman' => ExpenseStatus::PendingChairman,
            default => throw new RuntimeException(
                "Unknown next approver role \"{$next['required_role']}\" on expense {$expenseId}."
            ),
        };

        Expense::updateStatus($expenseId, $newStatus);

        AuditLogger::record($approverId, 'approve', 'expenses', $expenseId, [
            'status' => $expense['status'],
        ], [
            'status' => $newStatus->value,
        ]);

        return [
            'status'         => $newStatus->value,
            'expense_no'     => $expense['expense_no'] ?? ('EXP-' . $expenseId),
            'acted_on_role'  => $approverRoleName,
        ];
    }

    /**
     * Tech Spec §7 "On rejection": mark the acting row rejected (comment
     * required), cancel every OTHER still-pending step in the same cycle (kept
     * as historical rows, never deleted), set expenses.status='rejected' with
     * rejected_reason populated, and audit — all in the caller's transaction.
     *
     * @return array{expense_no: string}
     * @throws InvalidArgumentException when a reason is missing or the expense isn't awaiting this user
     */
    public static function reject(int $expenseId, int $approverId, string $approverRoleName, string $comment): array
    {
        $comment = trim($comment);
        if ($comment === '') {
            throw new InvalidArgumentException('A reason is required to reject an expense.');
        }

        $expense = Expense::find($expenseId);
        if ($expense === null) {
            throw new InvalidArgumentException('This expense no longer exists.');
        }

        $currentStatus = ExpenseStatus::tryFromString($expense['status']);
        if (!in_array($currentStatus, [ExpenseStatus::PendingGm, ExpenseStatus::PendingChairman], true)) {
            throw new InvalidArgumentException('This expense is not awaiting your approval.');
        }

        $roleId = self::roleIdFor($approverRoleName);
        $cycle = (int) $expense['resubmission_count'];

        $pending = ExpenseApproval::pendingForExpenseRole($expenseId, $cycle, $roleId);
        if ($pending === null) {
            throw new InvalidArgumentException('This expense is no longer awaiting your approval — it may have already been decided.');
        }

        ExpenseApproval::rejectRow((int) $pending['id'], $approverId, $comment);
        ExpenseApproval::cancelPendingForCycle($expenseId, $cycle, (int) $pending['id']);

        Expense::reject($expenseId, $comment);

        AuditLogger::record($approverId, 'reject', 'expenses', $expenseId, [
            'status' => $expense['status'],
        ], [
            'status'          => ExpenseStatus::Rejected->value,
            'rejected_reason' => $comment,
        ]);

        return ['expense_no' => $expense['expense_no'] ?? ('EXP-' . $expenseId)];
    }

    private static function roleIdFor(string $roleName): int
    {
        $stmt = Database::connection()->prepare('SELECT id FROM roles WHERE name = ? LIMIT 1');
        $stmt->execute([$roleName]);
        $id = $stmt->fetchColumn();
        if ($id === false) {
            throw new RuntimeException("Unknown role \"{$roleName}\".");
        }
        return (int) $id;
    }
}