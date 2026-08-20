<?php

declare(strict_types=1);

class ExpenseApproval
{
    /**
     * One required approval step in a chain. $action is an ExpenseApprovalAction
     * string ('pending' etc.); $approverId only for auto-approve/acted rows.
     */
    public static function create(
        int $expenseId,
        int $cycleNumber,
        int $approvalRuleId,
        int $tierOrder,
        int $requiredRoleId,
        string $action,
        ?int $approverId = null,
        ?string $comment = null
    ): int {
        $stmt = Database::connection()->prepare(
            'INSERT INTO expense_approvals
                (expense_id, cycle_number, approval_rule_id, tier_order, required_role_id,
                 approver_id, action, comment, acted_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $expenseId,
            $cycleNumber,
            $approvalRuleId,
            $tierOrder,
            $requiredRoleId,
            $approverId,
            $action,
            $comment,
            $approverId !== null ? date('Y-m-d H:i:s') : null,
        ]);
        return (int) Database::connection()->lastInsertId();
    }

    public static function byExpense(int $expenseId): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT ea.id, ea.expense_id, ea.cycle_number, ea.approval_rule_id, ea.tier_order,
                    ea.approver_id, ea.action, ea.comment, ea.acted_at, ea.created_at,
                    r.name AS required_role, a.name AS approver_name
             FROM expense_approvals ea
             JOIN roles r ON r.id = ea.required_role_id
             LEFT JOIN users a ON a.id = ea.approver_id
             WHERE ea.expense_id = ?
             ORDER BY ea.cycle_number ASC, ea.tier_order ASC'
        );
        $stmt->execute([$expenseId]);
        return $stmt->fetchAll();
    }

    /**
     * The single pending step awaiting $roleId in the current cycle — the row
     * GM/Chairman approve or reject. FOR UPDATE serializes two approvers racing
     * the same step: the second blocks until the first commits, then re-reads
     * the committed 'approved'/'rejected' row, the WHERE clause stops matching,
     * and it gets no row (tech Spec §7 "On approval action" concurrency).
     */
    public static function pendingForExpenseRole(int $expenseId, int $cycleNumber, int $roleId): ?array
    {
        $stmt = Database::connection()->prepare(
            'SELECT id, expense_id, cycle_number, tier_order, required_role_id
             FROM expense_approvals
             WHERE expense_id = ? AND cycle_number = ? AND required_role_id = ? AND action = ?
             ORDER BY tier_order ASC
             LIMIT 1
             FOR UPDATE'
        );
        $stmt->execute([$expenseId, $cycleNumber, $roleId, ExpenseApprovalAction::Pending->value]);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    /** The first still-pending step in the current cycle, with its role name — drives expenses.status after each action. */
    public static function nextPendingForCycle(int $expenseId, int $cycleNumber): ?array
    {
        $stmt = Database::connection()->prepare(
            'SELECT ea.id, ea.tier_order, r.name AS required_role
             FROM expense_approvals ea
             JOIN roles r ON r.id = ea.required_role_id
             WHERE ea.expense_id = ? AND ea.cycle_number = ? AND ea.action = ?
             ORDER BY ea.tier_order ASC
             LIMIT 1'
        );
        $stmt->execute([$expenseId, $cycleNumber, ExpenseApprovalAction::Pending->value]);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    /** Mark one step approved (approver + acted_at filled). */
    public static function approveRow(int $id, int $approverId): void
    {
        $stmt = Database::connection()->prepare(
            'UPDATE expense_approvals SET action = ?, approver_id = ?, acted_at = NOW() WHERE id = ?'
        );
        $stmt->execute([ExpenseApprovalAction::Approved->value, $approverId, $id]);
    }

    /** Mark one step rejected with the required comment. */
    public static function rejectRow(int $id, int $approverId, string $comment): void
    {
        $stmt = Database::connection()->prepare(
            'UPDATE expense_approvals SET action = ?, approver_id = ?, comment = ?, acted_at = NOW() WHERE id = ?'
        );
        $stmt->execute([ExpenseApprovalAction::Rejected->value, $approverId, $comment, $id]);
    }

    /**
     * Tech Spec §7 "On rejection": every still-pending step in the same cycle
     * is cancelled (never deleted — kept as historical rows). $exceptRowId is
     * the acting row, already set to 'rejected' before this runs.
     *
     * @return int number of rows cancelled
     */
    public static function cancelPendingForCycle(int $expenseId, int $cycleNumber, ?int $exceptRowId = null): int
    {
        $sql = 'UPDATE expense_approvals SET action = ?, acted_at = NOW()
                WHERE expense_id = ? AND cycle_number = ? AND action = ?';
        $params = [ExpenseApprovalAction::Cancelled->value, $expenseId, $cycleNumber, ExpenseApprovalAction::Pending->value];

        if ($exceptRowId !== null) {
            $sql .= ' AND id != ?';
            $params[] = $exceptRowId;
        }

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);
        return $stmt->rowCount();
    }
}