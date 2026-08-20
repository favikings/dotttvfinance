<?php

declare(strict_types=1);

/**
 * RBAC gate per Tech Spec §6. Reads against $_SESSION['permissions'] — the
 * flat "module.action" array Auth::login() loads once at login by joining
 * role_permissions + permissions for the user's role_id (schema.sql's
 * seeded PRD §3.2 matrix), not re-queried from the DB on every call.
 */
class Permission
{
    public static function check(?array $user, string $module, string $action): bool
    {
        if ($user === null || empty($_SESSION['permissions'])) {
            return false;
        }

        return in_array($module . '.' . $action, $_SESSION['permissions'], true);
    }

    /** @throws ForbiddenException if the user lacks module.action */
    public static function require(?array $user, string $module, string $action): void
    {
        if (!self::check($user, $module, $action)) {
            throw new ForbiddenException("Missing permission: {$module}.{$action}");
        }
    }
}
