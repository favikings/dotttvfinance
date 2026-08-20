<?php

declare(strict_types=1);

/**
 * Payroll (Build Prompt 3.2 / PRD §6.6).
 *
 * Flow: the Accountant creates one payroll_runs row per period_month/year
 * (schema's UNIQUE(period_month, period_year) — a friendly pre-check plus a
 * duplicate-key catch, never a raw DB error reaching the user) and adds
 * payroll_items to it in bulk while it's 'draft'. The GM gives a single
 * sign-off (payroll.approve) — no tiered chain, no reject step, since the
 * `status` ENUM only has draft/approved/paid. Once approved, items are
 * locked and the only remaining action is marking each item's
 * payment_status 'paid' (payroll.edit); the run itself flips to 'paid' once
 * every item has cleared.
 *
 * `net_salary` is a DB GENERATED column (schema.sql) — never computed here,
 * only read back after insert.
 *
 * Every create/approve/pay runs inside one DB transaction that also writes
 * the hash-chained audit row (CLAUDE.md rule 3).
 */
class PayrollController
{
    private function guard(string $action): array
    {
        $user = Auth::user();
        Permission::require($user, 'payroll', $action);
        return $user;
    }

    private function flash(string $type, string $message): void
    {
        $_SESSION['flash_' . $type] = $message;
    }

    private function redirectTo(string $path): void
    {
        header('Location: ' . url($path));
        exit;
    }

