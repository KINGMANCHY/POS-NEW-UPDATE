<?php
/** Public email-password-recovery endpoint. */
if (!defined('POS_APP') && basename($_SERVER['SCRIPT_FILENAME']) === basename(__FILE__)) {
    require_once dirname(__DIR__, 2) . '/config/config.php';
}
if (!defined('POS_APP')) die('Direct access not permitted.');

class PasswordResetController
{
    private User $users;
    private Setting $settings;
    public function __construct() { $this->users = new User(); $this->settings = new Setting(); }
    public function dispatch(): void {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') Helper::jsonResponse(false, 'Invalid request method.', [], 405);
        Security::requireValidCsrf($_POST[CSRF_TOKEN_NAME] ?? null);
        $action = $_POST['action'] ?? '';
        if ($action === 'request') $this->request();
        if ($action === 'reset') $this->reset();
        Helper::jsonResponse(false, 'Unknown action.', [], 400);
    }
    private function request(): void {
        $email = trim($_POST['email'] ?? '');
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) Helper::jsonResponse(false, 'Please enter a valid email address.', [], 422);
        if (($this->settings->getAll()['email_password_reset_enabled'] ?? '0') !== '1') {
            Helper::jsonResponse(false, 'Email password recovery is currently unavailable. Please contact an administrator.', [], 503);
        }
        $user = $this->users->findActiveByEmail($email);
        if (!$user) Helper::jsonResponse(false, 'Your email address is not found.', [], 404);
        $last = (int) ($_SESSION['password_reset_last_request'] ?? 0);
        if ($last && time() - $last < 60) Helper::jsonResponse(false, 'Please wait one minute before requesting another reset link.', [], 429);
        $_SESSION['password_reset_last_request'] = time();
        $token = $this->users->createEmailPasswordResetToken((int) $user['user_id'], Helper::getClientIp());
        $link = APP_URL . '/reset_password.php?token=' . rawurlencode($token);
        $name = Security::escape($user['full_name'] ?: 'there');
        $safeLink = Security::escape($link);
        $html = '<!doctype html><html><body style="margin:0;padding:0;background:#f4f6fa;font-family:Arial,sans-serif;color:#172033;">'
            . '<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="padding:32px 12px;"><tr><td align="center">'
            . '<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:560px;background:#ffffff;border-radius:16px;overflow:hidden;box-shadow:0 8px 28px rgba(22,35,63,.12);">'
            . '<tr><td style="background:#16233f;padding:24px 32px;color:#ffffff;font-weight:700;font-size:20px;letter-spacing:.4px;">POS <span style="color:#f2a65a;">STORE</span></td></tr>'
            . '<tr><td style="padding:32px;"><h1 style="margin:0 0 14px;font-size:24px;color:#172033;">Reset your password</h1><p style="margin:0 0 16px;line-height:1.6;">Hello ' . $name . ',</p><p style="margin:0 0 24px;line-height:1.6;color:#475467;">We received a request to reset your POS STORE password. Use the button below to create a new password.</p>'
            . '<p style="margin:0 0 28px;"><a href="' . $safeLink . '" style="display:inline-block;background:#16233f;color:#ffffff;text-decoration:none;font-weight:700;padding:13px 22px;border-radius:8px;">Reset your password</a></p>'
            . '<p style="margin:0;line-height:1.6;color:#667085;font-size:13px;">This secure link expires in 30 minutes and can only be used once. If you did not request it, you can safely ignore this email.</p>'
            . '<p style="margin:24px 0 0;padding-top:18px;border-top:1px solid #eaecf0;color:#98a2b3;font-size:12px;word-break:break-all;">Button not working? Copy this link:<br><a href="' . $safeLink . '" style="color:#3155e7;">' . $safeLink . '</a></p></td></tr></table></td></tr></table></body></html>';
        try { (new SmtpMailer($this->settings->getSmtpConfig()))->send($user['email'], 'Reset your POS STORE password', $html); }
        catch (Throwable $e) { error_log('Password reset email failed: ' . $e->getMessage()); Helper::jsonResponse(false, 'Could not send the reset email. Please try again or contact an administrator.', [], 500); }
        Helper::jsonResponse(true, 'A password reset link has been sent to your email address.');
    }
    private function reset(): void {
        $token = trim($_POST['token'] ?? '');
        $password = (string) ($_POST['password'] ?? '');
        $confirm = (string) ($_POST['confirm_password'] ?? '');
        if (!preg_match('/^[a-f0-9]{64}$/', $token)) Helper::jsonResponse(false, 'This password reset link is invalid or has expired.', [], 422);
        if ($password !== $confirm) Helper::jsonResponse(false, 'Passwords do not match.', [], 422);
        if (!Security::isStrongPassword($password)) Helper::jsonResponse(false, 'Password must have at least 8 characters, including a letter and a number.', [], 422);
        if (!$this->users->consumeEmailPasswordResetToken($token, $password)) Helper::jsonResponse(false, 'This password reset link is invalid or has expired.', [], 422);
        Helper::jsonResponse(true, 'Your password has been reset. You can now sign in.');
    }
}
if (basename($_SERVER['SCRIPT_FILENAME']) === basename(__FILE__)) { SessionManager::start(); (new PasswordResetController())->dispatch(); }
