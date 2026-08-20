<?php
/**
 * logout.php
 * -----------------------------------------------------------------------
 * Destroys the session, clears remember-me token/cookie, and redirects
 * back to the login page.
 */

define('POS_APP', true);
require_once __DIR__ . '/config/config.php';

SessionManager::start();

$controller = new LoginController();
$controller->handleLogout(); // this method calls exit via Helper::redirect()
