<?php
/**
 * app/controllers/CategoryController.php
 * -----------------------------------------------------------------------
 * AJAX CRUD endpoint for Categories, used by views/categories.php +
 * assets/js/categories.js.
 *
 * Single-file "resource controller": one URL, dispatched by an `action`
 * parameter -   ?action=list | get | create | update | delete
 * Reads (list/get) require only a valid session. Writes (create/update/
 * delete) additionally require a valid CSRF token.
 */

if (!defined('POS_APP') && basename($_SERVER['SCRIPT_FILENAME']) === basename(__FILE__)) {
    require_once dirname(__DIR__, 2) . '/config/config.php';
}

if (!defined('POS_APP')) {
    die('Direct access not permitted.');
}

class CategoryController
{
    private Category $categoryModel;
    private User $userModel;

    public function __construct()
    {
        $this->categoryModel = new Category();
        $this->userModel = new User();
    }

    public function dispatch(): void
    {
        SessionManager::requireLogin();

        $action = $_REQUEST['action'] ?? '';

        switch ($action) {
            case 'list':   $this->list(); break;
            case 'get':    $this->get(); break;
            case 'create': $this->create(); break;
            case 'update': $this->update(); break;
            case 'delete': $this->delete(); break;
            default:
                Helper::jsonResponse(false, 'Unknown action.', [], 400);
        }
    }

    private function list(): void
    {
        $search   = Security::sanitize(trim($_GET['search'] ?? ''));
        $sortBy   = Security::sanitize($_GET['sort_by'] ?? 'category_name');
        $sortDir  = Security::sanitize($_GET['sort_dir'] ?? 'ASC');
        $page     = (int) ($_GET['page'] ?? 1);
        $perPage  = (int) ($_GET['per_page'] ?? 10);

        $result = $this->categoryModel->paginate($search, $sortBy, $sortDir, $page, $perPage);
        Helper::jsonResponse(true, '', ['result' => $result]);
    }

    private function get(): void
    {
        $id = (int) ($_GET['id'] ?? 0);
        $category = $id > 0 ? $this->categoryModel->find($id) : null;

        if (!$category) {
            Helper::jsonResponse(false, 'Category not found.', [], 404);
        }
        Helper::jsonResponse(true, '', ['category' => $category]);
    }

    private function create(): void
    {
        Security::requireValidCsrfFromRequest();

        [$name, $description, $isActive, $error] = $this->validateInput();
        if ($error) {
            Helper::jsonResponse(false, $error, [], 422);
        }

        if ($this->categoryModel->nameExists($name)) {
            Helper::jsonResponse(false, 'A category with that name already exists.', [], 409);
        }

        $id = $this->categoryModel->create($name, $description, $isActive);
        $this->userModel->logActivity((int) SessionManager::get('user_id'), 'CATEGORY_CREATE', "Created category #{$id}: {$name}");

        Helper::jsonResponse(true, 'Category created successfully.', ['category_id' => $id]);
    }

    private function update(): void
    {
        Security::requireValidCsrfFromRequest();

        $id = (int) ($_POST['category_id'] ?? 0);
        $existing = $id > 0 ? $this->categoryModel->find($id) : null;
        if (!$existing) {
            Helper::jsonResponse(false, 'Category not found.', [], 404);
        }

        [$name, $description, $isActive, $error] = $this->validateInput();
        if ($error) {
            Helper::jsonResponse(false, $error, [], 422);
        }

        if ($this->categoryModel->nameExists($name, $id)) {
            Helper::jsonResponse(false, 'A category with that name already exists.', [], 409);
        }

        $this->categoryModel->update($id, $name, $description, $isActive);
        $this->userModel->logActivity((int) SessionManager::get('user_id'), 'CATEGORY_UPDATE', "Updated category #{$id}: {$name}");

        Helper::jsonResponse(true, 'Category updated successfully.');
    }

    private function delete(): void
    {
        Security::requireValidCsrfFromRequest();

        $id = (int) ($_POST['category_id'] ?? 0);
        $existing = $id > 0 ? $this->categoryModel->find($id) : null;
        if (!$existing) {
            Helper::jsonResponse(false, 'Category not found.', [], 404);
        }

        if ($this->categoryModel->isInUse($id)) {
            Helper::jsonResponse(false, 'This category has products assigned to it and cannot be deleted. Deactivate it instead.', [], 409);
        }

        $this->categoryModel->delete($id);
        $this->userModel->logActivity((int) SessionManager::get('user_id'), 'CATEGORY_DELETE', "Deleted category #{$id}: {$existing['category_name']}");

        Helper::jsonResponse(true, 'Category deleted successfully.');
    }

    /**
     * Shared validation for create/update. Returns [name, description, isActive, error].
     * $error is null when the input is valid.
     */
    private function validateInput(): array
    {
        $name        = Security::sanitize(trim($_POST['category_name'] ?? ''));
        $description = Security::sanitize(trim($_POST['description'] ?? ''));
        $isActive    = !empty($_POST['is_active']);

        if ($name === '' || mb_strlen($name) < 2 || mb_strlen($name) > 100) {
            return [$name, $description, $isActive, 'Category name must be between 2 and 100 characters.'];
        }

        return [$name, $description, $isActive, null];
    }
}

if (basename($_SERVER['SCRIPT_FILENAME']) === basename(__FILE__)) {
    SessionManager::start();
    (new CategoryController())->dispatch();
}
