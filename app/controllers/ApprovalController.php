<?php

declare(strict_types=1);

/**
 * GM / Chairman approval queues and actions (Build Prompt 2.1 / Tech Spec §7).
 *
 * The queue view itself is a server-rendered HTML page, but Approve/Reject are
 * Alpine-driven AJAX calls against JSON endpoints (Tech Spec §16) — no full
 * page reload between decisions. Each state change runs inside one DB
 * transaction that also writes the hash-chained audit row, so an unlogged
 * approval/rejection is an action that didn't happen (CLAUDE.md rule 3).
 *
 * Permissions per PRD §3.2: only GM and Chairman hold expenses.approve, and
 * only their own tier. Permission::require() is the security control; the
 * role-level tier filter below (which queue a user sees) is a UX nicety on
 * top of it, and ApprovalEngine refuses to act on a step that isn't the
 * acting user's own pending tier regardless.
 */
class ApprovalController
{
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

    private function requireApprove(): array
    {
        $user = Auth::user();
        Permission::require($user, 'expenses', 'approve');
        return $user;
    }

    // ------------------------------------------------------------------
    // Queue view
    // ------------------------------------------------------------------

    public function index(): void
    {
        $user = $this->requireApprove();

        // A user only ever sees their own tier's queue (Tech Spec §7). GM and
        // Chairman are the only roles holding expenses.approve, so a fully
        // unbranched fallback here would leak the page to a mis-configured
        // role — still guarded by Permission::require() above.
        $queue = match ($user['role_name']) {
            'gm'       => [ExpenseStatus::PendingGm,       'Pending GM'],
            'chairman' => [ExpenseStatus::PendingChairman, 'Pending Chairman'],
            default    => [null, 'Awaiting review'],
        };

        [$pendingStatus, $tierLabel] = $queue;

        $items = $pendingStatus !== null
            ? array_map(static fn (array $e): array => [
                'id'           => (int) $e['id'],
                'expense_no'   => $e['expense_no'] ?? '—',
                'date'         => date('d/m/Y', strtotime($e['date'])),
                'payee'        => $e['payee'],
                'description'  => $e['description'],
                'department'   => $e['department_name'] ?? '—',
                'amount'       => naira($e['amount']),
                'submitted_by' => $e['created_by_name'] ?? '—',
            ], Expense::approvalQueue((int) $user['role_id'], $pendingStatus))
            : [];

        View::render('approvals/index', [
            'title'     => 'Approval Queue',
            'tierLabel' => $tierLabel,
            'items'     => $items,
        ]);
    }

    // ------------------------------------------------------------------
    // Approve / reject — Alpine-driven JSON endpoints
    // ------------------------------------------------------------------

    public function approve(): void
    {
        $user = $this->requireApprove();

        $input = $this->jsonInput();
        if (!Csrf::verify($input['_csrf'] ?? null)) {
            $this->json(['ok' => false, 'message' => 'Your session expired. Please try again.'], 419);
            return;
        }

        $expenseId = (int) ($input['expense_id'] ?? 0);
        if ($expenseId <= 0) {
            $this->json(['ok' => false, 'message' => 'Missing expense reference.'], 422);
            return;
        }

        $pdo = Database::connection();
        try {
            $pdo->beginTransaction();
            $result = ApprovalEngine::approve($expenseId, (int) $user['id'], (string) $user['role_name']);
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
            $this->json(['ok' => false, 'message' => 'Could not approve the expense. Please try again.'], 500);
            return;
        }

        if ($result['status'] === ExpenseStatus::PendingGm->value) {
            $nextExpense = Expense::find($expenseId);
            EmailNotifier::expensePendingApproval($nextExpense, 'gm');
            PushNotifier::expensePendingApproval($nextExpense, 'gm');
        } elseif ($result['status'] === ExpenseStatus::PendingChairman->value) {
            $nextExpense = Expense::find($expenseId);
            EmailNotifier::expensePendingApproval($nextExpense, 'chairman');
            PushNotifier::expensePendingApproval($nextExpense, 'chairman');
        }

        $labels = [
            'pending_gm'       => 'now pending GM approval',
            'pending_chairman' => 'now pending Chairman approval',
            'approved'         => 'is fully approved',
        ];
        $label = $labels[$result['status']] ?? 'approved';
        $this->json([
            'ok'      => true,
            'status'  => $result['status'],
            'message' => "Expense {$result['expense_no']} {$label}.",
        ]);
    }

    public function reject(): void
    {
        $user = $this->requireApprove();

        $input = $this->jsonInput();
        if (!Csrf::verify($input['_csrf'] ?? null)) {
            $this->json(['ok' => false, 'message' => 'Your session expired. Please try again.'], 419);
            return;
        }

        $expenseId = (int) ($input['expense_id'] ?? 0);
        $comment   = trim((string) ($input['comment'] ?? ''));
        if ($expenseId <= 0) {
            $this->json(['ok' => false, 'message' => 'Missing expense reference.'], 422);
            return;
        }

        $pdo = Database::connection();
        try {
            $pdo->beginTransaction();
            $result = ApprovalEngine::reject($expenseId, (int) $user['id'], (string) $user['role_name'], $comment);
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
            $this->json(['ok' => false, 'message' => 'Could not reject the expense. Please try again.'], 500);
            return;
        }

        $rejectedExpense = Expense::find($expenseId);
        EmailNotifier::expenseRejected($rejectedExpense);
        PushNotifier::expenseRejected($rejectedExpense);

        $this->json(['ok' => true, 'message' => "Expense {$result['expense_no']} rejected."]);
    }
}