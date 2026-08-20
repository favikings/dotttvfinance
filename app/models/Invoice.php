<?php

declare(strict_types=1);

/**
 * Invoice model (Prompt 3.1 / PRD §6.2). Revenue side of the ledger.
 *
 * Flow: the Accountant creates the invoice (approval_status 'pending',
 * payment_status 'unpaid'); the GM gives a single sign-off (approved) or
 * rejects it with a reason. Payment tracking (unpaid/partial/paid) then
 * happens via edit on the same row — there is no separate payments table
 * for revenue.
 *
 * Permissions per PRD §3.2: Accountant view/create/edit, GM view/approve,
 * Chairman view, Super Admin full (view/create/edit/delete).
 */
class Invoice
{
    /** Auto invoice number prefix, e.g. INV-2026-0001. */
    private const INVOICE_NO_PREFIX = 'INV-';

    /**
     * List view — optionally filtered by payment_status. Joined with the
     * labels the UI needs (department, creator, approver) so views never run
     * their own queries.
     *
     * @return array<int, array>
     */
    public static function all(?string $paymentStatus = null): array
    {
        $sql = 'SELECT i.id, i.invoice_no, i.date, i.client, i.description,
                       i.department_id, i.amount, i.vat_amount, i.wht_amount,
                       i.due_date, i.payment_status, i.payment_date,
                       i.approval_status, i.approved_by, i.approved_at,
                       i.rejected_reason, i.created_by, i.created_at,
                       d.name AS department_name,
                       c.name AS created_by_name,
                       a.name AS approved_by_name
                FROM invoices i
                LEFT JOIN departments d ON d.id = i.department_id
                LEFT JOIN users c ON c.id = i.created_by
                LEFT JOIN users a ON a.id = i.approved_by';

        $params = [];
        if ($paymentStatus !== null && $paymentStatus !== '') {
            $sql .= ' WHERE i.payment_status = ?';
            $params[] = $paymentStatus;
        }

        $sql .= ' ORDER BY i.date DESC, i.id DESC';

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /** The GM's pending approval queue — invoices awaiting a single sign-off. */
    public static function approvalQueue(): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT i.id, i.invoice_no, i.date, i.client, i.description,
                    i.amount, i.due_date, i.payment_status, i.created_by, i.created_at,
                    d.name AS department_name,
                    c.name AS created_by_name
             FROM invoices i
             LEFT JOIN departments d ON d.id = i.department_id
             LEFT JOIN users c ON c.id = i.created_by
             WHERE i.approval_status = ?
             ORDER BY i.date ASC, i.id ASC'
        );
        $stmt->execute([InvoiceApprovalStatus::Pending->value]);
        return $stmt->fetchAll();
    }

    public static function find(int $id): ?array
    {
        $stmt = Database::connection()->prepare(
            'SELECT i.id, i.invoice_no, i.date, i.client, i.description,
                    i.department_id, i.amount, i.vat_amount, i.wht_amount,
                    i.due_date, i.payment_status, i.payment_date,
                    i.approval_status, i.approved_by, i.approved_at,
                    i.rejected_reason, i.created_by, i.created_at,
                    d.name AS department_name,
                    c.name AS created_by_name,
                    c.email AS created_by_email,
                    a.name AS approved_by_name
             FROM invoices i
             LEFT JOIN departments d ON d.id = i.department_id
             LEFT JOIN users c ON c.id = i.created_by
             LEFT JOIN users a ON a.id = i.approved_by
             WHERE i.id = ? LIMIT 1'
        );
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    /**
     * Auto invoice number, e.g. INV-2026-0001. Sequential within the current
     * calendar year; the DB's UNIQUE(invoice_no) enforces global uniqueness
     * if a race ever happens. The field is editable per PRD §6.2, so this is
     * only a suggested value for the create form — not a hard requirement.
     */
    public static function nextInvoiceNo(?string $date = null): string
    {
        $year = $date !== null && preg_match('/^(\d{4})/', $date, $m) ? $m[1] : date('Y');
        $stmt = Database::connection()->prepare(
            'SELECT COUNT(*) FROM invoices WHERE invoice_no LIKE ?'
        );
        $stmt->execute([self::INVOICE_NO_PREFIX . $year . '-%']);
        $sequence = (int) $stmt->fetchColumn() + 1;

        return self::INVOICE_NO_PREFIX . $year . '-' . str_pad((string) $sequence, 4, '0', STR_PAD_LEFT);
    }

    /** invoice_no is editable per PRD §6.2, so uniqueness is checked on save. */
    public static function invoiceNoExists(string $invoiceNo, ?int $excludeId = null): bool
    {
        $sql = 'SELECT COUNT(*) FROM invoices WHERE invoice_no = ?';
        $params = [$invoiceNo];
        if ($excludeId !== null) {
            $sql .= ' AND id != ?';
            $params[] = $excludeId;
        }
        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);
        return (int) $stmt->fetchColumn() > 0;
    }

    /**
     * Insert a live invoice in pending-approval state.
     *
     * @param array{invoice_no: string, date: string, client: string, description: ?string,
     *              department_id: ?int, amount: string, vat_amount: string, wht_amount: string,
     *              due_date: ?string, payment_status: string, payment_date: ?string, created_by: int} $data
     */
    public static function create(array $data): int
    {
        $stmt = Database::connection()->prepare(
            'INSERT INTO invoices
                (invoice_no, date, client, description, department_id,
                 amount, vat_amount, wht_amount, due_date, payment_status, payment_date,
                 approval_status, created_by)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $data['invoice_no'],
            $data['date'],
            $data['client'],
            $data['description'],
            $data['department_id'],
            $data['amount'],
            $data['vat_amount'],
            $data['wht_amount'],
            $data['due_date'],
            $data['payment_status'],
            $data['payment_date'],
            InvoiceApprovalStatus::Pending->value,
            $data['created_by'],
        ]);
        return (int) Database::connection()->lastInsertId();
    }

    /**
     * @param array{invoice_no: string, date: string, client: string, description: ?string,
     *              department_id: ?int, amount: string, vat_amount: string, wht_amount: string,
     *              due_date: ?string, payment_status: string, payment_date: ?string} $data
     */
    public static function update(int $id, array $data): void
    {
        $stmt = Database::connection()->prepare(
            'UPDATE invoices SET
                invoice_no = ?, date = ?, client = ?, description = ?, department_id = ?,
                amount = ?, vat_amount = ?, wht_amount = ?, due_date = ?,
                payment_status = ?, payment_date = ?
             WHERE id = ?'
        );
        $stmt->execute([
            $data['invoice_no'],
            $data['date'],
            $data['client'],
            $data['description'],
            $data['department_id'],
            $data['amount'],
            $data['vat_amount'],
            $data['wht_amount'],
            $data['due_date'],
            $data['payment_status'],
            $data['payment_date'],
            $id,
        ]);
    }

    public static function delete(int $id): void
    {
        $stmt = Database::connection()->prepare('DELETE FROM invoices WHERE id = ?');
        $stmt->execute([$id]);
    }

    /**
     * FOR UPDATE lock on a pending invoice about to get a GM sign-off: stops
     * two GMs racing the same invoice into two approvals — the second blocks
     * until the first commits (flipping approval_status), then re-reads the
     * row and the WHERE clause stops matching.
     */
    public static function lockPending(int $id): ?array
    {
        $stmt = Database::connection()->prepare(
            'SELECT id, invoice_no, amount, payment_status, approval_status
             FROM invoices
             WHERE id = ? AND approval_status = ?
             LIMIT 1
             FOR UPDATE'
        );
        $stmt->execute([$id, InvoiceApprovalStatus::Pending->value]);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    /** Single GM sign-off: the invoice is recognized as approved revenue. */
    public static function approve(int $id, int $approverId): void
    {
        $stmt = Database::connection()->prepare(
            'UPDATE invoices SET approval_status = ?, approved_by = ?, approved_at = NOW(),
                                 rejected_reason = NULL
             WHERE id = ?'
        );
        $stmt->execute([InvoiceApprovalStatus::Approved->value, $approverId, $id]);
    }

    /** Rejection with a required reason — mirrors the expense rejection flow. */
    public static function reject(int $id, string $reason): void
    {
        $stmt = Database::connection()->prepare(
            'UPDATE invoices SET approval_status = ?, rejected_reason = ?, approved_by = NULL,
                                 approved_at = NULL
             WHERE id = ?'
        );
        $stmt->execute([InvoiceApprovalStatus::Rejected->value, $reason, $id]);
    }
}