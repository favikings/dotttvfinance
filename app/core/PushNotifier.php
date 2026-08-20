<?php

declare(strict_types=1);

use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;

/**
 * Web Push per Tech Spec §15a — a SECOND, parallel notification channel
 * alongside EmailNotifier (Prompt 2.6), fired at the same trigger events:
 * expense enters a queue, expense rejected, top-up approved/rejected.
 *
 * A push payload is a nudge with a deep link only — {title, body, url} — it
 * must NEVER carry enough to approve/reject directly; tapping it just opens
 * the app, where the normal Permission::require()-gated flow takes over.
 *
 * send() is the primitive the spec names directly: fetch a user's
 * subscriptions, send via minishlink/web-push, delete the row on a 404/410
 * (expired/revoked — never retried again), log anything else to
 * storage/logs/push.log without throwing (same non-blocking discipline as
 * EmailNotifier — a push failure never blocks the underlying action).
 * expensePendingApproval()/expenseRejected()/topupDecided() mirror
 * EmailNotifier's trigger-event methods, fanning out to every recipient via
 * send() so controllers can call both notifiers side by side.
 */
class PushNotifier
{
    public static function expensePendingApproval(array $expense, string $roleName): void
    {
        $title = "Expense {$expense['expense_no']} awaiting your approval";
        $body  = "{$expense['payee']} — " . naira($expense['amount']);
        $url   = url('/approvals');

        foreach (User::activeByRole($roleName) as $approver) {
            self::send((int) $approver['id'], $title, $body, $url);
        }
    }

    public static function expenseRejected(array $expense): void
    {
        if (empty($expense['created_by'])) {
            return;
        }

        $title = "Expense {$expense['expense_no']} was rejected";
        $body  = 'Reason: ' . ($expense['rejected_reason'] ?? '—');
        $url   = url('/expenses');

        self::send((int) $expense['created_by'], $title, $body, $url);
    }

    public static function topupDecided(array $topup, TopupStatus $status): void
    {
        if (empty($topup['requested_by'])) {
            return;
        }

        $verb  = $status === TopupStatus::Approved ? 'approved' : 'rejected';
        $title = "Fund top-up request {$verb}";
        $body  = naira($topup['amount']) . ' on ' . $topup['date'];
        $url   = url('/fund-topups');

        self::send((int) $topup['requested_by'], $title, $body, $url);
    }

    /**
     * Fetches all subscriptions for $userId and sends the same {title, body,
     * url} payload to each. Tech Spec §15a's exact signature.
     */
    public static function send(int $userId, string $title, string $body, string $url): void
    {
        if (VAPID_PUBLIC_KEY === '' || VAPID_PRIVATE_KEY === '') {
            self::logFailure($userId, $title, 'VAPID keys are not configured (.env VAPID_* values missing).');
            return;
        }

        $subscriptions = PushSubscription::forUser($userId);
        if ($subscriptions === []) {
            return;
        }

        try {
            $webPush = new WebPush([
                'VAPID' => [
                    'subject'    => 'mailto:' . (SMTP_FROM_EMAIL !== '' ? SMTP_FROM_EMAIL : 'finance@dotttv.tv'),
                    'publicKey'  => VAPID_PUBLIC_KEY,
                    'privateKey' => VAPID_PRIVATE_KEY,
                ],
            ]);

            $payload = json_encode(['title' => $title, 'body' => $body, 'url' => $url], JSON_UNESCAPED_SLASHES);

            foreach ($subscriptions as $row) {
                $webPush->queueNotification(
                    Subscription::create([
                        'endpoint' => $row['endpoint'],
                        'keys'     => ['p256dh' => $row['p256dh_key'], 'auth' => $row['auth_key']],
                    ]),
                    $payload
                );
            }

            foreach ($webPush->flush() as $report) {
                $subscriptionId = self::idForEndpoint($subscriptions, $report->getEndpoint());
                if ($subscriptionId === null) {
                    continue;
                }

                if ($report->isSuccess()) {
                    PushSubscription::touch($subscriptionId);
                } elseif ($report->isSubscriptionExpired()) {
                    PushSubscription::delete($subscriptionId);
                } else {
                    self::logFailure($userId, $title, $report->getReason() . ' (' . $report->getEndpoint() . ')');
                }
            }
        } catch (Throwable $e) {
            self::logFailure($userId, $title, $e->getMessage());
        }
    }

    private static function idForEndpoint(array $subscriptions, string $endpoint): ?int
    {
        foreach ($subscriptions as $row) {
            if ($row['endpoint'] === $endpoint) {
                return (int) $row['id'];
            }
        }
        return null;
    }

    private static function logFailure(int $userId, string $title, string $reason): void
    {
        $line = sprintf(
            "[%s] user_id=%d title=\"%s\" error=%s\n",
            date('Y-m-d H:i:s'),
            $userId,
            $title,
            $reason
        );
        @file_put_contents(LOGS_PATH . '/push.log', $line, FILE_APPEND | LOCK_EX);
    }
}
