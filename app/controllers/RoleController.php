<?php
/**
 * app/controllers/RoleController.php
 * -----------------------------------------------------------------------
 * AJAX endpoint for Roles & Permissions. Administrator-only - this
 * controls who can do what everywhere else in the app, so it's held to
 * a tighter bar than the Manager-accessible modules.
 */

if (!defined('POS_APP') && basename($_SERVER['SCRIPT_FILENAME']) === basename(__FILE__)) {
    require_once dirname(__DIR__, 2) . '/config/config.php';
}

if (!defined('POS_APP')) {
    die('Direct access not permitted.');
}

class RoleController
{
    private Role $roleModel;
    private User $userModel;

    public function __construct()
    {
        $this->roleModel = new Role();
        $this->userModel = new User();
    }

    public function dispatch(): void
    {
        SessionManager::requireRole(['Administrator']);

        switch ($_REQUEST['action'] ?? '') {
            case 'list':              $this->list(); break;
            case 'get':                $this->get(); break;
            case 'permissions':        $this->permissions(); break;
            case 'create':             $this->create(); break;
            case 'update':             $this->update(); break;
            case 'save_permissions':   $this->savePermissions(); break;
            default: Helper::jsonResponse(false, 'Unknown action.', [], 400);
        }
    }

    private function list(): void
    {
        Helper::jsonResponse(true, '', ['roles' => $this->roleModel->all()]);
    }

    /** A role's own fields plus every permission, flagged with which ones it currently has. */
    private function get(): void
    {
        $id = (int) ($_GET['id'] ?? 0);
        $role = $id > 0 ? $this->roleModel->find($id) : null;
        if (!$role) {
            Helper::jsonResponse(false, 'Role not found.', [], 404);
        }

        $granted = $this->roleModel->permissionIdsForRole($id);
        $permissions = array_map(function ($perm) use ($granted) {
            $perm['granted'] = in_array((int) $perm['permission_id'], $granted, true);
            return $perm;
        }, $this->roleModel->allPermissions());

        Helper::jsonResponse(true, '', ['role' => $role, 'permissions' => $permissions]);
    }

    /** Permission catalog for a newly-created role (no grants selected yet). */
    private function permissions(): void
    {
        $permissions = array_map(static function ($perm) { $perm['granted'] = false; return $perm; }, $this->roleModel->allPermissions());
        Helper::jsonResponse(true, '', ['permissions' => $permissions]);
    }

    private function create(): void
    {
        Security::requireValidCsrfFromRequest();
        [$name, $description, $isActive, $error] = $this->validateInput();
        if ($error) {
            Helper::jsonResponse(false, $error, [], 422);
        }

        $roleId = $this->roleModel->create($name, $description, $isActive);
        $this->userModel->logActivity((int) SessionManager::get('user_id'), 'ROLE_CREATE', "Created role \"{$name}\"");
        Helper::jsonResponse(true, 'Role created.', ['role_id' => $roleId]);
    }

    private function update(): void
    {
        Security::requireValidCsrfFromRequest();

        $id = (int) ($_POST['role_id'] ?? 0);
        if ($id <= 0 || !$this->roleModel->find($id)) {
            Helper::jsonResponse(false, 'Role not found.', [], 404);
        }

        [$name, $description, $isActive, $error] = $this->validateInput($id);
        if ($error) {
            Helper::jsonResponse(false, $error, [], 422);
        }

        $this->roleModel->update($id, $name, $description, $isActive);
        $this->userModel->logActivity((int) SessionManager::get('user_id'), 'ROLE_UPDATE', "Updated role #{$id} (\"{$name}\")");
        Helper::jsonResponse(true, 'Role updated.');
    }

    private function savePermissions(): void
    {
        Security::requireValidCsrfFromRequest();

        $id = (int) ($_POST['role_id'] ?? 0);
        if ($id <= 0 || !$this->roleModel->find($id)) {
            Helper::jsonResponse(false, 'Role not found.', [], 404);
        }

        $permissionIds = json_decode($_POST['permission_ids'] ?? '[]', true);
        $permissionIds = is_array($permissionIds) ? $permissionIds : [];

        $this->roleModel->setPermissions($id, $permissionIds);
        $this->userModel->logActivity((int) SessionManager::get('user_id'), 'ROLE_PERMISSIONS_UPDATE', "Updated permissions for role #{$id}");
        Helper::jsonResponse(true, 'Permissions updated.');
    }

    private function validateInput(?int $excludeId = null): array
    {
        $name = Security::sanitize(trim($_POST['role_name'] ?? ''));
        $description = Security::sanitize(trim($_POST['description'] ?? ''));
        $isActive = !empty($_POST['is_active']);

        if ($name === '') {
            return [null, null, null, 'Role name is required.'];
        }
        if (strlen($name) > 50) {
            return [null, null, null, 'Role name is too long.'];
        }
        if ($this->roleModel->nameExists($name, $excludeId)) {
            return [null, null, null, 'A role with that name already exists.'];
        }

        return [$name, $description, $isActive, null];
    }
}

if (basename($_SERVER['SCRIPT_FILENAME']) === basename(__FILE__)) {
    SessionManager::start();
    (new RoleController())->dispatch();
}
