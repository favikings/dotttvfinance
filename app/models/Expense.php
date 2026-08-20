<?php

declare(strict_types=1);

class Expense
{
    /** Live entries are the only ones that get an auto expense_no (schema.sql). */
    private const EXPENSE_NO_PREFIX = 'EXP-';

    /** List view — joined with the labels the UI needs so views never run their own queries. */
    public static function all(): array
    {
        $stmt = Database::connection()->query(
            'SELECT e.id, e.expense_no, e.date, e.document_no, e.payee, e.description,
                    e.amount, e.status, e.supporting_doc_path, e.is_historical, e.source,
                    e.resubmission_count, e.created_at,
                    d.name AS department_name,
                    c.name AS category_name,
                    fa.name AS fund_account_name,
                    u.name AS created_by_name
             FROM expenses e
             LEFT JOIN departments d ON d.id = e.department_id
             LEFT JOIN expense_categories c ON c.id = e.category_id
             LEFT JOIN fund_account fa ON fa.id = e.fund_account_id
             LEFT JOIN users u ON u.id = e.created_by
             ORDER BY e.date DESC, e.id DESC'
        );
        return $stmt->fetchAll();
    }

    public static function find(int $id): ?array
    {
        $stmt = Database::connection()->prepare(
            'SELECT e.id, e.expense_no, e.date, e.document_no, e.payee, e.description,
                    e.amount, e.status, e.supporting_doc_path, e.is_historical, e.source,
                    e.resubmission_count, e.rejected_reason, e.closed_reason, e.created_at,
                    e.department_id, e.category_id, e.fund_account_id, e.created_by,
                    d.name AS department_name,
                    c.name AS category_name,
                    fa.name AS fund_account_name,
                    u.name AS created_by_name,
                    u.email AS created_by_email
             FROM expenses e
             LEFT JOIN departments d ON d.id = e.department_id
             LEFT JOIN expense_categories c ON c.id = e.category_id
             LEFT JOIN fund_account fa ON fa.id = e.fund_account_id
             LEFT JOIN users u ON u.id = e.created_by
             WHERE e.id = ? LIMIT 1'
        );
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    /**
     * Auto expense number, e.g. EXP-2026-0001. Sequential within the current
     * calendar year; the DB enforces global uniqueness if a race ever happens.
     */
    public static function nextExpenseNo(?string $date = null): string
    {
        $year = $date !== null && preg_match('/^(\d{4})/', $date, $m) ? $m[1] : date('Y');
        $stmt = Database::connection()->prepare(
            'SELECT COUNT(*) FROM expenses WHERE expense_no LIKE ?'
        );
        $stmt->execute([self::EXPENSE_NO_PREFIX . $year . '-%']);
        $sequence = (int) $stmt->fetchColumn() + 1;

        return self::EXPENSE_NO_PREFIX . $year . '-' . str_pad((string) $sequence, 4, '0', STR_PAD_LEFT);
    }

    /** document_no must be required + unique for live (PRD §5). */
    public static function documentNoExists(string $documentNo, ?int $excludeId = null): bool
    {
        $sql = 'SELECT COUNT(*) FROM expenses WHERE document_no = ?';
        $params = [$documentNo];
        if ($excludeId !== null) {
            $sql .= ' AND id != ?';
            $params[] = $excludeId;
        }
        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);
        return (int) $stmt->fetchColumn() > 0;
    }

    /**
     * Insert a live expense in draft state. The caller then immediately runs
     * ApprovalEngine::generateChain() in the same transaction to submit it.
     *
     * @param array{expense_no: string, date: string, document_no: string, payee: string,
     *              description: string, department_id: int, category_id: ?int, amount: string,
     *              fund_account_id: int, supporting_doc_path: ?string, created_by: int} $data
     */
    public static function create(array $data): int
    {
        $stmt = Database::connection()->prepare(
            'INSERT INTO expenses
                (expense_no, date, document_no, payee, description, department_id, category_id,
                 amount, fund_account_id, supporting_doc_path, status, resubmission_count,
                 is_historical, source, created_by)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0, 0, \'live\', ?)'
        );
        $stmt->execute([
            $data['expense_no'],
            $data['date'],
            $data['document_no'],
            $data['payee'],
            $data['description'],
            $data['department_id'],
            $data['category_id'],
            $data['amount'],
            $data['fund_account_id'],
            $data['supporting_doc_path'],
            ExpenseStatus::Draft->value,
            $data['created_by'],
        ]);
        return (int) Database::connection()->lastInsertId();
    }

