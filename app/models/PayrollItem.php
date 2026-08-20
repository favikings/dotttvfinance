<?php

declare(strict_types=1);

/**
 * Payroll item model (Build Prompt 3.2 / PRD §6.6). Belongs to a single
 * payroll_runs row. `net_salary` is a DB GENERATED column (schema.sql) —
 * this class never computes it in PHP, only reads it back after insert.
 *
 * Items can only be added/removed while the parent run is still 'draft'
 * (enforced in PayrollController against a fresh PayrollRun::find() read,
 * same pattern the guide's other locked-after-approval flows use). Once the
 * run is 'approved', the only mutation left is markPaid() per item.
 */
class PayrollItem
{
    /** @return array<int, array> */
    public static function forRun(int $payrollRunId): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT i.id, i.payroll_run_id, i.employee_name, i.department_id,
                    i.basic_salary, i.allowances, i.deductions, i.loan_deduction,
                    i.net_salary, i.payment_status, i.created_at,
                    d.name AS department_name
             FROM payroll_items i
             LEFT JOIN departments d ON d.id = i.department_id
             WHERE i.payroll_run_id = ?
             ORDER BY i.employee_name ASC'
        );
        $stmt->execute([$payrollRunId]);
        return $stmt->fetchAll();
    }

    public static function find(int $id): ?array
    {
        $stmt = Database::connection()->prepare(
            'SELECT i.id, i.payroll_run_id, i.employee_name, i.department_id,
                    i.basic_salary, i.allowances, i.deductions, i.loan_deduction,
                    i.net_salary, i.payment_status, i.created_at,
                    d.name AS department_name,
                    r.status AS run_status, r.period_month, r.period_year
             FROM payroll_items i
             LEFT JOIN departments d ON d.id = i.department_id
             JOIN payroll_runs r ON r.id = i.payroll_run_id
             WHERE i.id = ? LIMIT 1'
        );
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    /**
     * @param array{payroll_run_id: int, employee_name: string, department_id: ?int,
     *              basic_salary: string, allowances: string, deductions: string,
     *              loan_deduction: string} $data
     */
    public static function create(array $data): int
    {
        $stmt = Database::connection()->prepare(
            'INSERT INTO payroll_items
                (payroll_run_id, employee_name, department_id, basic_salary, allowances, deductions, loan_deduction, payment_status)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $data['payroll_run_id'],
            $data['employee_name'],
            $data['department_id'],
            $data['basic_salary'],
            $data['allowances'],
            $data['deductions'],
            $data['loan_deduction'],
            PayrollItemPaymentStatus::Pending->value,
        ]);
        return (int) Database::connection()->lastInsertId();
    }

    public static function delete(int $id): void
    {
        $stmt = Database::connection()->prepare('DELETE FROM payroll_items WHERE id = ?');
        $stmt->execute([$id]);
    }

    /**
     * FOR UPDATE lock on a pending item about to be marked paid: stops two
     * accountants racing the same item into a double pay confirmation.
     * Joined against the run so a stale/approved-then-somehow-draft race
     * can't sneak an item through outside an approved run.
     */
    public static function lockPending(int $id): ?array
    {
        $stmt = Database::connection()->prepare(
            'SELECT i.id, i.payroll_run_id, i.payment_status, r.status AS run_status
             FROM payroll_items i
             JOIN payroll_runs r ON r.id = i.payroll_run_id
             WHERE i.id = ? AND i.payment_status = ? AND r.status = ?
             LIMIT 1
             FOR UPDATE'
        );
        $stmt->execute([$id, PayrollItemPaymentStatus::Pending->value, PayrollRunStatus::Approved->value]);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    public static function markPaid(int $id): void
    {
        $stmt = Database::connection()->prepare(
            'UPDATE payroll_items SET payment_status = ? WHERE id = ?'
        );
        $stmt->execute([PayrollItemPaymentStatus::Paid->value, $id]);
    }

    /** True once every item on the run has cleared to 'paid' — flips the run itself. */
    public static function allPaid(int $payrollRunId): bool
    {
        $stmt = Database::connection()->prepare(
            'SELECT COUNT(*) FROM payroll_items WHERE payroll_run_id = ? AND payment_status != ?'
        );
        $stmt->execute([$payrollRunId, PayrollItemPaymentStatus::Paid->value]);
        return (int) $stmt->fetchColumn() === 0;
    }

    /** Aggregate totals for the run's metric cards. */
    public static function totalsForRun(int $payrollRunId): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT COUNT(*) AS item_count,
                    COALESCE(SUM(basic_salary), 0) AS total_basic,
                    COALESCE(SUM(allowances), 0) AS total_allowances,
                    COALESCE(SUM(deductions), 0) AS total_deductions,
                    COALESCE(SUM(loan_deduction), 0) AS total_loan_deduction,
                    COALESCE(SUM(net_salary), 0) AS total_net_salary,
                    COALESCE(SUM(CASE WHEN payment_status = ? THEN net_salary ELSE 0 END), 0) AS total_paid
             FROM payroll_items WHERE payroll_run_id = ?'
        );
        $stmt->execute([PayrollItemPaymentStatus::Paid->value, $payrollRunId]);
        return $stmt->fetch();
    }
}
