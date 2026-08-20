<?php
/**
 * app/helpers/Helper.php
 * -----------------------------------------------------------------------
 * Small, reusable helper functions used across controllers and views.
 */

if (!defined('POS_APP')) {
    die('Direct access not permitted.');
}

class Helper
{
    /**
     * Sends a standard JSON response and stops execution.
     */
    public static function jsonResponse(bool $success, string $message = '', array $data = [], int $httpCode = 200): void
    {
        // Discard any stray output that leaked out before this point (PHP
        // notices/warnings, accidental whitespace, etc.) - see the
        // ob_start() call and comment in config/config.php for why. This
        // guarantees the response body is ONLY ever the JSON below, so a
        // 200 status always means valid, parseable JSON on the other end.
        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        http_response_code($httpCode);
        header('Content-Type: application/json; charset=utf-8');

        // JSON_INVALID_UTF8_SUBSTITUTE: if a string field somewhere in
        // $data contains a byte sequence that isn't valid UTF-8 (has
        // happened with PDO_SQLSRV data depending on collation/driver
        // version even with UTF-8 encoding requested), json_encode()
        // normally just fails outright and returns false - which would
        // otherwise mean sending an EMPTY 200 response, the exact same
        // "looks fine, isn't actually JSON" failure this whole function
        // exists to prevent. Substitute the bad bytes instead of failing.
        $json = json_encode(array_merge([
            'success' => $success,
            'message' => $message,
        ], $data), JSON_INVALID_UTF8_SUBSTITUTE);

        if ($json === false) {
            // Should be unreachable given the substitute flag above, but
            // never send a broken/empty body if it somehow still happens.
            error_log('Helper::jsonResponse - json_encode failed: ' . json_last_error_msg());
            http_response_code(500);
            $json = json_encode(['success' => false, 'message' => 'A server error occurred while preparing the response.']);
        }

        echo $json;
        exit;
    }

    /**
     * Builds an asset URL with a cache-busting version query string
     * based on the file's last-modified time, so a browser that
     * cached an old .js/.css file automatically fetches the new one
     * the moment the file actually changes on the server - no manual
     * version bump, no "hard refresh and hope" needed after a deploy.
     * $relativePath is relative to /assets, e.g. '/js/pos.js'.
     */
    public static function versionedAsset(string $relativePath): string
    {
        $fullPath = BASE_PATH . '/assets' . $relativePath;
        $version = file_exists($fullPath) ? filemtime($fullPath) : APP_VERSION;
        return ASSETS_URL . $relativePath . '?v=' . $version;
    }

    /**
     * Returns the real client IP, accounting for common proxy headers.
     * (Falls back safely - never trust this blindly for security decisions
     * without a trusted proxy layer in front.)
     */
    public static function getClientIp(): string
    {
        foreach (['HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR'] as $key) {
            if (!empty($_SERVER[$key])) {
                foreach (explode(',', (string) $_SERVER[$key]) as $candidate) {
                    $ip = trim($candidate);
                    if (filter_var($ip, FILTER_VALIDATE_IP)) {
                        return $ip;
                    }
                }
            }
        }
        return '0.0.0.0';
    }

    public static function redirect(string $path): void
    {
        header('Location: ' . APP_URL . $path);
        exit;
    }

    public static function formatCurrency(float $amount, string $symbol = '₱'): string
    {
        return $symbol . number_format($amount, 2);
    }

    public static function formatDate(string $datetime, string $format = 'M d, Y h:i A'): string
    {
        $ts = strtotime($datetime);
        return $ts ? date($format, $ts) : '';
    }

    /**
     * Generates a POS-style reference/code, e.g. for products or
     * transactions: PREFIX-YYYYMMDD-XXXX
     */
    public static function generateCode(string $prefix): string
    {
        return strtoupper($prefix) . '-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(3)));
    }

    /**
     * Very small flash-message helper using the session.
     */
    public static function setFlash(string $type, string $message): void
    {
        $_SESSION['flash'][$type] = $message;
    }

    public static function getFlash(string $type): ?string
    {
        if (!empty($_SESSION['flash'][$type])) {
            $message = $_SESSION['flash'][$type];
            unset($_SESSION['flash'][$type]);
            return $message;
        }
        return null;
    }
}
