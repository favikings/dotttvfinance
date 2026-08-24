<?php

declare(strict_types=1);

/**
 * Self-service password change (Build Prompt 1.7 / Tech Spec §5a). Deliberately
 * NOT gated by Permission::require() — this isn't a module.action check against
 * other people's/company data, it's "does this session belong to the account
 * being modified." All four roles reach it, Super Admin included.
 */
class AccountController
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

    public function showPassword(): void
    {
        View::render('account/password', ['title' => 'Change Password']);
    }

    public function updatePassword(): void
    {
        $userId = Auth::id();

        $input = $this->jsonInput();
        if (!Csrf::verify($input['_csrf'] ?? null)) {
            $this->json(['ok' => false, 'message' => 'Your session expired. Please try again.'], 419);
            return;
        }

        $currentPassword = (string) ($input['current_password'] ?? '');
        $newPassword = (string) ($input['new_password'] ?? '');
        $confirmPassword = (string) ($input['confirm_password'] ?? '');

        // Tech Spec §5a validation order: current password must verify FIRST —
        // that's the actual security control, reject before checking anything
        // else, generic error so a stolen session alone doesn't confirm anything.
        $hash = User::passwordHash((int) $userId);
        if ($hash === null || !password_verify($currentPassword, $hash)) {
            $this->json(['ok' => false, 'message' => 'Current password is incorrect.'], 422);
            return;
        }

        if (strlen($newPassword) < 8) {
            $this->json(['ok' => false, 'message' => 'New password must be at least 8 characters.'], 422);
            return;
        }

        if ($newPassword !== $confirmPassword) {
            $this->json(['ok' => false, 'message' => 'New password and confirmation do not match.'], 422);
            return;
        }

        $pdo = Database::connection();
        try {
            $pdo->beginTransaction();
            User::updatePassword((int) $userId, password_hash($newPassword, PASSWORD_BCRYPT, ['cost' => 12]));
            // Never write the password, hashed or plain, to audit_log — only
            // that a change happened and when (Tech Spec §5a).
            AuditLogger::record((int) $userId, 'password_changed', 'users', (int) $userId, [], ['changed_at' => date('Y-m-d H:i:s')]);
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log($e->getMessage());
            $this->json(['ok' => false, 'message' => 'Could not update your password. Please try again.'], 500);
            return;
        }

        // Cheap extra safety, same as login — this isn't solving "an attacker
        // has an active session," so the current session stays valid, no
        // forced re-login.
        session_regenerate_id(true);

        $this->json(['ok' => true, 'message' => 'Password updated.']);
    }
}