    private function requirePost(): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
            http_response_code(405);
            echo '405 Method Not Allowed';
            exit;
        }
    }

    // ------------------------------------------------------------------
    // List — every payroll run
    // ------------------------------------------------------------------

    public function index(): void
    {
        $user = $this->guard('view');

        View::render('payroll/index', [
            'title'     => 'Payroll',
            'runs'      => PayrollRun::all(),
            'canCreate' => Permission::check($user, 'payroll', 'create'),
        ]);
    }

    // ------------------------------------------------------------------
    // Create a run (Accountant)
    // ------------------------------------------------------------------

    public function create(): void
    {
        $this->guard('create');

        View::render('payroll/create', [
            'title'       => 'New Payroll Run',
            'currentYear' => (int) date('Y'),
            'currentMonth' => (int) date('n'),
        ]);
    }

    public function store(): void
    {
        $this->requirePost();
        $user = $this->guard('create');

        if (!Csrf::verify($_POST['_csrf'] ?? null)) {
            $this->flash('error', 'Your session expired. Please try again.');
            $this->redirectTo('/payroll/create');
        }

        $month = (int) ($_POST['period_month'] ?? 0);
        $year  = (int) ($_POST['period_year'] ?? 0);

        if ($month < 1 || $month > 12) {
            $this->flash('error', 'Please choose a valid month.');
            $this->redirectTo('/payroll/create');
        }
        if ($year < 2000 || $year > 2100) {
            $this->flash('error', 'Please enter a valid year.');
            $this->redirectTo('/payroll/create');
        }
        if (PayrollRun::existsForPeriod($month, $year)) {
            $this->flash('error', 'A payroll run for ' . PayrollRun::monthName($month) . ' ' . $year . ' already exists.');
            $this->redirectTo('/payroll/create');
        }

        try {
            $runId = PayrollRun::create($month, $year, (int) $user['id']);
        } catch (PDOException $e) {
            // Race-condition fallback behind the pre-check above — the
            // schema's UNIQUE(period_month, period_year) is the real guard;
            // this just keeps a raw duplicate-key error off the screen.
            if ((string) $e->getCode() === '23000') {
                $this->flash('error', 'A payroll run for ' . PayrollRun::monthName($month) . ' ' . $year . ' already exists.');
                $this->redirectTo('/payroll/create');
            }
            throw $e;
        }

        $pdo = Database::connection();
        $pdo->beginTransaction();
        try {
            AuditLogger::record($user['id'], 'create', 'payroll_runs', $runId, [], [
                'period_month' => $month,
                'period_year'  => $year,
                'status'       => PayrollRunStatus::Draft->value,
            ]);
            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }

        $this->flash('success', 'Payroll run for ' . PayrollRun::monthName($month) . ' ' . $year . ' created. Add employees below.');
        $this->redirectTo('/payroll/' . $runId);
    }

    // ------------------------------------------------------------------
    // Run detail — items list, bulk add-item form, approve, per-item pay
    // ------------------------------------------------------------------

    public function show(string $id): void
    {
        $user = $this->guard('view');

        $run = PayrollRun::find((int) $id);
        if ($run === null) {
            $this->flash('error', 'Payroll run not found.');
            $this->redirectTo('/payroll');
        }

        View::render('payroll/show', [
            'title'       => 'Payroll — ' . PayrollRun::monthName((int) $run['period_month']) . ' ' . $run['period_year'],
            'run'         => $run,
            'items'       => PayrollItem::forRun((int) $run['id']),
            'totals'      => PayrollItem::totalsForRun((int) $run['id']),
            'departments' => Department::all(),
            'canEdit'     => Permission::check($user, 'payroll', 'edit') && $run['status'] === PayrollRunStatus::Draft->value,
            'canPay'      => Permission::check($user, 'payroll', 'edit') && $run['status'] === PayrollRunStatus::Approved->value,
            'canApprove'  => Permission::check($user, 'payroll', 'approve') && $run['status'] === PayrollRunStatus::Draft->value,
        ]);
    }

    /**
     * Bulk "save all" — every row validated up front, all rows insert or
     * none do, one AuditLogger::record() per row inside the transaction
     * (mirrors HistoricalEntryController::storeExpenses()).
     */
    public function storeItems(string $id): void
    {
        $this->requirePost();
        $user = $this->guard('edit');

        $run = PayrollRun::find((int) $id);
        if ($run === null) {
            $this->flash('error', 'Payroll run not found.');
            $this->redirectTo('/payroll');
        }
        if ($run['status'] !== PayrollRunStatus::Draft->value) {
            $this->flash('error', 'Employees can only be added while the run is still in draft.');
            $this->redirectTo('/payroll/' . $id);
        }

        if (!Csrf::verify($_POST['_csrf'] ?? null)) {
            $this->flash('error', 'Your session expired. Please try again.');
            $this->redirectTo('/payroll/' . $id);
        }

        $names          = is_array($_POST['employee_name'] ?? null) ? $_POST['employee_name'] : [];
        $departmentIds  = is_array($_POST['department_id'] ?? null) ? $_POST['department_id'] : [];
        $basicSalaries  = is_array($_POST['basic_salary'] ?? null) ? $_POST['basic_salary'] : [];
        $allowancesRaw  = is_array($_POST['allowances'] ?? null) ? $_POST['allowances'] : [];
        $deductionsRaw  = is_array($_POST['deductions'] ?? null) ? $_POST['deductions'] : [];
        $loanDeductions = is_array($_POST['loan_deduction'] ?? null) ? $_POST['loan_deduction'] : [];

        if ($names === []) {
            $this->flash('error', 'Add at least one employee before saving.');
            $this->redirectTo('/payroll/' . $id);
        }

        $rows = [];
        $position = 0;
        foreach (array_keys($names) as $key) {
            $position++;
            $name         = trim((string) ($names[$key] ?? ''));
            $departmentId = trim((string) ($departmentIds[$key] ?? ''));
            $basicSalary  = trim((string) ($basicSalaries[$key] ?? ''));
            $allowances   = trim((string) ($allowancesRaw[$key] ?? '0'));
            $deductions   = trim((string) ($deductionsRaw[$key] ?? '0'));
            $loanDeduction = trim((string) ($loanDeductions[$key] ?? '0'));

            // A row Alpine added but the user never filled in — skip it
            // silently rather than forcing them to delete blank trailing rows.
            if ($name === '' && $departmentId === '' && $basicSalary === '') {
                continue;
            }

            $error = $this->validateRow($name, $departmentId, $basicSalary, $allowances, $deductions, $loanDeduction);
            if ($error !== null) {
                $this->flash('error', "Row {$position}: {$error}");
                $this->redirectTo('/payroll/' . $id);
            }

            $rows[] = [
                'employee_name'  => $name,
                'department_id'  => $departmentId !== '' ? (int) $departmentId : null,
                'basic_salary'   => self::normalizeAmount($basicSalary),
                'allowances'     => self::normalizeAmount($allowances),
                'deductions'     => self::normalizeAmount($deductions),
                'loan_deduction' => self::normalizeAmount($loanDeduction),
            ];
        }

        if ($rows === []) {
            $this->flash('error', 'Add at least one employee before saving.');
            $this->redirectTo('/payroll/' . $id);
        }

        $pdo = Database::connection();
        $pdo->beginTransaction();
        try {
            foreach ($rows as $row) {
                $itemId = PayrollItem::create($row + ['payroll_run_id' => (int) $id]);

                $item = PayrollItem::find($itemId);
                AuditLogger::record($user['id'], 'create', 'payroll_items', $itemId, [], [
                    'payroll_run_id' => (int) $id,
                    'employee_name'  => $item['employee_name'],
                    'department'     => $item['department_name'],
                    'basic_salary'   => $item['basic_salary'],
                    'allowances'     => $item['allowances'],
                    'deductions'     => $item['deductions'],
                    'loan_deduction' => $item['loan_deduction'],
                    'net_salary'     => $item['net_salary'],
                ]);
            }

            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }

        $count = count($rows);
        $this->flash('success', $count === 1 ? '1 employee added.' : "{$count} employees added.");
        $this->redirectTo('/payroll/' . $id);
    }

    public function deleteItem(string $itemId): void
    {
        $this->requirePost();
        $user = $this->guard('edit');

        $item = PayrollItem::find((int) $itemId);
        if ($item === null) {
            $this->flash('error', 'Payroll item not found.');
            $this->redirectTo('/payroll');
        }
        if ($item['run_status'] !== PayrollRunStatus::Draft->value) {
            $this->flash('error', 'Employees can only be removed while the run is still in draft.');
            $this->redirectTo('/payroll/' . $item['payroll_run_id']);
        }

        if (!Csrf::verify($_POST['_csrf'] ?? null)) {
            $this->flash('error', 'Your session expired. Please try again.');
            $this->redirectTo('/payroll/' . $item['payroll_run_id']);
        }

        $runId = (int) $item['payroll_run_id'];

        $pdo = Database::connection();
        $pdo->beginTransaction();
        try {
            AuditLogger::record($user['id'], 'delete', 'payroll_items', (int) $itemId, [
                'employee_name' => $item['employee_name'],
                'net_salary'    => $item['net_salary'],
            ], []);
            PayrollItem::delete((int) $itemId);

            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }

        $this->flash('success', "{$item['employee_name']} removed from the run.");
        $this->redirectTo('/payroll/' . $runId);
    }

    // ------------------------------------------------------------------
    // GM approval — single sign-off for the whole run
    // ------------------------------------------------------------------

    public function approve(string $id): void
    {
        $this->requirePost();
        $user = $this->guard('approve');

        if (!Csrf::verify($_POST['_csrf'] ?? null)) {
            $this->flash('error', 'Your session expired. Please try again.');
            $this->redirectTo('/payroll/' . $id);
        }

        $pdo = Database::connection();
        try {
            $pdo->beginTransaction();
            $locked = PayrollRun::lockDraft((int) $id);
            if ($locked === null) {
                throw new InvalidArgumentException('This payroll run is no longer awaiting approval.');
            }

            $itemCount = PayrollItem::totalsForRun((int) $id)['item_count'];
            if ((int) $itemCount === 0) {
                throw new InvalidArgumentException('Add at least one employee before approving this run.');
            }

            PayrollRun::approve((int) $id, (int) $user['id']);

            $run = PayrollRun::find((int) $id);
            AuditLogger::record($user['id'], 'approve', 'payroll_runs', (int) $id, [
                'status' => PayrollRunStatus::Draft->value,
            ], [
                'status'      => $run['status'],
                'approved_by' => $run['approved_by_name'],
            ]);

            $pdo->commit();
        } catch (InvalidArgumentException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $this->flash('error', $e->getMessage());
            $this->redirectTo('/payroll/' . $id);
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }

        $this->flash('success', 'Payroll run approved. Items are now ready for payment.');
        $this->redirectTo('/payroll/' . $id);
    }

    // ------------------------------------------------------------------
    // Per-item payment status tracking
    // ------------------------------------------------------------------

    public function payItem(string $itemId): void
    {
        $this->requirePost();
        $user = $this->guard('edit');

        if (!Csrf::verify($_POST['_csrf'] ?? null)) {
            $this->flash('error', 'Your session expired. Please try again.');
            $this->redirectTo('/payroll');
        }

        $pdo = Database::connection();
        $runId = null;
        try {
            $pdo->beginTransaction();
            $locked = PayrollItem::lockPending((int) $itemId);
            if ($locked === null) {
                throw new InvalidArgumentException('This item is no longer awaiting payment.');
            }
            $runId = (int) $locked['payroll_run_id'];

            PayrollItem::markPaid((int) $itemId);

            $item = PayrollItem::find((int) $itemId);
            AuditLogger::record($user['id'], 'pay', 'payroll_items', (int) $itemId, [
                'payment_status' => PayrollItemPaymentStatus::Pending->value,
            ], [
                'payment_status' => $item['payment_status'],
                'net_salary'     => $item['net_salary'],
            ]);

            if (PayrollItem::allPaid($runId)) {
                PayrollRun::markPaid($runId);
                $run = PayrollRun::find($runId);
                AuditLogger::record($user['id'], 'pay', 'payroll_runs', $runId, [
                    'status' => PayrollRunStatus::Approved->value,
                ], [
                    'status' => $run['status'],
                ]);
            }

            $pdo->commit();
        } catch (InvalidArgumentException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $this->flash('error', $e->getMessage());
            $this->redirectTo($runId !== null ? '/payroll/' . $runId : '/payroll');
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }

        $this->flash('success', "{$item['employee_name']} marked paid.");
        $this->redirectTo('/payroll/' . $runId);
    }

    // ------------------------------------------------------------------
    // Shared validation
    // ------------------------------------------------------------------

    private function validateRow(string $name, string $departmentId, string $basicSalary, string $allowances, string $deductions, string $loanDeduction): ?string
    {
        if ($name === '' || mb_strlen($name) > 150) {
            return 'enter an employee name (150 characters max).';
        }
        if ($departmentId !== '' && !Department::exists((int) $departmentId)) {
            return 'choose a valid department.';
        }
        if (self::normalizeAmount($basicSalary) === null || (float) $basicSalary <= 0) {
            return 'enter a valid basic salary greater than zero.';
        }
        if (self::normalizeAmount($allowances) === null || (float) $allowances < 0) {
            return 'enter a valid allowances amount (0 or more).';
        }
        if (self::normalizeAmount($deductions) === null || (float) $deductions < 0) {
            return 'enter a valid deductions amount (0 or more).';
        }
        if (self::normalizeAmount($loanDeduction) === null || (float) $loanDeduction < 0) {
            return 'enter a valid loan deduction amount (0 or more).';
        }
        return null;
    }

    /** Keep amounts as exact '1234.56' strings for DECIMAL columns. */
    private static function normalizeAmount(string $amount): ?string
    {
        $amount = trim($amount);
        if ($amount === '' || !is_numeric($amount) || (float) $amount < 0) {
            return null;
        }
        return number_format((float) $amount, 2, '.', '');
    }
}
