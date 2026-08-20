<?php

declare(strict_types=1);

// PHP built-in dev server only (`php -S ... public_html/index.php`): Apache's
// .htaccess already passes real files straight through in production, so
// this has no effect there — it just replicates that behavior for local dev.
if (PHP_SAPI === 'cli-server') {
    $requested = realpath(__DIR__ . parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));
    if ($requested !== false && str_starts_with($requested, __DIR__) && is_file($requested)) {
        return false;
    }
}

require dirname(__DIR__) . '/vendor/autoload.php';
require dirname(__DIR__) . '/app/config/config.php';

// Tech Spec §5: httponly + secure (HTTPS only, so off in local dev) + strict
// mode (rejects uninitialized session IDs, closing a session-fixation gap).
ini_set('session.use_strict_mode', '1');
session_name('dotttv_finance_session');
session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'httponly' => true,
    'secure' => APP_ENV !== 'development',
    'samesite' => 'Lax',
]);
session_start();

set_exception_handler(function (Throwable $e): void {
    if ($e instanceof ForbiddenException) {
        http_response_code(403);
        try {
            View::render('errors/403', ['title' => 'Access Denied']);
        } catch (Throwable) {
            echo '403 Forbidden';
        }
        return;
    }

    error_log($e->getMessage() . "\n" . $e->getTraceAsString());

    http_response_code(500);

    if (APP_ENV === 'development') {
        echo '<pre>' . htmlspecialchars((string) $e) . '</pre>';
        return;
    }

    $refId = bin2hex(random_bytes(4));
    error_log("Reference: {$refId}");
    echo "Something went wrong. Reference: {$refId}";
});

/** @var Router $router */
$router = require APP_ROOT . '/app/config/routes.php';

$router->dispatch($_SERVER['REQUEST_METHOD'], $_SERVER['REQUEST_URI']);
