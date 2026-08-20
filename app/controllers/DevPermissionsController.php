<?php

declare(strict_types=1);

// TEMP: dev-only permission inspector, gated to super_admin. Revisit at
// Prompt 4.1 security audit — decide keep-and-formalize vs. delete.
/**
 * Prompt 1.2 manual-QA aid: shows the logged-in user's role and full
 * permission list so each of the 4 seeded roles can be verified against
 * PRD §3.2's matrix by logging in as that role and visiting this page.
 * No sidebar nav entry — direct-URL-only, and gated to super_admin: this
 * isn't a "module.action" screen tracked in role_permissions, so it's a
 * direct role check rather than a new RBAC permission entry, reusing
 * ForbiddenException for the same 403 handling Permission::require() gets.
 */
class DevPermissionsController
{
    public function index(): void
    {
        $user = Auth::user();

        if (($user['role_name'] ?? null) !== 'super_admin') {
            throw new ForbiddenException('Dev permission inspector is restricted to Super Admin.');
        }

        $permissions = $_SESSION['permissions'] ?? [];
        sort($permissions);

        View::render('dev/permissions', [
            'title' => 'Permission Check (Dev)',
            'user' => $user,
            'permissions' => $permissions,
        ]);
    }
}
