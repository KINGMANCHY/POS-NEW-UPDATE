<?php
/**
 * login.php
 * -----------------------------------------------------------------------
 * Public entry point for the login page.
 * Redirects to the dashboard if already authenticated (or auto-logs-in
 * a returning visitor via a valid "remember me" cookie).
 */

define('POS_APP', true);
require_once __DIR__ . '/config/config.php';

SessionManager::start();
Security::applySecurityHeaders();

// Try silent remember-me login before deciding what to show
$loginController = new LoginController();
$loginController->attemptRememberLogin();

if (SessionManager::isLoggedIn()) {
    Helper::redirect('/index.php');
}

$csrfToken = Security::generateCsrfToken();
$pageTitle = 'Login';

require_once __DIR__ . '/views/login.php';
