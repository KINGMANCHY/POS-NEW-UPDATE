<?php
/**
 * app/controllers/LoginController.php
 * -----------------------------------------------------------------------
 * Handles the AJAX login request from views/login.php + assets/js/login.js
 * -----------------------------------------------------------------------
 * Flow:
 *  1. Validate request method + CSRF token
 *  2. Sanitize + validate input
 *  3. Look up user, check active/locked status
 *  4. Verify password (timing-safe via password_verify)
 *  5. Reset failed attempts / update last login on success
 *  6. Optionally issue a "remember me" cookie
 *  7. Log the attempt (success or failure) to LoginLogs + ActivityLogs
 *  8. Return JSON so the AJAX layer can redirect the browser
 */

// This file doubles as the AJAX endpoint the login form posts to. When it's
// requested directly by the browser, POS_APP isn't defined yet - bootstrap
// the app FIRST so the guard below (and everything else) has what it needs,
// and so a direct hit always ends in a JSON response, never plain text.
if (!defined('POS_APP') && basename($_SERVER['SCRIPT_FILENAME']) === basename(__FILE__)) {
    require_once dirname(__DIR__, 2) . '/config/config.php';
}

if (!defined('POS_APP')) {
    die('Direct access not permitted.');
}

class LoginController
{
    private User $userModel;

    public function __construct()
    {
        $this->userModel = new User();
    }

    public function handleLogin(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            Helper::jsonResponse(false, 'Invalid request method.', [], 405);
        }

        // ---- CSRF check ------------------------------------------------
        Security::requireValidCsrf($_POST[CSRF_TOKEN_NAME] ?? null);

        // ---- Sanitize + validate input ---------------------------------
        $username = Security::sanitize(trim($_POST['username'] ?? ''));
        $password = (string) ($_POST['password'] ?? ''); // never sanitize/escape passwords - hash the raw value
        $rememberMe = !empty($_POST['remember_me']);

