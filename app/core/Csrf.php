<?php

declare(strict_types=1);

/**
 * Minimal session-bound CSRF token, per Tech Spec §18 ("CSRF token on every
 * state-changing form/AJAX call, validated server-side"). One token per
 * session, rotated on login since privilege changes there — every
 * subsequent state-changing form in the app reuses this, not just login.
 */
class Csrf
{
    public static function token(): string
    {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }

        return $_SESSION['csrf_token'];
    }

    public static function verify(mixed $token): bool
    {
        return is_string($token) && $token !== '' && isset($_SESSION['csrf_token'])
            && hash_equals($_SESSION['csrf_token'], $token);
    }

    public static function field(): string
    {
        return '<input type="hidden" name="_csrf" value="' . htmlspecialchars(self::token(), ENT_QUOTES, 'UTF-8') . '">';
    }
}