    public static function updateStatus(int $id, ExpenseStatus $status): void
    {
        $stmt = Database::connection()->prepare('UPDATE expenses SET status = ? WHERE id = ?');
        $stmt->execute([$status->value, $id]);
    }

    /**
     * GM/Chairman approval queue (Build Prompt 2.1 / Tech Spec §7). Joins the
     * CURRENT cycle's expense_approvals (cycle matches resubmission_count, the
     * cycle the chain was generated for) so a user only ever sees expenses
     * that genuinely have a pending approval step for their own role — never
     * another tier's, never an already-decided one. Historical entries never
     * reach a queue because their status is 'approved' from the start.
     *
     * @return array<int, array> queue rows safe to render directly
     */
    public static function approvalQueue(int $roleId, ExpenseStatus $status): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT DISTINCT e.id, e.expense_no, e.date, e.payee, e.description,
                    e.amount, e.status, e.resubmission_count,
                    d.name AS department_name,
                    c.name AS category_name,
                    u.name AS created_by_name
             FROM expenses e
             JOIN expense_approvals ea
               ON ea.expense_id = e.id
              AND ea.cycle_number = e.resubmission_count
              AND ea.required_role_id = ?
              AND ea.action = ?
             LEFT JOIN departments d ON d.id = e.department_id
             LEFT JOIN expense_categories c ON c.id = e.category_id
             LEFT JOIN users u ON u.id = e.created_by
             WHERE e.status = ?
             ORDER BY e.date ASC, e.id ASC'
        );
        $stmt->execute([
            $roleId,
            ExpenseApprovalAction::Pending->value,
            $status->value,
        ]);
        return $stmt->fetchAll();
    }

    /**
     * FOR UPDATE lock on an approved expense about to get a payment voucher
     * (Tech Spec §9 / Build Prompt 2.4): only an 'approved' expense can be
     * paid — enforced here, not just hidden from the UI. The lock also stops
     * two accountants racing the same expense into two vouchers: the second
     * blocks until the first commits (flipping status to 'paid'), then
     * re-reads the row and the WHERE clause stops matching.
     */
    public static function lockApproved(int $id): ?array
    {
        $stmt = Database::connection()->prepare(
            'SELECT id, expense_no, status FROM expenses WHERE id = ? AND status = ? LIMIT 1 FOR UPDATE'
        );
        $stmt->execute([$id, ExpenseStatus::Approved->value]);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    /** Tech Spec §7 "On rejection": set rejected + store the required reason. */
    public static function reject(int $id, string $reason): void
    {
        $stmt = Database::connection()->prepare(
            'UPDATE expenses SET status = ?, rejected_reason = ? WHERE id = ?'
        );
        $stmt->execute([ExpenseStatus::Rejected->value, $reason, $id]);
    }

    /**
     * Bulk historical backfill insert (Tech Spec §13): skips ApprovalEngine
     * entirely — status is 'approved' from the moment it's saved, since
     * these are records of expenses that already happened, not requests
     * awaiting approval. expense_no stays NULL; that sequential numbering
     * only applies to live entries going forward (schema.sql).
     *
     * @param array{date: string, document_no: ?string, payee: string, description: string,
     *              department_id: int, amount: string, fund_account_id: int, created_by: int} $data
     */
    public static function createHistorical(array $data): int
    {
        $stmt = Database::connection()->prepare(
            'INSERT INTO expenses
                (expense_no, date, document_no, payee, description, department_id, category_id,
                 amount, fund_account_id, supporting_doc_path, status, resubmission_count,
                 is_historical, source, created_by)
             VALUES (NULL, ?, ?, ?, ?, ?, NULL, ?, ?, NULL, ?, 0, 1, \'backfilled\', ?)'
        );
        $stmt->execute([
            $data['date'],
            $data['document_no'],
            $data['payee'],
            $data['description'],
            $data['department_id'],
            $data['amount'],
            $data['fund_account_id'],
            ExpenseStatus::Approved->value,
            $data['created_by'],
        ]);
        return (int) Database::connection()->lastInsertId();
    }
}