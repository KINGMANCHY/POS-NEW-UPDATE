<?php
/**
 * config/config.php
 * -----------------------------------------------------------------------
 * POS STORE v1.0 - Global Application Configuration
 * -----------------------------------------------------------------------
 * Central place for constants used across the application.
 * Everything here is environment-level configuration only - no business
 * logic lives in this file.
 */

// Output buffering, started as the very first thing this app does.
// Why: every AJAX endpoint in this app must respond with clean JSON.
// If ANY code between here and Helper::jsonResponse() prints so much as
// one stray character - a PHP notice/warning/deprecation message (very
// common: PDO_SQLSRV data quirks, an unguarded array key, etc.), a BOM,
// even just whitespace - that text lands in the response BEFORE the
// JSON does. The HTTP status is still a perfectly fine 200, but the
// body is no longer valid JSON, so the browser's fetch/jQuery call
// fails to parse it and everything downstream looks like "the server
// is broken" with no useful clue why. Helper::jsonResponse() discards
// this buffer's contents right before sending the real response, so
// stray output gets silently dropped instead of corrupting the payload.
ob_start();

// Prevent direct access to this file
if (!defined('POS_APP')) {
    define('POS_APP', true);
}

// -------------------------------------------------------------------
// Environment
// -------------------------------------------------------------------
// Set to 'development' while building, 'production' when you deploy.
// In production, error_reporting is silenced and errors are only logged.
define('APP_ENV', 'production');

if (APP_ENV === 'development') {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
} else {
    error_reporting(0);
    ini_set('display_errors', '0');
}

// Always log errors to a file, regardless of environment
ini_set('log_errors', '1');
ini_set('error_log', __DIR__ . '/../logs/php_error.log');

// -------------------------------------------------------------------
// Application info
// -------------------------------------------------------------------
define('APP_NAME', 'POS STORE');
define('APP_VERSION', '1.1.9
    ');
define('APP_TIMEZONE', 'Asia/Manila');
date_default_timezone_set(APP_TIMEZONE);

// -------------------------------------------------------------------
// Paths / URLs
// -------------------------------------------------------------------
// BASE_PATH -> absolute filesystem path to the project root
define('BASE_PATH', dirname(__DIR__));

// BASE_URL -> change this to match your server/virtual host setup.
// Example: if the app lives at http://localhost/pos_store, keep '/pos_store'
// If it lives at the web root, set this to '' (empty string).
define('BASE_URL', '/pos_store');

// Protocol detection: $_SERVER['HTTPS'] only reflects the connection PHP
// itself sees. Behind a reverse proxy or tunnel (Tailscale Funnel/Serve,
// nginx, a load balancer, ngrok, etc.) that terminates HTTPS and forwards
// to this app over plain HTTP internally, that check alone is wrong - it
// reports 'http' even though the browser is actually on 'https'. That
// mismatch breaks EVERYTHING: every asset URL and every AJAX endpoint
// built from APP_URL comes out as http://, and loading http:// resources
// from an https:// page is "mixed content" - browsers block it outright
// (not just a warning), which looks exactly like a totally unstyled page
// (stylesheet blocked) and search/scan/every AJAX call doing nothing
// (blocked too). X-Forwarded-Proto is the standard header a reverse
// proxy sets to say what protocol the client actually used - trust it
// when present.
$isHttps = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== '' && strtolower($_SERVER['HTTPS']) !== 'off')
    || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower(trim(explode(',', $_SERVER['HTTP_X_FORWARDED_PROTO'])[0])) === 'https')
    || (isset($_SERVER['HTTP_X_FORWARDED_SSL']) && strtolower($_SERVER['HTTP_X_FORWARDED_SSL']) === 'on')
    || (isset($_SERVER['SERVER_PORT']) && (int) $_SERVER['SERVER_PORT'] === 443);

define('APP_URL', ($isHttps ? 'https://' : 'http://') . ($_SERVER['HTTP_HOST'] ?? 'localhost') . BASE_URL);

// Exposed so other bootstrap files (session.php's cookie 'secure' flag,
// in particular) use this SAME proxy-aware check instead of each
// re-implementing (and potentially getting wrong) their own.
define('IS_HTTPS', $isHttps);

define('ASSETS_URL', APP_URL . '/assets');

// -------------------------------------------------------------------
// Database (SQL Server)
// -------------------------------------------------------------------
define('DB_DRIVER', 'sqlsrv');
define('DB_HOST', 'localhost\\SQLEXPRESS');      // SQL Server host / instance, e.g. 'localhost\SQLEXPRESS'
define('DB_PORT', '1433');
define('DB_NAME', 'pos_store');
define('DB_USER', 'SQLUSER');             // Change for production - never use sa in production
define('DB_PASS', 'BOOMPANES');
define('DB_CHARSET', 'UTF-8');
// SQL Server 2014 installations commonly use drivers/instances that do not
// negotiate TLS by default. Keep encryption configurable; production servers
// with a trusted certificate can set this to 'yes'.
define('DB_ENCRYPT', 'no');
define('DB_TRUST_SERVER_CERTIFICATE', 'yes');

// -------------------------------------------------------------------
// Session
// -------------------------------------------------------------------
define('SESSION_NAME', 'POS_STORE_SESSID');
define('SESSION_LIFETIME', 30 * 60);      // 30 minutes idle timeout (seconds)
define('SESSION_REGENERATE_INTERVAL', 5 * 60); // regenerate session id every 5 minutes
define('REMEMBER_ME_LIFETIME', 30 * 24 * 60 * 60); // 30 days (seconds)
define('REMEMBER_ME_COOKIE', 'pos_remember_token');

// -------------------------------------------------------------------
// Security
// -------------------------------------------------------------------
define('CSRF_TOKEN_NAME', 'csrf_token');
define('MAX_LOGIN_ATTEMPTS', 5);
define('ACCOUNT_LOCK_MINUTES', 15);
define('PASSWORD_HASH_ALGO', PASSWORD_BCRYPT);
define('PASSWORD_HASH_OPTIONS', ['cost' => 12]);

// -------------------------------------------------------------------
// Uploads
// -------------------------------------------------------------------
define('UPLOAD_PATH', BASE_PATH . '/assets/uploads/products/');
define('UPLOAD_URL', ASSETS_URL . '/uploads/products/');
define('MAX_UPLOAD_SIZE', 2 * 1024 * 1024); // 2MB
define('ALLOWED_IMAGE_TYPES', ['image/jpeg', 'image/png', 'image/webp']);

// -------------------------------------------------------------------
// Autoload core classes (simple PSR-4-ish autoloader for Phase 1)
// -------------------------------------------------------------------
spl_autoload_register(function ($class) {
    $directories = [
        BASE_PATH . '/app/controllers/',
        BASE_PATH . '/app/models/',
        BASE_PATH . '/app/helpers/',
    ];

    foreach ($directories as $dir) {
        $file = $dir . $class . '.php';
        if (file_exists($file)) {
            require_once $file;
            return;
        }
    }
});

// Core requires that are not classes (procedural helpers, must load explicitly)
require_once BASE_PATH . '/config/database.php';
require_once BASE_PATH . '/config/security.php';
require_once BASE_PATH . '/config/session.php';
require_once BASE_PATH . '/config/email.php';
