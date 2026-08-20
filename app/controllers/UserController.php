<?php
/**
 * app/controllers/UserController.php
 * -----------------------------------------------------------------------
 * AJAX endpoint for the "Users" tab on the Roles & Permissions page.
 * Administrator-only - creating accounts and reassigning roles is more
 * sensitive than the Manager-level access the rest of that page allows
 * for viewing.
 */

if (!defined('POS_APP') && basename($_SERVER['SCRIPT_FILENAME']) === basename(__FILE__)) {
    require_once dirname(__DIR__, 2) . '/config/config.php';
}

if (!defined('POS_APP')) {
    die('Direct access not permitted.');
}

class UserController
{
    private User $userModel;
    private Role $roleModel;

    public function __construct()
    {
        $this->userModel = new User();
        $this->roleModel = new Role();
    }

    public function dispatch(): void
    {
        SessionManager::requireRole(['Administrator']);

        switch ($_REQUEST['action'] ?? '') {
            case 'list':      $this->list(); break;
            case 'form_data': $this->formData(); break;
            case 'create':    $this->create(); break;
            case 'update':    $this->update(); break;
            case 'reset_password': $this->resetPassword(); break;
            default: Helper::jsonResponse(false, 'Unknown action.', [], 400);
        }
    }

    private function list(): void
    {
        $result = $this->userModel->paginate(
            ['search' => Security::sanitize(trim($_GET['search'] ?? '')), 'role_id' => (int) ($_GET['role_id'] ?? 0) ?: null],
            $_GET['sort_by'] ?? 'full_name',
            $_GET['sort_dir'] ?? 'ASC',
            (int) ($_GET['page'] ?? 1),
            (int) ($_GET['per_page'] ?? 10)
        );
        Helper::jsonResponse(true, '', ['result' => $result]);
    }

    private function formData(): void
    {
        Helper::jsonResponse(true, '', ['roles' => $this->roleModel->all()]);
    }

    private function create(): void
    {
        Security::requireValidCsrfFromRequest();

        $username = Security::sanitize(trim($_POST['username'] ?? ''));
        $fullName = Security::sanitize(trim($_POST['full_name'] ?? ''));
        $email    = Security::sanitize(trim($_POST['email'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');
        $roleId   = (int) ($_POST['role_id'] ?? 0);
        $isActive = !empty($_POST['is_active']);

        if ($username === '' || $fullName === '' || $roleId <= 0) {
            Helper::jsonResponse(false, 'Username, full name, and role are required.', [], 422);
        }

        [$userId, $error] = $this->userModel->create($username, $fullName, $email, $password, $roleId, $isActive);
        if ($error) {
            Helper::jsonResponse(false, $error, [], 422);
        }

        $this->userModel->logActivity((int) SessionManager::get('user_id'), 'USER_CREATED', "Created user account: {$username}");
        Helper::jsonResponse(true, 'User created.', ['user_id' => $userId]);
    }

    private function update(): void
    {
        Security::requireValidCsrfFromRequest();

        $userId   = (int) ($_POST['user_id'] ?? 0);
        $fullName = Security::sanitize(trim($_POST['full_name'] ?? ''));
        $email    = Security::sanitize(trim($_POST['email'] ?? ''));
        $roleId   = (int) ($_POST['role_id'] ?? 0) ?: null;
        $isActive = isset($_POST['is_active']) ? !empty($_POST['is_active']) : null;

        if ($userId <= 0 || $fullName === '') {
            Helper::jsonResponse(false, 'Full name is required.', [], 422);
        }

        // Guard against an admin locking themselves out by deactivating their own account.
        if ($userId === (int) SessionManager::get('user_id') && $isActive === false) {
            Helper::jsonResponse(false, 'You cannot deactivate your own account.', [], 422);
        }

        $this->userModel->updateProfile($userId, $fullName, $email, $roleId, $isActive);
        $this->userModel->logActivity((int) SessionManager::get('user_id'), 'USER_UPDATED', "Updated user #{$userId}");
        Helper::jsonResponse(true, 'User updated.');
    }

    private function resetPassword(): void
    {
        Security::requireValidCsrfFromRequest();

        $userId = (int) ($_POST['user_id'] ?? 0);
        $password = (string) ($_POST['password'] ?? '');

        [$ok, $error] = $this->userModel->setPassword($userId, $password);
        if ($error) {
            Helper::jsonResponse(false, $error, [], 422);
        }

        $this->userModel->logActivity((int) SessionManager::get('user_id'), 'USER_PASSWORD_RESET', "Reset password for user #{$userId}");
        Helper::jsonResponse(true, 'Password updated.');
    }
}

if (basename($_SERVER['SCRIPT_FILENAME']) === basename(__FILE__)) {
    SessionManager::start();
    (new UserController())->dispatch();
}
