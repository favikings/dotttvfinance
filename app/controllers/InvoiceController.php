<?php

declare(strict_types=1);

/**
 * Invoice management (Build Prompt 3.1 / PRD §6.2, §3.2).
 *
 * Flow: the Accountant creates/edits invoices (payment + VAT/WHT + due date +
 * payment status). GM gives a single sign-off via the approval queue —
 * deliberately lighter than expenses (revenue recognition is not a
 * spend-control gate, so no tiered chain). Chairman is view-only; Super Admin
 * is full CRUD including delete.
 *
 * Every create/edit/approve/reject/delete runs inside one DB transaction
 * that also writes the hash-chained audit row (CLAUDE.md rule 3).
 */
class InvoiceController
{
    private function guard(string $action): array
    {
        $user = Auth::user();
        Permission::require($user, 'invoices', $action);
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

    private function json(array $payload, int $code = 200): void
    {
        http_response_code($code);
        header('Content-Type: application/json');
        echo json_encode($payload);
    }

    private function jsonInput(): array
    {
        $body = file_get_contents('php://input');
        $decoded = $body !== false ? json_decode($body, true) : null;
        return is_array($decoded) ? $decoded : [];
    }

    // ------------------------------------------------------------------
    // List — filterable by payment_status, overdue highlighted, GM queue
    // ------------------------------------------------------------------

    public function index(): void
    {
        $user = $this->guard('view');

        $status = $_GET['status'] ?? '';
        $statuses = [InvoicePaymentStatus::Unpaid->value, InvoicePaymentStatus::Partial->value, InvoicePaymentStatus::Paid->value];
        if (!in_array($status, $statuses, true)) {
            $status = '';
        }

        $canApprove = Permission::check($user, 'invoices', 'approve');

        // Pending invoices feed a small Alpine queue (mirrors the expense
        // ApprovalController and fund-topup queues) so a GM can clear one
        // without a page reload; the full table below is the read-only list.
        $pending = $canApprove
            ? array_values(array_map(static fn (array $inv): array => [
                'id'          => (int) $inv['id'],
                'invoice_no'  => $inv['invoice_no'],
                'date'        => date('d/m/Y', strtotime($inv['date'])),
                'client'      => $inv['client'],
                'department'  => $inv['department_name'] ?? '—',
                'amount'      => naira($inv['amount']),
                'created_by'  => $inv['created_by_name'] ?? '—',
            ], Invoice::approvalQueue()))
            : [];

        View::render('invoices/index', [
            'title'       => 'Invoices',
            'invoices'    => Invoice::all($status !== '' ? $status : null),
            'pending'     => $pending,
            'filter'      => $status,
            'canCreate'   => Permission::check($user, 'invoices', 'create'),
            'canEdit'     => Permission::check($user, 'invoices', 'edit'),
            'canDelete'   => Permission::check($user, 'invoices', 'delete'),
            'canApprove'  => $canApprove,
            'today'       => date('Y-m-d'),
        ]);
    }

    // ------------------------------------------------------------------
    // Create (Accountant / Super Admin)
    // ------------------------------------------------------------------

    public function create(): void
    {
        $this->guard('create');

        View::render('invoices/create', [
            'title'       => 'Record Invoice',
            'departments' => Department::all(),
            'today'       => date('Y-m-d'),
            'invoiceNo'   => Invoice::nextInvoiceNo(),
        ]);
    }

    public function store(): void
    {
        $this->requirePost();
        $user = $this->guard('create');

        if (!Csrf::verify($_POST['_csrf'] ?? null)) {
            $this->flash('error', 'Your session expired. Please try again.');
            $this->redirectTo('/invoices/create');
        }

        $data = $this->normalizeForm($_POST);
        $error = $this->validate($data, null);
        if ($error !== null) {
            $this->flash('error', $error);
            $this->redirectTo('/invoices/create');
        }

        $pdo = Database::connection();
        $pdo->beginTransaction();
        try {
            $invoiceId = Invoice::create($data + ['created_by' => $user['id']]);

            $invoice = Invoice::find($invoiceId);
            AuditLogger::record($user['id'], 'create', 'invoices', $invoiceId, [], self::auditPayload($invoice));

            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }

        $this->flash('success', "Invoice {$data['invoice_no']} recorded and submitted for GM approval.");
        $this->redirectTo('/invoices');
    }

    // ------------------------------------------------------------------
    // Edit (Accountant / Super Admin)
    // ------------------------------------------------------------------

    public function edit(string $id): void
    {
        $this->guard('edit');

        $invoice = Invoice::find((int) $id);
        if ($invoice === null) {
            $this->flash('error', 'Invoice not found.');
            $this->redirectTo('/invoices');
        }

        View::render('invoices/edit', [
            'title'       => 'Edit Invoice',
            'invoice'     => $invoice,
            'departments' => Department::all(),
            'today'       => date('Y-m-d'),
        ]);
    }

    public function update(string $id): void
    {
        $this->requirePost();
        $user = $this->guard('edit');

        $invoice = Invoice::find((int) $id);
        if ($invoice === null) {
            $this->flash('error', 'Invoice not found.');
            $this->redirectTo('/invoices');
        }

        if (!Csrf::verify($_POST['_csrf'] ?? null)) {
            $this->flash('error', 'Your session expired. Please try again.');
            $this->redirectTo('/invoices/edit/' . $id);
        }

        $data = $this->normalizeForm($_POST);
        $error = $this->validate($data, (int) $id);
        if ($error !== null) {
            $this->flash('error', $error);
            $this->redirectTo('/invoices/edit/' . $id);
        }

        $pdo = Database::connection();
        $pdo->beginTransaction();
        try {
            Invoice::update((int) $id, $data);

            $updated = Invoice::find((int) $id);
            AuditLogger::record($user['id'], 'edit', 'invoices', (int) $id, self::auditPayload($invoice), self::auditPayload($updated));

            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }

        $this->flash('success', "Invoice {$data['invoice_no']} updated.");
        $this->redirectTo('/invoices');
    }

    // ------------------------------------------------------------------
    // Delete (Super Admin only — invoices.delete)
    // ------------------------------------------------------------------

    public function destroy(string $id): void
    {
        $this->requirePost();
        $user = $this->guard('delete');

        $invoice = Invoice::find((int) $id);
        if ($invoice === null) {
            $this->flash('error', 'Invoice not found.');
            $this->redirectTo('/invoices');
        }

        if (!Csrf::verify($_POST['_csrf'] ?? null)) {
            $this->flash('error', 'Your session expired. Please try again.');
            $this->redirectTo('/invoices');
        }

        $pdo = Database::connection();
        $pdo->beginTransaction();
        try {
            AuditLogger::record($user['id'], 'delete', 'invoices', (int) $id, self::auditPayload($invoice), []);
            Invoice::delete((int) $id);

            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }

        $this->flash('success', "Invoice {$invoice['invoice_no']} deleted.");
        $this->redirectTo('/invoices');
    }

    // ------------------------------------------------------------------
    // GM approval — Alpine-driven JSON endpoints, single sign-off
    // ------------------------------------------------------------------

    public function approve(): void
    {
        $user = $this->guard('approve');

        $input = $this->jsonInput();
        if (!Csrf::verify($input['_csrf'] ?? null)) {
            $this->json(['ok' => false, 'message' => 'Your session expired. Please try again.'], 419);
            return;
        }

        $invoiceId = (int) ($input['invoice_id'] ?? 0);
        if ($invoiceId <= 0) {
            $this->json(['ok' => false, 'message' => 'Missing invoice reference.'], 422);
            return;
        }

        $pdo = Database::connection();
        try {
            $pdo->beginTransaction();
            $locked = Invoice::lockPending($invoiceId);
            if ($locked === null) {
                throw new InvalidArgumentException('This invoice is no longer awaiting approval.');
            }

            Invoice::approve($invoiceId, (int) $user['id']);

            $invoice = Invoice::find($invoiceId);
            AuditLogger::record($user['id'], 'approve', 'invoices', $invoiceId, ['approval_status' => InvoiceApprovalStatus::Pending->value], self::auditPayload($invoice));

            $pdo->commit();
        } catch (InvalidArgumentException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $this->json(['ok' => false, 'message' => $e->getMessage()], 422);
            return;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log($e->getMessage());
            $this->json(['ok' => false, 'message' => 'Could not approve the invoice. Please try again.'], 500);
            return;
        }

        $this->json([
            'ok'      => true,
            'message' => "Invoice {$invoice['invoice_no']} approved.",
        ]);
    }

    public function reject(): void
    {
        $user = $this->guard('approve');

        $input = $this->jsonInput();
        if (!Csrf::verify($input['_csrf'] ?? null)) {
            $this->json(['ok' => false, 'message' => 'Your session expired. Please try again.'], 419);
            return;
        }

        $invoiceId = (int) ($input['invoice_id'] ?? 0);
        $reason    = trim((string) ($input['reason'] ?? ''));
        if ($invoiceId <= 0) {
            $this->json(['ok' => false, 'message' => 'Missing invoice reference.'], 422);
            return;
        }
        if ($reason === '') {
            $this->json(['ok' => false, 'message' => 'A rejection reason is required.'], 422);
            return;
        }

        $pdo = Database::connection();
        try {
            $pdo->beginTransaction();
            $locked = Invoice::lockPending($invoiceId);
            if ($locked === null) {
                throw new InvalidArgumentException('This invoice is no longer awaiting approval.');
            }

            Invoice::reject($invoiceId, $reason);

            $invoice = Invoice::find($invoiceId);
            AuditLogger::record($user['id'], 'reject', 'invoices', $invoiceId, ['approval_status' => InvoiceApprovalStatus::Pending->value], self::auditPayload($invoice));

            $pdo->commit();
        } catch (InvalidArgumentException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $this->json(['ok' => false, 'message' => $e->getMessage()], 422);
            return;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log($e->getMessage());
            $this->json(['ok' => false, 'message' => 'Could not reject the invoice. Please try again.'], 500);
            return;
        }

        $this->json([
            'ok'      => true,
            'message' => "Invoice {$invoice['invoice_no']} rejected.",
        ]);
    }

    // ------------------------------------------------------------------
    // Shared form handling
    // ------------------------------------------------------------------

    /** Trim/normalize raw POST fields into the shape Invoice::create()/update() expect. */
    private function normalizeForm(array $raw): array
    {
        $dueDate     = trim((string) ($raw['due_date'] ?? ''));
        $paymentDate = trim((string) ($raw['payment_date'] ?? ''));

        return [
            'invoice_no'     => trim((string) ($raw['invoice_no'] ?? '')),
            'date'           => trim((string) ($raw['date'] ?? '')),
            'client'         => trim((string) ($raw['client'] ?? '')),
            'description'    => trim((string) ($raw['description'] ?? '')),
            'department_id'  => ($raw['department_id'] ?? '') !== '' ? (int) $raw['department_id'] : null,
            'amount'         => self::normalizeAmount((string) ($raw['amount'] ?? '')),
            'vat_amount'     => self::normalizeAmount((string) ($raw['vat_amount'] ?? '0')),
            'wht_amount'     => self::normalizeAmount((string) ($raw['wht_amount'] ?? '0')),
            'due_date'       => $dueDate !== '' ? $dueDate : null,
            'payment_status' => trim((string) ($raw['payment_status'] ?? InvoicePaymentStatus::Unpaid->value)),
            'payment_date'   => $paymentDate !== '' ? $paymentDate : null,
        ];
    }

    /** @return string|null user-facing error message, or null when valid */
    private function validate(array $data, ?int $excludeId): ?string
    {
        if ($data['invoice_no'] === '' || mb_strlen($data['invoice_no']) > 30) {
            return 'Please enter an invoice number (30 characters max).';
        }
        if (Invoice::invoiceNoExists($data['invoice_no'], $excludeId)) {
            return "Invoice number \"{$data['invoice_no']}\" is already in use.";
        }
        if (!self::validDate($data['date'])) {
            return 'Please enter a valid invoice date.';
        }
        if ($data['client'] === '' || mb_strlen($data['client']) > 150) {
            return 'Please enter a client name (150 characters max).';
        }
        if ($data['department_id'] !== null && !Department::exists($data['department_id'])) {
            return 'Please choose a valid department.';
        }
        if ($data['amount'] === null || (float) $data['amount'] <= 0) {
            return 'Please enter a valid amount greater than zero.';
        }
        if ($data['vat_amount'] === null || $data['wht_amount'] === null) {
            return 'Please enter valid VAT and withholding amounts (0 or more).';
        }
        if ($data['due_date'] !== null && !self::validDate($data['due_date'])) {
            return 'Please enter a valid due date.';
        }
        $validStatuses = [
            InvoicePaymentStatus::Unpaid->value,
            InvoicePaymentStatus::Partial->value,
            InvoicePaymentStatus::Paid->value,
        ];
        if (!in_array($data['payment_status'], $validStatuses, true)) {
            return 'Please choose a valid payment status.';
        }
        if ($data['payment_date'] !== null && !self::validDate($data['payment_date'])) {
            return 'Please enter a valid payment date.';
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

    /** Keep amounts as exact '1234.56' strings for DECIMAL columns. */
    private static function normalizeAmount(string $amount): ?string
    {
        $amount = trim($amount);
        if ($amount === '' || !is_numeric($amount) || (float) $amount < 0) {
            return null;
        }
        return number_format((float) $amount, 2, '.', '');
    }

    /** The state captured in the audit after_json for an invoice. */
    private static function auditPayload(array $invoice): array
    {
        return [
            'invoice_no'     => $invoice['invoice_no'],
            'date'           => $invoice['date'],
            'client'         => $invoice['client'],
            'description'    => $invoice['description'],
            'department'     => $invoice['department_name'],
            'amount'         => $invoice['amount'],
            'vat_amount'     => $invoice['vat_amount'],
            'wht_amount'     => $invoice['wht_amount'],
            'due_date'       => $invoice['due_date'],
            'payment_status' => $invoice['payment_status'],
            'payment_date'   => $invoice['payment_date'],
            'approval_status' => $invoice['approval_status'],
            'approved_by'    => $invoice['approved_by_name'],
        ];
    }
}