        $ip = Helper::getClientIp();
        $userAgent = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255);

        if ($username === '' || $password === '') {
            Helper::jsonResponse(false, 'Username and password are required.', [], 422);
        }

        if (!Security::isValidUsername($username)) {
            Helper::jsonResponse(false, 'Invalid username format.', [], 422);
        }

        // ---- Look up user ------------------------------------------------
        $user = $this->userModel->findByUsername($username);

        if (!$user) {
            // Same generic message as a wrong-password failure -
            // never reveal whether the username exists.
            $this->userModel->logLoginAttempt(null, $username, false, $ip, $userAgent);
            Helper::jsonResponse(false, 'Invalid username or password.', [], 401);
        }

        // ---- Account status checks ---------------------------------------
        if (!$user['is_active']) {
            $this->userModel->logLoginAttempt((int) $user['user_id'], $username, false, $ip, $userAgent);
            Helper::jsonResponse(false, 'This account has been deactivated. Contact your administrator.', [], 403);
        }

        if ($this->userModel->isLocked($user)) {
            $this->userModel->logLoginAttempt((int) $user['user_id'], $username, false, $ip, $userAgent);
            Helper::jsonResponse(false, 'Account temporarily locked due to multiple failed attempts. Try again later.', [], 423);
        }

        // Branch users cannot log in when their branch is disabled.
        if (!empty($user['branch_id']) && empty($user['branch_is_active'])) {
            $this->userModel->logLoginAttempt((int) $user['user_id'], $username, false, $ip, $userAgent);
            Helper::jsonResponse(false, 'This branch has been disabled. Contact your administrator.', [], 403);
        }

        // ---- Password verification ----------------------------------------
        if (!$this->userModel->verifyPassword($password, $user['password_hash'])) {
            $this->userModel->registerFailedAttempt((int) $user['user_id'], (int) $user['failed_attempts']);
            $this->userModel->logLoginAttempt((int) $user['user_id'], $username, false, $ip, $userAgent);

            $remaining = max(0, MAX_LOGIN_ATTEMPTS - ((int) $user['failed_attempts'] + 1));
            $msg = $remaining > 0
                ? "Invalid username or password. {$remaining} attempt(s) remaining before lockout."
                : 'Invalid username or password. Your account has now been locked.';

            Helper::jsonResponse(false, $msg, [], 401);
        }

        // ---- Success -----------------------------------------------------
        $this->userModel->resetFailedAttempts((int) $user['user_id']);
        $this->userModel->rehashPasswordIfNeeded((int) $user['user_id'], $password, $user['password_hash']);
        $this->userModel->updateLastLogin((int) $user['user_id'], $ip);
        $this->userModel->logLoginAttempt((int) $user['user_id'], $username, true, $ip, $userAgent);
        $this->userModel->logActivity((int) $user['user_id'], 'LOGIN', 'User logged in successfully.');

        // Regenerate session ID on privilege change (login) - mitigates fixation
        session_regenerate_id(true);

        $_SESSION['user_id']    = (int) $user['user_id'];
        $_SESSION['username']   = $user['username'];
        $_SESSION['full_name']  = $user['full_name'];
        $_SESSION['role_id']    = (int) $user['role_id'];
        $_SESSION['role_name']  = $user['role_name'];
        $_SESSION['branch_id']  = !empty($user['branch_id']) ? (int) $user['branch_id'] : null;
        $_SESSION['branch_code'] = $user['branch_code'] ?? null;
        $_SESSION['branch_name'] = $user['branch_name'] ?? null;
        $_SESSION['last_activity'] = time();
        $_SESSION['created_at']    = time();
        // Fresh CSRF token for the authenticated session
        unset($_SESSION[CSRF_TOKEN_NAME]);
        Security::generateCsrfToken();

        if ($rememberMe) {
            $tokenValue = $this->userModel->createRememberToken((int) $user['user_id']);
            setcookie(REMEMBER_ME_COOKIE, $tokenValue, [
                'expires'  => time() + REMEMBER_ME_LIFETIME,
                'path'     => '/',
                'secure'   => !empty($_SERVER['HTTPS']),
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
        }

        Helper::jsonResponse(true, 'Login successful. Redirecting...', [
            'redirect' => APP_URL . '/index.php',
        ]);
    }

    public function handleLogout(): void
    {
        if (!empty($_COOKIE[REMEMBER_ME_COOKIE])) {
            [$selector] = array_pad(explode(':', $_COOKIE[REMEMBER_ME_COOKIE], 2), 1, '');
            if ($selector !== '') {
                $this->userModel->deleteRememberToken($selector);
            }
            setcookie(REMEMBER_ME_COOKIE, '', time() - 3600, '/');
        }

        if (!empty($_SESSION['user_id'])) {
            $this->userModel->logActivity((int) $_SESSION['user_id'], 'LOGOUT', 'User logged out.');
        }

        SessionManager::destroy();
        Helper::redirect('/login.php');
    }

    /**
     * Attempts to auto-login a returning visitor via their remember-me
     * cookie. Called from index.php/login.php before checking the session.
     */
    public function attemptRememberLogin(): void
    {
        if (SessionManager::isLoggedIn() || empty($_COOKIE[REMEMBER_ME_COOKIE])) {
            return;
        }

        $parts = explode(':', $_COOKIE[REMEMBER_ME_COOKIE], 2);
        if (count($parts) !== 2) {
            return;
        }
        [$selector, $validator] = $parts;

        $tokenRow = $this->userModel->findByRememberSelector($selector);
        if (!$tokenRow) {
            return;
        }

        if (!hash_equals($tokenRow['validator_hash'], hash('sha256', $validator))) {
            // Possible token theft - invalidate it defensively
            $this->userModel->deleteRememberToken($selector);
            return;
        }

        $user = $this->userModel->findById((int) $tokenRow['user_id']);
        if (!$user || !$user['is_active']) {
            return;
        }

        session_regenerate_id(true);
        $_SESSION['user_id']   = (int) $user['user_id'];
        $_SESSION['username']  = $user['username'];
        $_SESSION['full_name'] = $user['full_name'];
        $_SESSION['role_id']   = (int) $user['role_id'];
        $_SESSION['role_name'] = $user['role_name'];
        $_SESSION['branch_id']  = !empty($user['branch_id']) ? (int) $user['branch_id'] : null;
        $_SESSION['branch_code'] = $user['branch_code'] ?? null;
        $_SESSION['branch_name'] = $user['branch_name'] ?? null;
        $_SESSION['last_activity'] = time();
        $_SESSION['created_at']    = time();

        // Rotate the token (one-time use pattern) for better security
        $this->userModel->deleteRememberToken($selector);
        $newTokenValue = $this->userModel->createRememberToken((int) $user['user_id']);
        setcookie(REMEMBER_ME_COOKIE, $newTokenValue, [
            'expires'  => time() + REMEMBER_ME_LIFETIME,
            'path'     => '/',
            'secure'   => !empty($_SERVER['HTTPS']),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }
}

// ---------------------------------------------------------------------
// Front-controller style dispatch for this file's own AJAX endpoint.
// views/login.php + assets/js/login.js POST here directly.
// ---------------------------------------------------------------------
if (basename($_SERVER['SCRIPT_FILENAME']) === basename(__FILE__)) {
    SessionManager::start();
    $controller = new LoginController();
    $controller->handleLogin();
}
