<?php

declare(strict_types=1);

use Dotenv\Dotenv;

$root = dirname(__DIR__, 2);

// Convention-based autoloader for app/ classes: filename === class name,
// no namespaces. Deliberately not Composer classmap — a classmap has to be
// regenerated (`composer dump-autoload`) every time a new controller/model
// file is added, which is exactly the kind of framework friction Tech Spec
// §2 says this project is avoiding. This just looks in the three folders
// that hold PHP classes.
spl_autoload_register(function (string $class) use ($root): void {
    foreach (['app/core', 'app/controllers', 'app/models'] as $dir) {
        $file = "{$root}/{$dir}/{$class}.php";
        if (is_file($file)) {
            require $file;
            return;
        }
    }
});

Dotenv::createImmutable($root)->safeLoad();

function env(string $key, mixed $default = null): mixed
{
    $value = $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key);

    if ($value === false || $value === null) {
        return $default;
    }

    return match (strtolower((string) $value)) {
        'true' => true,
        'false' => false,
        'null' => null,
        default => $value,
    };
}

define('APP_ROOT', $root);
define('APP_ENV', env('APP_ENV', 'production'));
define('APP_NAME', env('APP_NAME', 'DOTT TV Finance'));
define('APP_SECRET', env('APP_SECRET', ''));
define('APP_URL', rtrim((string) env('APP_URL', ''), '/'));

define('DB_HOST', env('DB_HOST', '127.0.0.1'));
define('DB_PORT', (int) env('DB_PORT', 3306));
define('DB_NAME', env('DB_NAME', ''));
define('DB_USER', env('DB_USER', ''));
define('DB_PASS', env('DB_PASS', ''));

define('SMTP_HOST', env('SMTP_HOST', ''));
define('SMTP_PORT', (int) env('SMTP_PORT', 587));
define('SMTP_USER', env('SMTP_USER', ''));
define('SMTP_PASS', env('SMTP_PASS', ''));
define('SMTP_FROM_EMAIL', env('SMTP_FROM_EMAIL', ''));
define('SMTP_FROM_NAME', env('SMTP_FROM_NAME', APP_NAME));

define('VAPID_PUBLIC_KEY', env('VAPID_PUBLIC_KEY', ''));
define('VAPID_PRIVATE_KEY', env('VAPID_PRIVATE_KEY', ''));

define('SESSION_IDLE_TIMEOUT_MINUTES_DEFAULT', (int) env('SESSION_IDLE_TIMEOUT_MINUTES', 30));

define('STORAGE_PATH', $root . '/storage');
define('UPLOADS_PATH', STORAGE_PATH . '/uploads');
define('LOGS_PATH', STORAGE_PATH . '/logs');

// Production (Tech Spec §20) is a subfolder deploy: dotttv.tv/dotttvfinance,
// with the root .htaccess transparently forwarding into public_html/ so the
// URL never shows /public_html/. That means SCRIPT_NAME (the physical
// script, e.g. "/dotttvfinance/public_html/index.php") and REQUEST_URI (what
// the browser actually asked for, e.g. "/dotttvfinance/foo") disagree on the
// base path once the forward is in play — SCRIPT_NAME alone would produce
// "/dotttvfinance/public_html" and every route/link would 404. Reconcile the
// two: start from SCRIPT_NAME's directory, and if it ends in "/public_html"
// but the ORIGINAL request path doesn't actually contain "/public_html/" at
// that position, the request arrived via the forward — trim that segment
// off so BASE_PATH matches what's actually in the browser's address bar.
// (Direct access to .../public_html/... — e.g. local testing without the
// forward — is unaffected: REQUEST_URI still contains "/public_html/" there,
// so the trim is skipped and BASE_PATH stays the physical directory.)
define('BASE_PATH', (static function (): string {
    if (PHP_SAPI === 'cli' || !isset($_SERVER['SCRIPT_NAME'])) {
        return '';
    }

    $scriptDir = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'])), '/');

    if ($scriptDir !== '' && str_ends_with($scriptDir, '/public_html')) {
        $requestPath = rtrim(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '', '/');
        if (!str_starts_with($requestPath, $scriptDir)) {
            $scriptDir = substr($scriptDir, 0, -strlen('/public_html'));
        }
    }

    return $scriptDir;
})());

function url(string $path = ''): string
{
    return BASE_PATH . '/' . ltrim($path, '/');
}

// Shared view partials (UI Component Guide §5/§6/§11) — plain functions,
// not autoloaded classes, so they're required once here rather than per view.
require_once $root . '/app/views/partials/status_badge.php';
require_once $root . '/app/views/partials/metric_card.php';
require_once $root . '/app/views/partials/settings_tabs.php';
require_once $root . '/app/views/partials/flash.php';
require_once $root . '/app/views/partials/role_badge.php';
require_once $root . '/app/views/partials/money.php';
require_once $root . '/app/views/partials/naira_pdf.php';
require_once $root . '/app/views/partials/backfilled_badge.php';
require_once $root . '/app/views/partials/historical_tabs.php';
require_once $root . '/app/views/partials/audit_diff_view.php';
require_once $root . '/app/views/partials/notification_banner.php';
require_once $root . '/app/views/partials/report_tabs.php';
require_once $root . '/app/views/partials/date_range_filter.php';
require_once $root . '/app/views/partials/install_banner.php';
require_once $root . '/app/views/partials/sync_indicator.php';

error_reporting(E_ALL);
ini_set('display_errors', APP_ENV === 'development' ? '1' : '0');
ini_set('log_errors', '1');
ini_set('error_log', LOGS_PATH . '/php_errors.log');

date_default_timezone_set('Africa/Lagos');
