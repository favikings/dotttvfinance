<?php

declare(strict_types=1);

/**
 * Live expense entry + approval chain generation (Build Prompt 1.4).
 *
 * Flow (Tech Spec §7): the expense inserts as 'draft', then ApprovalEngine
 * immediately generates the approval chain for its bracket and moves it to
 * the correct pending/approved status — all inside ONE transaction that also
 * writes the audit row, so an unlogged expense is an expense that didn't
 * happen (CLAUDE.md rule 3).
 *
 * Permissions per PRD §3.2: Accountant creates expenses (expenses.create);
 * GM/Chairman/Super Admin see the list (expenses.view).
 */
class ExpenseController
{
    private function guard(string $action): array
    {
        $user = Auth::user();
        Permission::require($user, 'expenses', $action);
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
    // Expense list
    // ------------------------------------------------------------------

    public function index(): void
    {
        $this->guard('view');

        View::render('expenses/index', [
            'title'    => 'Expenses',
            'expenses' => Expense::all(),
        ]);
    }

    // ------------------------------------------------------------------
    // Entry form
    // ------------------------------------------------------------------

    public function create(): void
    {
        $this->guard('create');

        View::render('expenses/create', [
            'title'        => 'Record Expense',
            'departments'  => Department::all(),
            'categories'   => ExpenseCategory::all(),
            'fundAccounts' => FundAccount::active(),
            'today'        => date('Y-m-d'),
        ]);
    }

    public function store(): void
    {
        $this->requirePost();
        $user = $this->guard('create');

        if (!Csrf::verify($_POST['_csrf'] ?? null)) {
            $this->flash('error', 'Your session expired. Please try again.');
            $this->redirectTo('/expenses/create');
        }

        $data = $_POST;
        $file = $_FILES['supporting_doc'] ?? [];

        // ---------- validation (application layer — schema doesn't enforce these) ----------
        $date        = trim((string) ($data['date'] ?? ''));
        $documentNo  = trim((string) ($data['document_no'] ?? ''));
        $payee       = trim((string) ($data['payee'] ?? ''));
        $description = trim((string) ($data['description'] ?? ''));
        $departmentId = (int) ($data['department_id'] ?? 0);
        $categoryId   = $data['category_id'] !== '' ? (int) $data['category_id'] : null;
        $amount       = (string) ($data['amount'] ?? '');
        $fundId       = (int) ($data['fund_account_id'] ?? 0);

        $error = $this->validate($date, $documentNo, $payee, $description, $departmentId, $categoryId, $amount, $fundId);
        if ($error !== null) {
            $this->flash('error', $error);
            $this->redirectTo('/expenses/create');
        }

        // ---------- supporting doc (optional) ----------
        try {
            $receiptPath = Upload::receipt($file);
        } catch (InvalidArgumentException $e) {
            $this->flash('error', $e->getMessage());
            $this->redirectTo('/expenses/create');
        }

        $amountNormalized = self::normalizeAmount($amount);

        // ---------- insert draft + generate chain + audit, atomically ----------
        $pdo = Database::connection();
        $pdo->beginTransaction();
        try {
            $expenseNo = Expense::nextExpenseNo($date);

            $expenseId = Expense::create([
                'expense_no'           => $expenseNo,
                'date'                 => $date,
                'document_no'          => $documentNo,
                'payee'                => $payee,
                'description'          => $description,
                'department_id'        => $departmentId,
                'category_id'          => $categoryId,
                'amount'               => $amountNormalized,
                'fund_account_id'      => $fundId,
                'supporting_doc_path'  => $receiptPath,
                'created_by'           => $user['id'],
            ]);

            $status = ApprovalEngine::generateChain([
                'id'                => $expenseId,
                'amount'            => $amountNormalized,
                'resubmission_count' => 0,
                'created_by'        => $user['id'],
            ]);

            $expense = Expense::find($expenseId);
            $after = self::auditPayload($expense);

            AuditLogger::record($user['id'], 'create', 'expenses', $expenseId, [], $after);

            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            // Don't leave an orphaned file behind when the DB write failed.
            if ($receiptPath !== null) {
                $stored = UPLOADS_PATH . '/' . $receiptPath;
                if (is_file($stored)) {
                    @unlink($stored);
                }
            }
            throw $e;
        }

        if ($status === ExpenseStatus::PendingGm) {
            EmailNotifier::expensePendingApproval($expense, 'gm');
            PushNotifier::expensePendingApproval($expense, 'gm');
        } elseif ($status === ExpenseStatus::PendingChairman) {
            EmailNotifier::expensePendingApproval($expense, 'chairman');
            PushNotifier::expensePendingApproval($expense, 'chairman');
        }

        $this->flash('success', "Expense {$expenseNo} recorded. Status: {$status->value}.");
        $this->redirectTo('/expenses');
    }

    /** @return string|null user-facing error message, or null when valid */
    private function validate(
        string $date,
        string $documentNo,
        string $payee,
        string $description,
        int $departmentId,
        ?int $categoryId,
        string $amount,
        int $fundId
    ): ?string {
        if (!self::validDate($date)) {
            return 'Please enter a valid expense date.';
        }
        if ($documentNo === '') {
            return 'A document number is required for live expense entries.';
        }
        if (Expense::documentNoExists($documentNo)) {
            return "Document number \"{$documentNo}\" is already in use.";
        }
        if ($payee === '' || mb_strlen($payee) > 150) {
            return 'Please enter a payee (150 characters max).';
        }
        if ($description === '') {
            return 'Please enter a description.';
        }
        if (!Department::exists($departmentId)) {
            return 'Please choose a valid department.';
        }
        if ($categoryId !== null && !ExpenseCategory::exists($categoryId)) {
            return 'Please choose a valid category.';
        }
        if (self::normalizeAmount($amount) === null || (float) $amount <= 0) {
            return 'Please enter a valid amount greater than zero.';
        }
        if (!FundAccount::isActive($fundId)) {
            return 'Please choose a valid fund account.';
        }
        return null;
    }

    /** Validate an ISO Y-m-d date. */
    private static function validDate(string $date): bool
    {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            return false;
        }
        $d = DateTime::createFromFormat('Y-m-d', $date);
        return $d !== false && $d->format('Y-m-d') === $date;
    }

    /** Keep amounts as exact '1234.56' strings for DECIMAL columns and tier comparisons. */
    private static function normalizeAmount(string $amount): ?string
    {
        $amount = trim($amount);
        if ($amount === '' || !is_numeric($amount) || (float) $amount < 0) {
            return null;
        }
        return number_format((float) $amount, 2, '.', '');
    }

    /** The state captured in the audit after_json for an expense create. */
    private static function auditPayload(array $expense): array
    {
        return [
            'expense_no'      => $expense['expense_no'],
            'date'            => $expense['date'],
            'document_no'     => $expense['document_no'],
            'payee'           => $expense['payee'],
            'description'     => $expense['description'],
            'department'      => $expense['department_name'],
            'category'        => $expense['category_name'],
            'amount'          => $expense['amount'],
            'fund_account'    => $expense['fund_account_name'],
            'status'          => $expense['status'],
            'supporting_doc'  => $expense['supporting_doc_path'],
        ];
    }
}