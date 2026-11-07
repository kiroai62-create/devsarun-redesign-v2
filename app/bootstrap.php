<?php
declare(strict_types=1);

/*
 * Devs Arun — application bootstrap.
 * Every public page / API / admin page includes this file first.
 */

define('ROOT', dirname(__DIR__));
define('APP', ROOT . '/app');
define('STORAGE', ROOT . '/storage');

$configFile = APP . '/config.php';
$GLOBALS['CONFIG'] = is_file($configFile) ? require $configFile : [];

// Bundled vendor libraries (PHPMailer, Razorpay SDK). Shipped in /vendor so no Composer is needed on the server.
if (is_file(ROOT . '/vendor/autoload.php')) {
    require ROOT . '/vendor/autoload.php';
}

require APP . '/helpers.php';
require APP . '/db.php';

date_default_timezone_set((string)config('timezone', 'Asia/Kolkata'));
mb_internal_encoding('UTF-8');

// Base path (supports installs in a sub-folder). Can be forced in config.
if (config('base_path') !== null) {
    define('BASE_PATH', rtrim((string)config('base_path'), '/'));
} else {
    $docRoot = realpath((string)($_SERVER['DOCUMENT_ROOT'] ?? '')) ?: '';
    $rel = ($docRoot !== '' && str_starts_with(ROOT, $docRoot)) ? substr(ROOT, strlen($docRoot)) : '';
    define('BASE_PATH', rtrim(str_replace('\\', '/', $rel), '/'));
}

// Errors: never leak details in production.
$debug = (bool)config('debug', false);
ini_set('display_errors', $debug ? '1' : '0');
ini_set('log_errors', '1');
if (is_dir(STORAGE . '/logs') && is_writable(STORAGE . '/logs')) {
    ini_set('error_log', STORAGE . '/logs/php-error.log');
}
error_reporting(E_ALL);

set_exception_handler(static function (Throwable $e) use ($debug): void {
    error_log('[' . date('c') . '] ' . $e::class . ': ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
    if (!headers_sent()) {
        http_response_code(500);
    }
    $isJson = str_contains((string)($_SERVER['HTTP_ACCEPT'] ?? ''), 'application/json') || str_contains((string)($_SERVER['REQUEST_URI'] ?? ''), '/api/');
    if ($isJson) {
        if (!headers_sent()) {
            header('Content-Type: application/json');
        }
        echo json_encode(['ok' => false, 'error' => $debug ? $e->getMessage() : 'Something went wrong. Please try again.']);
    } else {
        echo $debug ? '<pre>' . e((string)$e) . '</pre>' : '<h1 style="font-family:sans-serif">Something went wrong.</h1><p style="font-family:sans-serif">Please try again in a moment.</p>';
    }
});

// Not installed yet → send to installer (except when we ARE the installer).
if (!is_file($configFile) && !defined('INSTALLER')) {
    header('Location: ' . BASE_PATH . '/install/');
    exit;
}

// Security headers.
if (!headers_sent()) {
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=(), payment=(self "https://checkout.razorpay.com" "https://api.razorpay.com")');
    header('Cross-Origin-Opener-Policy: same-origin-allow-popups');
    if (is_https()) {
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
    }
    header("Content-Security-Policy: default-src 'self'; "
        . "script-src 'self' https://checkout.razorpay.com https://*.razorpay.com https://cdn.jsdelivr.net; "
        . "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; "
        . "font-src 'self' https://fonts.gstatic.com data:; "
        . "img-src 'self' data: blob: https:; "
        . "connect-src 'self' https://*.razorpay.com; "
        . "frame-src https://*.razorpay.com; "
        . "form-action 'self'; base-uri 'self'; object-src 'none'; frame-ancestors 'self'");
}

// Session hardening.
if (session_status() === PHP_SESSION_NONE && PHP_SAPI !== 'cli') {
    session_name('da_sid');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => BASE_PATH === '' ? '/' : BASE_PATH . '/',
        'secure' => is_https(),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.gc_maxlifetime', '7200');
    session_start();
}

// Automatic, idempotent upgrades for existing installs.
require APP . '/upgrade.php';
if (is_file($configFile) && (int)setting('schema_version', '1') < SCHEMA_VERSION) {
    try {
        run_upgrades(db());
        settings_all(true);
    } catch (Throwable $e) {
        error_log('Upgrade failed: ' . $e->getMessage());
    }
}

require APP . '/auth.php';
require APP . '/mailer.php';
require APP . '/razorpay.php';
require APP . '/ai.php';
require APP . '/analytics.php';
require APP . '/content.php';
require APP . '/views.php';
