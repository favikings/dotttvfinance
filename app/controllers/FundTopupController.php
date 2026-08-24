<?php

declare(strict_types=1);

/**
 * Fund top-up request + approval (Tech Spec §8): Accountant submits a
 * request ('pending'); either a GM or a Chairman can clear it with a single
 * sign-off, whoever gets there first — no sequencing, no second approver.
 * Approve/reject are Alpine-driven JSON endpoints (Tech Spec §16), same
 * pattern as ApprovalController's expense queue. Every state change runs
 * inside one DB transaction that also writes the hash-chained audit row
 * (CLAUDE.md rule 3).
 */
class FundTopupController
{
    private function guard(string $action): array
    {
        $user = Auth::user();
        Permission::require($user, 'fund_topups', $action);
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

    /** The single v1 fund account (PRD §10.2's resolved single-float decision). */
    private function defaultFundAccountId(): ?int
    {
        $accounts = FundAccount::active();
        return $accounts === [] ? null : (int) $accounts[0]['id'];
    }

    // ------------------------------------------------------------------
    // List — all top-ups, with inline approve/reject for pending rows
    // ------------------------------------------------------------------

    public function index(): void
    {
        $user = $this->guard('view');

        $topups = FundTopup::all();
        $canApprove = Permission::check($user, 'fund_topups', 'approve');

        // Pending requests feed a small Alpine queue (mirrors ApprovalController)
        // so a GM/Chairman can clear one without a page reload; the full table
        // below is a plain read-only history list, refreshed on next visit.
        $pending = $canApprove
            ? array_values(array_map(static fn (array $t): array => [
                'id'           => (int) $t['id'],
                'date'         => date('d/m/Y', strtotime($t['date'])),
                'amount'       => naira($t['amount']),
                'reference'    => $t['reference'] ?? '—',
                'requested_by' => $t['requested_by_name'] ?? '—',
            ], array_filter($topups, static fn (array $t): bool => $t['status'] === TopupStatus::Pending->value)))
            : [];

        View::render('fund_topups/index', [
            'title'      => 'Fund Account',
            'topups'     => $topups,
            'pending'    => $pending,
            'canCreate'  => Permission::check($user, 'fund_topups', 'create'),
            'canApprove' => $canApprove,
        ]);
    }

    // ------------------------------------------------------------------
    // Request form (Accountant)
    // ------------------------------------------------------------------

    public function create(): void
    {
        $this->guard('create');

        View::render('fund_topups/create', [
            'title' => 'Request Fund Account Top-Up',
            'today' => date('Y-m-d'),
        ]);
    }

    public function store(): void
    {
        $this->requirePost();
        $user = $this->guard('create');

        if (!Csrf::verify($_POST['_csrf'] ?? null)) {
            $this->flash('error', 'Your session expired. Please try again.');
            $this->redirectTo('/fund-topups/create');
        }

        $fundAccountId = $this->defaultFundAccountId();
        if ($fundAccountId === null) {
            $this->flash('error', 'No active fund account is configured.');
            $this->redirectTo('/fund-topups/create');
        }

        $date      = trim((string) ($_POST['date'] ?? ''));
        $amount    = trim((string) ($_POST['amount'] ?? ''));
        $reference = trim((string) ($_POST['reference'] ?? ''));
        $note      = trim((string) ($_POST['note'] ?? ''));

        $error = $this->validate($date, $amount);
        if ($error !== null) {
            $this->flash('error', $error);
            $this->redirectTo('/fund-topups/create');
        }

        $amountNormalized = self::normalizeAmount($amount);

        $pdo = Database::connection();
        $pdo->beginTransaction();
        try {
            $topupId = FundTopup::create([
                'fund_account_id' => $fundAccountId,
                'amount'          => $amountNormalized,
                'date'            => $date,
                'requested_by'    => $user['id'],
                'reference'       => $reference !== '' ? $reference : null,
                'note'            => $note !== '' ? $note : null,
            ]);

            $topup = FundTopup::find($topupId);
            AuditLogger::record($user['id'], 'create', 'fund_topups', $topupId, [], [
                'date'      => $topup['date'],
                'amount'    => $topup['amount'],
                'reference' => $topup['reference'],
                'note'      => $topup['note'],
                'status'    => $topup['status'],
            ]);

            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }

        $this->flash('success', 'Top-up request submitted for approval.');
        $this->redirectTo('/fund-topups');
    }

    private function validate(string $date, string $amount): ?string
    {
        if (!self::validDate($date)) {
            return 'Please enter a valid date.';
        }
        if (self::normalizeAmount($amount) === null || (float) $amount <= 0) {
            return 'Please enter a valid amount greater than zero.';
        }
        return null;
    }

    // ------------------------------------------------------------------
    // Approve / reject — Alpine-driven JSON endpoints, GM or Chairman
    // ------------------------------------------------------------------

    public function approve(): void
    {
        $user = $this->guard('approve');

        $input = $this->jsonInput();
        if (!Csrf::verify($input['_csrf'] ?? null)) {
            $this->json(['ok' => false, 'message' => 'Your session expired. Please try again.'], 419);
            return;
        }

        $topupId = (int) ($input['topup_id'] ?? 0);
        if ($topupId <= 0) {
            $this->json(['ok' => false, 'message' => 'Missing top-up reference.'], 422);
            return;
        }

        $pdo = Database::connection();
        try {
            $pdo->beginTransaction();
            $result = FundTopupEngine::approve($topupId, (int) $user['id']);
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
            $this->json(['ok' => false, 'message' => 'Could not approve the top-up. Please try again.'], 500);
            return;
        }

        $approvedTopup = FundTopup::find($topupId);
        EmailNotifier::topupDecided($approvedTopup, TopupStatus::Approved);
        PushNotifier::topupDecided($approvedTopup, TopupStatus::Approved);

        $this->json([
            'ok'      => true,
            'message' => "Top-up of " . naira($result['amount']) . " approved.",
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

        $topupId = (int) ($input['topup_id'] ?? 0);
        $comment = trim((string) ($input['comment'] ?? ''));
        if ($topupId <= 0) {
            $this->json(['ok' => false, 'message' => 'Missing top-up reference.'], 422);
            return;
        }

        $pdo = Database::connection();
        try {
            $pdo->beginTransaction();
            $result = FundTopupEngine::reject($topupId, (int) $user['id'], $comment);
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
            $this->json(['ok' => false, 'message' => 'Could not reject the top-up. Please try again.'], 500);
            return;
        }

        $rejectedTopup = FundTopup::find($topupId);
        EmailNotifier::topupDecided($rejectedTopup, TopupStatus::Rejected);
        PushNotifier::topupDecided($rejectedTopup, TopupStatus::Rejected);

        $this->json([
            'ok'      => true,
            'message' => "Top-up of " . naira($result['amount']) . " rejected.",
        ]);
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
