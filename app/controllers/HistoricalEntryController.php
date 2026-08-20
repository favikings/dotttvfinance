<?php

declare(strict_types=1);

/**
 * Bulk Historical Entry (Build Prompt 1.5 / Tech Spec §13 / PRD §5) — a
 * DISTINCT screen from the live expense form (ExpenseController) and the
 * eventual fund top-up request flow (Phase 2.3): every row entered here
 * records something that already happened on paper, so it inserts directly
 * as approved (expenses: is_historical=1, source='backfilled'; top-ups:
 * is_historical=1) instead of generating an approval chain for an event
 * that's already over.
 */
class HistoricalEntryController
{
    private function guardExpenses(string $action): array
    {
        $user = Auth::user();
        Permission::require($user, 'expenses', $action);
        return $user;
    }

    private function guardTopups(string $action): array
    {
        $user = Auth::user();
        Permission::require($user, 'fund_topups', $action);
        return $user;
    }

    private function verifyCsrf(): bool
    {
        return Csrf::verify($_POST['_csrf'] ?? null);
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

    /** The single v1 fund account (PRD §10.2's resolved single-float decision). */
    private function defaultFundAccountId(): ?int
    {
        $accounts = FundAccount::active();
        return $accounts === [] ? null : (int) $accounts[0]['id'];
    }

    // ------------------------------------------------------------------
    // Historical expenses — table-style bulk grid
    // ------------------------------------------------------------------

    public function expenses(): void
    {
        $this->guardExpenses('create');

        View::render('historical/expenses', [
            'title'       => 'Historical Expense Entry',
            'departments' => Department::all(),
            'today'       => date('Y-m-d'),
        ]);
    }

    /**
     * "Save all" batch action: every row in the grid is validated up front,
     * and either all rows insert or none do — one bad row shouldn't
     * half-commit a page of backfilled entries. Each row still gets its own
     * AuditLogger::record() call, all inside the same transaction.
     */
    public function storeExpenses(): void
    {
        $this->requirePost();
        $user = $this->guardExpenses('create');

        if (!$this->verifyCsrf()) {
            $this->flash('error', 'Your session expired. Please try again.');
            $this->redirectTo('/historical-entry/expenses');
        }

        $fundAccountId = $this->defaultFundAccountId();
        if ($fundAccountId === null) {
            $this->flash('error', 'No active fund account is configured.');
            $this->redirectTo('/historical-entry/expenses');
        }

        $dates         = is_array($_POST['date'] ?? null) ? $_POST['date'] : [];
        $documentNos   = is_array($_POST['document_no'] ?? null) ? $_POST['document_no'] : [];
        $payees        = is_array($_POST['payee'] ?? null) ? $_POST['payee'] : [];
        $descriptions  = is_array($_POST['description'] ?? null) ? $_POST['description'] : [];
        $departmentIds = is_array($_POST['department_id'] ?? null) ? $_POST['department_id'] : [];
        $amounts       = is_array($_POST['amount'] ?? null) ? $_POST['amount'] : [];

        if ($dates === []) {
            $this->flash('error', 'Add at least one row before saving.');
            $this->redirectTo('/historical-entry/expenses');
        }

        $rows = [];
        $position = 0;
        foreach (array_keys($dates) as $key) {
            $position++;
            $date         = trim((string) ($dates[$key] ?? ''));
            $documentNo   = trim((string) ($documentNos[$key] ?? ''));
            $payee        = trim((string) ($payees[$key] ?? ''));
            $description  = trim((string) ($descriptions[$key] ?? ''));
            $departmentId = trim((string) ($departmentIds[$key] ?? ''));
            $amount       = trim((string) ($amounts[$key] ?? ''));

            // A row Alpine added but the user never filled in — skip it
            // silently rather than forcing them to delete blank trailing rows.
            if ($date === '' && $documentNo === '' && $payee === '' && $description === '' && $departmentId === '' && $amount === '') {
                continue;
            }

            $error = $this->validateRow($date, $payee, $description, $departmentId, $amount);
            if ($error !== null) {
                $this->flash('error', "Row {$position}: {$error}");
                $this->redirectTo('/historical-entry/expenses');
            }

            $rows[] = [
                'date'          => $date,
                'document_no'   => $documentNo !== '' ? $documentNo : null,
                'payee'         => $payee,
                'description'   => $description,
                'department_id' => (int) $departmentId,
                'amount'        => self::normalizeAmount($amount),
            ];
        }

        if ($rows === []) {
            $this->flash('error', 'Add at least one row before saving.');
            $this->redirectTo('/historical-entry/expenses');
        }

        $pdo = Database::connection();
        $pdo->beginTransaction();
        try {
            foreach ($rows as $row) {
                $expenseId = Expense::createHistorical([
                    'date'            => $row['date'],
                    'document_no'     => $row['document_no'],
                    'payee'           => $row['payee'],
                    'description'     => $row['description'],
                    'department_id'   => $row['department_id'],
                    'amount'          => $row['amount'],
                    'fund_account_id' => $fundAccountId,
                    'created_by'      => $user['id'],
                ]);

                $expense = Expense::find($expenseId);
                AuditLogger::record($user['id'], 'create', 'expenses', $expenseId, [], [
                    'date'          => $expense['date'],
                    'document_no'   => $expense['document_no'],
                    'payee'         => $expense['payee'],
                    'description'   => $expense['description'],
                    'department'    => $expense['department_name'],
                    'amount'        => $expense['amount'],
                    'status'        => $expense['status'],
                    'is_historical' => true,
                    'source'        => $expense['source'],
                ]);
            }

            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }

        $count = count($rows);
        $this->flash('success', $count === 1 ? '1 historical expense saved.' : "{$count} historical expenses saved.");
        $this->redirectTo('/historical-entry/expenses');
    }

    private function validateRow(string $date, string $payee, string $description, string $departmentId, string $amount): ?string
    {
        if (!self::validDate($date)) {
            return 'enter a valid date.';
        }
        if ($payee === '' || mb_strlen($payee) > 150) {
            return 'enter a payee (150 characters max).';
        }
        if ($description === '') {
            return 'enter a description.';
        }
        if ($departmentId === '' || !Department::exists((int) $departmentId)) {
            return 'choose a valid department.';
        }
        if (self::normalizeAmount($amount) === null || (float) $amount <= 0) {
            return 'enter a valid amount greater than zero.';
        }
        return null;
    }

    // ------------------------------------------------------------------
    // Historical fund top-up — single-row form
    // ------------------------------------------------------------------

    public function topups(): void
    {
        $this->guardTopups('create');

        View::render('historical/topups', [
            'title' => 'Historical Fund Top-Up',
            'today' => date('Y-m-d'),
        ]);
    }

    public function storeTopups(): void
    {
        $this->requirePost();
        $user = $this->guardTopups('create');

        if (!$this->verifyCsrf()) {
            $this->flash('error', 'Your session expired. Please try again.');
            $this->redirectTo('/historical-entry/topups');
        }

        $fundAccountId = $this->defaultFundAccountId();
        if ($fundAccountId === null) {
            $this->flash('error', 'No active fund account is configured.');
            $this->redirectTo('/historical-entry/topups');
        }

        $date      = trim((string) ($_POST['date'] ?? ''));
        $amount    = trim((string) ($_POST['amount'] ?? ''));
        $reference = trim((string) ($_POST['reference'] ?? ''));
        $note      = trim((string) ($_POST['note'] ?? ''));

        if (!self::validDate($date)) {
            $this->flash('error', 'Please enter a valid date.');
            $this->redirectTo('/historical-entry/topups');
        }

        $amountNormalized = self::normalizeAmount($amount);
        if ($amountNormalized === null || (float) $amount <= 0) {
            $this->flash('error', 'Please enter a valid amount greater than zero.');
            $this->redirectTo('/historical-entry/topups');
        }

        $pdo = Database::connection();
        $pdo->beginTransaction();
        try {
            $topupId = FundTopup::createHistorical([
                'fund_account_id' => $fundAccountId,
                'amount'          => $amountNormalized,
                'date'            => $date,
                'requested_by'    => $user['id'],
                'reference'       => $reference !== '' ? $reference : null,
                'note'            => $note !== '' ? $note : null,
            ]);

            $topup = FundTopup::find($topupId);
            AuditLogger::record($user['id'], 'create', 'fund_topups', $topupId, [], [
                'date'          => $topup['date'],
                'amount'        => $topup['amount'],
                'reference'     => $topup['reference'],
                'note'          => $topup['note'],
                'status'        => $topup['status'],
                'is_historical' => true,
            ]);

            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }

        $this->flash('success', 'Historical fund top-up saved.');
        $this->redirectTo('/historical-entry/topups');
    }

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
}
