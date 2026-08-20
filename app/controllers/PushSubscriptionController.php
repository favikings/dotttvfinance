<?php

declare(strict_types=1);

/**
 * POST /push/subscribe (Tech Spec §15a step 3): the client-side opt-in flow
 * posts the browser's PushSubscription object here after a successful
 * `pushManager.subscribe()`. Any authenticated user can subscribe their own
 * device — there's no module permission for this, just a valid session
 * (already enforced by Router::dispatch() before this action runs).
 */
class PushSubscriptionController
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

    public function subscribe(): void
    {
        $user = Auth::user();

        $input = $this->jsonInput();
        if (!Csrf::verify($input['_csrf'] ?? null)) {
            $this->json(['ok' => false, 'message' => 'Your session expired. Please try again.'], 419);
            return;
        }

        $endpoint = trim((string) ($input['endpoint'] ?? ''));
        $p256dh   = trim((string) ($input['keys']['p256dh'] ?? ''));
        $authKey  = trim((string) ($input['keys']['auth'] ?? ''));

        if ($endpoint === '' || $p256dh === '' || $authKey === '') {
            $this->json(['ok' => false, 'message' => 'Invalid subscription payload.'], 422);
            return;
        }

        PushSubscription::upsert(
            (int) $user['id'],
            $endpoint,
            $p256dh,
            $authKey,
            substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255)
        );

        $this->json(['ok' => true]);
    }
}
