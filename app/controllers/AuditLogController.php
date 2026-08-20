<?php

declare(strict_types=1);

/**
 * Audit log viewer (Build Prompt 2.5 / Tech Spec §11). Read-only — there is
 * no create/edit/delete action here, matching the append-only, no-UPDATE/
 * DELETE contract on audit_log itself.
 *
 * Visibility per PRD §3.2's permission matrix: Super Admin and Chairman see
 * every row; GM sees only rows where they are the actor ("own approvals
 * only" — GM's only state-changing actions in this system ARE approvals, so
 * restricting to user_id = themselves is exactly that restriction). This is
 * enforced server-side in the query filter, not just hidden in the UI, so a
 * GM can't see other actors' rows by tampering with the query string.
 */
class AuditLogController
{
    public function index(): void
    {
        $user = Auth::user();
        Permission::require($user, 'audit_log', 'view');

        $isGm = ($user['role_name'] ?? null) === 'gm';

        $entityType = trim((string) ($_GET['entity_type'] ?? ''));
        $dateFrom   = trim((string) ($_GET['date_from'] ?? ''));
        $dateTo     = trim((string) ($_GET['date_to'] ?? ''));
        $userId     = (int) ($_GET['user_id'] ?? 0);
        $page       = (int) ($_GET['page'] ?? 1);

        $result = AuditLog::search([
            'entity_type'      => $entityType !== '' ? $entityType : null,
            'date_from'        => self::validDate($dateFrom) ? $dateFrom : null,
            'date_to'          => self::validDate($dateTo) ? $dateTo : null,
            'user_id'          => $userId > 0 ? $userId : null,
            'restrict_user_id' => $isGm ? (int) $user['id'] : null,
            'page'             => $page,
        ]);

        View::render('audit_log/index', [
            'title'          => 'Audit Log',
            'rows'           => $result['rows'],
            'total'          => $result['total'],
            'page'           => $result['page'],
            'totalPages'     => $result['totalPages'],
            'entityTypes'    => AuditLog::distinctEntityTypes(),
            // GM's view is locked to their own actions, so the "user" filter
            // (which would otherwise let them pick someone else) is pointless
            // and hidden — the restriction is enforced above regardless.
            'users'          => $isGm ? [] : User::all(),
            'isGm'           => $isGm,
            'filters'        => [
                'entity_type' => $entityType,
                'date_from'   => $dateFrom,
                'date_to'     => $dateTo,
                'user_id'     => $userId,
            ],
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
}
