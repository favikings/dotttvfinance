<?php

declare(strict_types=1);

class PaymentVoucher
{
    private const VOUCHER_NO_PREFIX = 'PV-';

    /** Sequential within the current calendar year, e.g. PV-2026-0001. */
    public static function nextVoucherNo(): string
    {
        $year = date('Y');
        $stmt = Database::connection()->prepare(
            'SELECT COUNT(*) FROM payment_vouchers WHERE voucher_no LIKE ?'
        );
        $stmt->execute([self::VOUCHER_NO_PREFIX . $year . '-%']);
        $sequence = (int) $stmt->fetchColumn() + 1;

        return self::VOUCHER_NO_PREFIX . $year . '-' . str_pad((string) $sequence, 4, '0', STR_PAD_LEFT);
    }

    /**
     * @param array{expense_id: int, voucher_no: string, payment_method: string, bank_account: ?string,
     *              payment_reference: ?string, supporting_doc_path: ?string, paid_by: int, paid_at: string} $data
     */
    public static function create(array $data): int
    {
        $stmt = Database::connection()->prepare(
            'INSERT INTO payment_vouchers
                (expense_id, voucher_no, payment_method, bank_account, payment_reference,
                 supporting_doc_path, paid_by, paid_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $data['expense_id'],
            $data['voucher_no'],
            $data['payment_method'],
            $data['bank_account'],
            $data['payment_reference'],
            $data['supporting_doc_path'],
            $data['paid_by'],
            $data['paid_at'],
        ]);
        return (int) Database::connection()->lastInsertId();
    }

    /** Detail view — joined with the labels the UI needs so views never run their own queries. */
    public static function find(int $id): ?array
    {
        $stmt = Database::connection()->prepare(
            'SELECT pv.id, pv.expense_id, pv.voucher_no, pv.payment_method, pv.bank_account,
                    pv.payment_reference, pv.supporting_doc_path, pv.paid_by, pv.paid_at, pv.created_at,
                    e.expense_no, e.payee, e.description, e.amount, e.date AS expense_date,
                    d.name AS department_name,
                    u.name AS paid_by_name
             FROM payment_vouchers pv
             JOIN expenses e ON e.id = pv.expense_id
             LEFT JOIN departments d ON d.id = e.department_id
             LEFT JOIN users u ON u.id = pv.paid_by
             WHERE pv.id = ? LIMIT 1'
        );
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    /**
     * List view, filterable by paid-date range and the paid expense's
     * department (Build Prompt 2.4: "filterable by date/department").
     *
     * @param array{date_from?: ?string, date_to?: ?string, department_id?: ?int} $filters
     */
    public static function all(array $filters = []): array
    {
        $sql = 'SELECT pv.id, pv.voucher_no, pv.payment_method, pv.bank_account, pv.payment_reference,
                       pv.paid_at,
                       e.expense_no, e.payee, e.amount,
                       d.name AS department_name,
                       u.name AS paid_by_name
                FROM payment_vouchers pv
                JOIN expenses e ON e.id = pv.expense_id
                LEFT JOIN departments d ON d.id = e.department_id
                LEFT JOIN users u ON u.id = pv.paid_by
                WHERE 1 = 1';
        $params = [];

        if (!empty($filters['date_from'])) {
            $sql .= ' AND pv.paid_at >= ?';
            $params[] = $filters['date_from'] . ' 00:00:00';
        }
        if (!empty($filters['date_to'])) {
            $sql .= ' AND pv.paid_at <= ?';
            $params[] = $filters['date_to'] . ' 23:59:59';
        }
        if (!empty($filters['department_id'])) {
            $sql .= ' AND e.department_id = ?';
            $params[] = $filters['department_id'];
        }

        $sql .= ' ORDER BY pv.paid_at DESC, pv.id DESC';

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * Approved expenses with no voucher yet — the Payments module's "create a
     * voucher" entry point (Tech Spec §9 / Build Prompt 2.4).
     */
    public static function unpaidApprovedExpenses(): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT e.id, e.expense_no, e.date, e.payee, e.amount, d.name AS department_name
             FROM expenses e
             LEFT JOIN departments d ON d.id = e.department_id
             LEFT JOIN payment_vouchers pv ON pv.expense_id = e.id
             WHERE e.status = ? AND pv.id IS NULL
             ORDER BY e.date ASC, e.id ASC'
        );
        $stmt->execute([ExpenseStatus::Approved->value]);
        return $stmt->fetchAll();
    }
}
