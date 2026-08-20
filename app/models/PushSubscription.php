<?php

declare(strict_types=1);

/**
 * Web Push subscriptions (Tech Spec §15a / schema.sql §10a). A user can have
 * several rows (phone + desktop, etc.) — the only uniqueness constraint is on
 * endpoint, so a re-subscribe from the same browser just refreshes its keys.
 */
class PushSubscription
{
    /** Upsert keyed on endpoint — POST /push/subscribe calls this. */
    public static function upsert(int $userId, string $endpoint, string $p256dh, string $authKey, ?string $userAgent): void
    {
        $stmt = Database::connection()->prepare(
            'INSERT INTO push_subscriptions (user_id, endpoint, p256dh_key, auth_key, user_agent)
             VALUES (?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE
                user_id = VALUES(user_id),
                p256dh_key = VALUES(p256dh_key),
                auth_key = VALUES(auth_key),
                user_agent = VALUES(user_agent)'
        );
        $stmt->execute([$userId, $endpoint, $p256dh, $authKey, $userAgent]);
    }

    public static function forUser(int $userId): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT id, endpoint, p256dh_key, auth_key FROM push_subscriptions WHERE user_id = ?'
        );
        $stmt->execute([$userId]);
        return $stmt->fetchAll();
    }

    /** Bumped on a successful send so a Super Admin device list (future) can spot stale rows. */
    public static function touch(int $id): void
    {
        $stmt = Database::connection()->prepare('UPDATE push_subscriptions SET last_used_at = NOW() WHERE id = ?');
        $stmt->execute([$id]);
    }

    /** Called on a 404/410 push-service response — an expired/revoked subscription is dead, never retried. */
    public static function delete(int $id): void
    {
        $stmt = Database::connection()->prepare('DELETE FROM push_subscriptions WHERE id = ?');
        $stmt->execute([$id]);
    }
}
