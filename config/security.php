<?php
/**
 * config/security.php
 * -----------------------------------------------------------------------
 * Security Utilities: CSRF Protection, XSS Filtering, Sanitization
 * -----------------------------------------------------------------------
 */

if (!defined('POS_APP')) {
    die('Direct access not permitted.');
}

class Security
{
    // -------------------------------------------------------------
    // CSRF Protection
    // -------------------------------------------------------------

    /**
     * Generates (or returns the existing) CSRF token for this session.
     */
    public static function generateCsrfToken(): string
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            SessionManager::start();
        }

        if (empty($_SESSION[CSRF_TOKEN_NAME])) {
            $_SESSION[CSRF_TOKEN_NAME] = bin2hex(random_bytes(32));
        }

        return $_SESSION[CSRF_TOKEN_NAME];
    }

    /**
     * Validates a token submitted by the client using a timing-safe
     * comparison. Returns true only if it matches the session token.
     */
    public static function verifyCsrfToken(?string $token): bool
    {
        if (empty($token) || empty($_SESSION[CSRF_TOKEN_NAME])) {
            return false;
        }
        return hash_equals($_SESSION[CSRF_TOKEN_NAME], $token);
    }

    /**
     * Reads the submitted CSRF token from wherever the caller put it:
     * the X-CSRF-Token header (auto-attached by assets/js/app.js to every
     * AJAX call), a POST field, or a GET field - in that order.
     */
    public static function getSubmittedCsrfToken(): ?string
    {
        return $_SERVER['HTTP_X_CSRF_TOKEN']
            ?? $_POST[CSRF_TOKEN_NAME]
            ?? $_GET[CSRF_TOKEN_NAME]
            ?? null;
    }

    /**
     * Convenience guard for controllers: kills the request with a 403
     * JSON response if the CSRF token is missing/invalid.
     */
    public static function requireValidCsrf(?string $token): void
    {
        if (!self::verifyCsrfToken($token)) {
            http_response_code(403);
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Invalid or expired security token. Please refresh the page.']);
            exit;
        }
    }

    /**
     * Same as requireValidCsrf(), but pulls the token from wherever the
     * request put it: the X-CSRF-Token header (sent automatically by
     * assets/js/app.js on every AJAX call) or a csrf_token POST field
     * (used for multipart/form-data requests like image uploads, where
     * app.js's JSON header still applies but a form field is easiest
     * for FormData-based submits).
     */
    public static function requireValidCsrfFromRequest(): void
    {
        $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($_POST[CSRF_TOKEN_NAME] ?? null);
        self::requireValidCsrf($token);
    }

    // -------------------------------------------------------------
    // XSS Protection
    // -------------------------------------------------------------

    /**
     * Escapes a single value for safe HTML output.
     */
    public static function escape($value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    /**
     * Recursively strips tags / escapes an array or scalar of raw input.
     * Use this on incoming $_POST / $_GET data before it touches
     * business logic or gets stored.
     */
    public static function sanitize($input)
    {
        if (is_array($input)) {
            return array_map([self::class, 'sanitize'], $input);
        }

        if (is_string($input)) {
            $input = trim($input);
            $input = strip_tags($input);
            $input = htmlspecialchars($input, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        }

        return $input;
    }

    // -------------------------------------------------------------
    // Validation helpers
    // -------------------------------------------------------------

    public static function isValidUsername(string $username): bool
    {
        // Letters, numbers, dot, underscore, dash, 3-50 chars
        return (bool) preg_match('/^[a-zA-Z0-9._-]{3,50}$/', $username);
    }

    public static function isStrongPassword(string $password): bool
    {
        // Minimum 8 chars, at least one letter and one number
        return strlen($password) >= 8
            && preg_match('/[A-Za-z]/', $password)
            && preg_match('/[0-9]/', $password);
    }

    // -------------------------------------------------------------
    // Security headers - call once, early, on every request
    // -------------------------------------------------------------
    public static function applySecurityHeaders(): void
    {
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: SAMEORIGIN');
        header('X-XSS-Protection: 1; mode=block');
        header('Referrer-Policy: strict-origin-when-cross-origin');
        // Adjust CSP as new asset sources are added in later phases
        header("Content-Security-Policy: default-src 'self'; script-src 'self' https://cdn.jsdelivr.net https://code.jquery.com 'unsafe-inline'; style-src 'self' https://cdn.jsdelivr.net 'unsafe-inline'; img-src 'self' data:; font-src 'self' https://cdn.jsdelivr.net;");
    }
}
