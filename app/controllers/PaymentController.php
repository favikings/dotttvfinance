<?php

declare(strict_types=1);

/**
 * Payment vouchers (Tech Spec §9 / Build Prompt 2.4). Option A, resolved: an
 * expense reaching status='approved' is itself sufficient authorization to
 * release payment — there is no second approval chain here. An Accountant
 * creates a payment_vouchers row directly against an approved expense, which
 * flips that expense to 'paid' in the same transaction that writes the
 * hash-chained audit rows (CLAUDE.md rule 3).
 *
 * "Only approved expenses can have a voucher created" is enforced at the
 * model layer (Expense::lockApproved()), not just by hiding the button —
 * posting straight to /payments/create/{id} for a pending/rejected expense
 * is rejected the same way a stale double-submit would be.
 */
class PaymentController
{
    private function guard(string $action): array
    {
        $user = Auth::user();
        Permission::require($user, 'payments', $action);
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
    // List — vouchers (filterable by date/department) + approved expenses
    // still awaiting a voucher
    // ------------------------------------------------------------------

    public function index(): void
    {
        $user = $this->guard('view');

        $dateFrom = trim((string) ($_GET['date_from'] ?? ''));
        $dateTo   = trim((string) ($_GET['date_to'] ?? ''));
        $deptId   = (int) ($_GET['department_id'] ?? 0);

        View::render('payments/index', [
            'title'         => 'Payments',
            'vouchers'      => PaymentVoucher::all([
                'date_from'     => self::validDate($dateFrom) ? $dateFrom : null,
                'date_to'       => self::validDate($dateTo) ? $dateTo : null,
                'department_id' => $deptId > 0 ? $deptId : null,
            ]),
            'awaitingPayment' => PaymentVoucher::unpaidApprovedExpenses(),
            'departments'      => Department::all(),
            'canCreate'        => Permission::check($user, 'payments', 'create'),
            'filters'          => [
                'date_from'     => $dateFrom,
                'date_to'       => $dateTo,
                'department_id' => $deptId,
            ],
        ]);
    }

    // ------------------------------------------------------------------
    // Voucher detail
    // ------------------------------------------------------------------

    public function show(string $id): void
    {
        $this->guard('view');

        $voucher = PaymentVoucher::find((int) $id);
        if ($voucher === null) {
            http_response_code(404);
            echo '404 Not Found';
            return;
        }

        View::render('payments/show', [
            'title'   => 'Payment Voucher ' . $voucher['voucher_no'],
            'voucher' => $voucher,
        ]);
    }

    // ------------------------------------------------------------------
    // Create a voucher against one approved expense
    // ------------------------------------------------------------------

    public function create(string $id): void
    {
        $this->guard('create');

        $expenseId = (int) $id;
        $expense = Expense::find($expenseId);

        if ($expense === null || $expense['status'] !== ExpenseStatus::Approved->value) {
            $this->flash('error', 'Only approved expenses can be paid.');
            $this->redirectTo('/payments');
        }

        View::render('payments/create', [
            'title'   => 'Create Payment Voucher',
            'expense' => $expense,
            'now'     => date('Y-m-d\TH:i'),
        ]);
    }

    public function store(string $id): void
    {
        $this->requirePost();
        $user = $this->guard('create');

        $expenseId = (int) $id;

        if (!Csrf::verify($_POST['_csrf'] ?? null)) {
            $this->flash('error', 'Your session expired. Please try again.');
            $this->redirectTo('/payments/create/' . $expenseId);
        }

        $paymentMethod = PaymentMethod::tryFromString((string) ($_POST['payment_method'] ?? ''));
        $bankAccount   = trim((string) ($_POST['bank_account'] ?? ''));
        $reference     = trim((string) ($_POST['payment_reference'] ?? ''));
        $paidAtRaw     = trim((string) ($_POST['paid_at'] ?? ''));
        $file          = $_FILES['supporting_doc'] ?? [];

        $paidAt = self::normalizeDateTime($paidAtRaw);

        if ($paymentMethod === null) {
            $this->flash('error', 'Please choose a valid payment method.');
            $this->redirectTo('/payments/create/' . $expenseId);
        }
        if ($paidAt === null) {
            $this->flash('error', 'Please enter a valid payment date/time.');
            $this->redirectTo('/payments/create/' . $expenseId);
        }

        try {
            $docPath = Upload::receipt($file);
        } catch (InvalidArgumentException $e) {
            $this->flash('error', $e->getMessage());
            $this->redirectTo('/payments/create/' . $expenseId);
        }

        $pdo = Database::connection();
        $pdo->beginTransaction();
        try {
            $expense = Expense::lockApproved($expenseId);
            if ($expense === null) {
                $pdo->rollBack();
                if ($docPath !== null) {
                    self::deleteUpload($docPath);
                }
                $this->flash('error', 'This expense is no longer approved and awaiting payment — it may already have a voucher, or its approval status changed.');
                $this->redirectTo('/payments');
            }

            $voucherNo = PaymentVoucher::nextVoucherNo();

            $voucherId = PaymentVoucher::create([
                'expense_id'           => $expenseId,
                'voucher_no'           => $voucherNo,
                'payment_method'       => $paymentMethod->value,
                'bank_account'         => $bankAccount !== '' ? $bankAccount : null,
                'payment_reference'    => $reference !== '' ? $reference : null,
                'supporting_doc_path'  => $docPath,
                'paid_by'              => $user['id'],
                'paid_at'              => $paidAt,
            ]);

            AuditLogger::record($user['id'], 'create', 'payment_vouchers', $voucherId, [], [
                'voucher_no'         => $voucherNo,
                'expense_id'         => $expenseId,
                'payment_method'     => $paymentMethod->value,
                'bank_account'       => $bankAccount !== '' ? $bankAccount : null,
                'payment_reference'  => $reference !== '' ? $reference : null,
                'paid_at'            => $paidAt,
            ]);

            Expense::updateStatus($expenseId, ExpenseStatus::Paid);
            AuditLogger::record($user['id'], 'pay', 'expenses', $expenseId, [
                'status' => ExpenseStatus::Approved->value,
            ], [
                'status'     => ExpenseStatus::Paid->value,
                'voucher_no' => $voucherNo,
            ]);

            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            if ($docPath !== null) {
                self::deleteUpload($docPath);
            }
            throw $e;
        }

        $this->flash('success', "Voucher {$voucherNo} created. Expense marked paid.");
        $this->redirectTo('/payments/' . $voucherId);
    }

    private static function deleteUpload(string $relativePath): void
    {
        $stored = UPLOADS_PATH . '/' . $relativePath;
        if (is_file($stored)) {
            @unlink($stored);
        }
    }

    private static function validDate(string $date): bool
    {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            return false;
        }
        $d = DateTime::createFromFormat('Y-m-d', $date);
        return $d !== false && $d->format('Y-m-d') === $date;
    }

    /** Accepts the <input type="datetime-local"> format ('Y-m-d\TH:i') and normalizes to 'Y-m-d H:i:s'. */
    private static function normalizeDateTime(string $value): ?string
    {
        if ($value === '') {
            return null;
        }
        $d = DateTime::createFromFormat('Y-m-d\TH:i', $value) ?: DateTime::createFromFormat('Y-m-d H:i:s', $value);
        return $d !== false ? $d->format('Y-m-d H:i:s') : null;
    }
}
