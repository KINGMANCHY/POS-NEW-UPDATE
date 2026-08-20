<?php
/**
 * app/controllers/ProfileController.php
 * -----------------------------------------------------------------------
 * Self-service account page: any logged-in user (any role) can view
 * their own details, update their name/email, and change their own
 * password. This is deliberately separate from UserController - that
 * one lets an Administrator manage OTHER people's accounts and roles;
 * this one only ever touches the current session's own user_id, so
 * there's no risk of a role/user_id being passed in from the client.
 */

if (!defined('POS_APP') && basename($_SERVER['SCRIPT_FILENAME']) === basename(__FILE__)) {
    require_once dirname(__DIR__, 2) . '/config/config.php';
}

if (!defined('POS_APP')) {
    die('Direct access not permitted.');
}

class ProfileController
{
    private User $userModel;

    public function __construct()
    {
        $this->userModel = new User();
    }

    public function dispatch(): void
    {
        SessionManager::requireLogin();

        switch ($_REQUEST['action'] ?? '') {
            case 'get':              $this->get(); break;
            case 'update':           $this->update(); break;
            case 'change_password':  $this->changePassword(); break;
            default: Helper::jsonResponse(false, 'Unknown action.', [], 400);
        }
    }

    private function currentUserId(): int
    {
        return (int) SessionManager::get('user_id');
    }

    private function get(): void
    {
        $user = $this->userModel->findById($this->currentUserId());
        if (!$user) {
            Helper::jsonResponse(false, 'Account not found.', [], 404);
        }
        Helper::jsonResponse(true, '', ['user' => $user]);
    }

    private function update(): void
    {
        Security::requireValidCsrfFromRequest();

        $fullName = Security::sanitize(trim($_POST['full_name'] ?? ''));
        $email    = Security::sanitize(trim($_POST['email'] ?? ''));

        if ($fullName === '') {
            Helper::jsonResponse(false, 'Full name is required.', [], 422);
        }

        // Own profile only - role and active status are not editable here.
        $this->userModel->updateProfile($this->currentUserId(), $fullName, $email, null, null);
        SessionManager::set('full_name', $fullName);

        $this->userModel->logActivity($this->currentUserId(), 'PROFILE_UPDATED', 'Updated own profile details');
        Helper::jsonResponse(true, 'Profile updated.');
    }

    private function changePassword(): void
    {
        Security::requireValidCsrfFromRequest();

        $current = (string) ($_POST['current_password'] ?? '');
        $new     = (string) ($_POST['new_password'] ?? '');
        $userId  = $this->currentUserId();

        $hash = $this->userModel->getPasswordHash($userId);
        if (!$hash || !$this->userModel->verifyPassword($current, $hash)) {
            Helper::jsonResponse(false, 'Your current password is incorrect.', [], 422);
        }

        [$ok, $error] = $this->userModel->setPassword($userId, $new);
        if ($error) {
            Helper::jsonResponse(false, $error, [], 422);
        }

        $this->userModel->logActivity($userId, 'PASSWORD_CHANGED', 'Changed own password');
        Helper::jsonResponse(true, 'Password changed.');
    }
}

if (basename($_SERVER['SCRIPT_FILENAME']) === basename(__FILE__)) {
    SessionManager::start();
    (new ProfileController())->dispatch();
}
