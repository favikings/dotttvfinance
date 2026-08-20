<?php

declare(strict_types=1);

/**
 * Session-based auth per Tech Spec §5, plus RBAC session bootstrapping per
 * §6: login() loads the user's full permission set into
 * $_SESSION['permissions'] (flat "module.action" strings) so Permission.php
 * never has to re-query the DB per request. This class answers "who is
 * logged in, are they still allowed to be, and what can they do".
 */
class Auth
{
    /** Per-request memoization so check() doesn't re-query the DB every time it's called within one request. */
    private static ?bool $validated = null;

    public static function attempt(string $email, string $password): array
    {
        $email = trim($email);
        $key = ($_SERVER['REMOTE_ADDR'] ?? 'unknown') . '|' . strtolower($email);

        if (RateLimiter::tooManyAttempts($key)) {
            $minutes = (int) ceil(RateLimiter::retryAfterSeconds($key) / 60);
            return ['ok' => false, 'error' => "Too many failed attempts. Try again in {$minutes} minute" . ($minutes === 1 ? '' : 's') . '.'];
        }

        $stmt = Database::connection()->prepare(
            'SELECT id, name, email, password_hash, role_id, department_id, status FROM users WHERE email = ? LIMIT 1'
        );
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        // Same generic error whether the email doesn't exist, the password is
        // wrong, or the account is inactive — never reveal which (Tech Spec §5
        // acceptance criteria / OWASP: don't leak account existence).
        if (!$user || $user['status'] !== 'active' || !password_verify($password, $user['password_hash'])) {
            RateLimiter::recordFailure($key);
            return ['ok' => false, 'error' => 'Invalid email or password.'];
        }

        RateLimiter::clear($key);
        self::login($user);

        return ['ok' => true, 'error' => null];
    }

    public static function login(array $user): void
    {
        session_regenerate_id(true);

        $roleStmt = Database::connection()->prepare('SELECT name FROM roles WHERE id = ? LIMIT 1');
        $roleStmt->execute([$user['role_id']]);
        $roleName = $roleStmt->fetchColumn();

        $_SESSION['user_id'] = (int) $user['id'];
        $_SESSION['user_name'] = $user['name'];
        $_SESSION['user_email'] = $user['email'];
        $_SESSION['role_id'] = (int) $user['role_id'];
        $_SESSION['role_name'] = $roleName !== false ? $roleName : null;
        $_SESSION['department_id'] = $user['department_id'] !== null ? (int) $user['department_id'] : null;
        $_SESSION['last_activity'] = time();
        unset($_SESSION['csrf_token']); // rotate: fresh token now that privilege changed

        // Tech Spec §6: full permission set loaded once at login, not
        // re-queried per request. Joins schema.sql's seeded role_permissions
        // (already generated from the PRD §3.2 matrix — never duplicated here).
        $permStmt = Database::connection()->prepare(
            'SELECT p.module, p.action FROM role_permissions rp
             JOIN permissions p ON p.id = rp.permission_id
             WHERE rp.role_id = ?'
        );
        $permStmt->execute([$user['role_id']]);
        $_SESSION['permissions'] = array_map(
            static fn (array $row): string => $row['module'] . '.' . $row['action'],
            $permStmt->fetchAll()
        );

        $updateStmt = Database::connection()->prepare('UPDATE users SET last_login_at = NOW() WHERE id = ?');
        $updateStmt->execute([$user['id']]);

        self::$validated = true;
    }

    public static function logout(): void
    {
        $_SESSION = [];

        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', [
                'expires' => time() - 42000,
                'path' => $params['path'],
                'domain' => $params['domain'],
                'secure' => $params['secure'],
                'httponly' => $params['httponly'],
                'samesite' => $params['samesite'] ?? 'Lax',
            ]);
        }

        session_destroy();

        self::$validated = false;
    }

    /**
     * The single source of truth for "is this session still valid right
     * now" — re-checks idle timeout AND users.status='active' from the DB
     * on every call (memoized per-request), not just session data, so a
     * deactivated user is locked out on their very next request rather than
     * only their next login.
     */
    public static function check(): bool
    {
        if (self::$validated !== null) {
            return self::$validated;
        }

        if (empty($_SESSION['user_id'])) {
            return self::$validated = false;
        }

        $timeoutMinutes = (int) Setting::get('session_idle_timeout_minutes', SESSION_IDLE_TIMEOUT_MINUTES_DEFAULT);
        if ($timeoutMinutes <= 0) {
            $timeoutMinutes = SESSION_IDLE_TIMEOUT_MINUTES_DEFAULT;
        }

        if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > $timeoutMinutes * 60) {
            self::logout();
            return self::$validated = false;
        }

        $stmt = Database::connection()->prepare('SELECT status FROM users WHERE id = ? LIMIT 1');
        $stmt->execute([$_SESSION['user_id']]);
        $status = $stmt->fetchColumn();

        if ($status !== 'active') {
            self::logout();
            return self::$validated = false;
        }

        $_SESSION['last_activity'] = time();

        return self::$validated = true;
    }

    public static function user(): ?array
    {
        if (!self::check()) {
            return null;
        }

        return [
            'id' => $_SESSION['user_id'],
            'name' => $_SESSION['user_name'],
            'email' => $_SESSION['user_email'],
            'role_id' => $_SESSION['role_id'],
            'role_name' => $_SESSION['role_name'] ?? null,
            'department_id' => $_SESSION['department_id'],
        ];
    }

    public static function id(): ?int
    {
        return self::check() ? (int) $_SESSION['user_id'] : null;
    }
}
