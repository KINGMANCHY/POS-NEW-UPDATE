<?php
/**
 * app/controllers/SupplierController.php
 * -----------------------------------------------------------------------
 * AJAX CRUD endpoint for Suppliers. Same resource-controller pattern as
 * CategoryController.php: ?action=list|get|create|update|delete
 */

if (!defined('POS_APP') && basename($_SERVER['SCRIPT_FILENAME']) === basename(__FILE__)) {
    require_once dirname(__DIR__, 2) . '/config/config.php';
}

if (!defined('POS_APP')) {
    die('Direct access not permitted.');
}

class SupplierController
{
    private Supplier $supplierModel;
    private User $userModel;

    public function __construct()
    {
        $this->supplierModel = new Supplier();
        $this->userModel = new User();
    }

    public function dispatch(): void
    {
        SessionManager::requireLogin();

        switch ($_REQUEST['action'] ?? '') {
            case 'list':   $this->list(); break;
            case 'get':    $this->get(); break;
            case 'create': $this->create(); break;
            case 'update': $this->update(); break;
            case 'delete': $this->delete(); break;
            default: Helper::jsonResponse(false, 'Unknown action.', [], 400);
        }
    }

    private function list(): void
    {
        $result = $this->supplierModel->paginate(
            Security::sanitize(trim($_GET['search'] ?? '')),
            Security::sanitize($_GET['sort_by'] ?? 'supplier_name'),
            Security::sanitize($_GET['sort_dir'] ?? 'ASC'),
            (int) ($_GET['page'] ?? 1),
            (int) ($_GET['per_page'] ?? 10)
        );
        Helper::jsonResponse(true, '', ['result' => $result]);
    }

    private function get(): void
    {
        $id = (int) ($_GET['id'] ?? 0);
        $supplier = $id > 0 ? $this->supplierModel->find($id) : null;
        if (!$supplier) {
            Helper::jsonResponse(false, 'Supplier not found.', [], 404);
        }
        Helper::jsonResponse(true, '', ['supplier' => $supplier]);
    }

    private function create(): void
    {
        Security::requireValidCsrfFromRequest();
        [$data, $error] = $this->validateInput();
        if ($error) {
            Helper::jsonResponse(false, $error, [], 422);
        }

        $id = $this->supplierModel->create($data);
        $this->userModel->logActivity((int) SessionManager::get('user_id'), 'SUPPLIER_CREATE', "Created supplier #{$id}: {$data['supplier_name']}");

        Helper::jsonResponse(true, 'Supplier created successfully.', ['supplier_id' => $id]);
    }

    private function update(): void
    {
        Security::requireValidCsrfFromRequest();

        $id = (int) ($_POST['supplier_id'] ?? 0);
        if ($id <= 0 || !$this->supplierModel->find($id)) {
            Helper::jsonResponse(false, 'Supplier not found.', [], 404);
        }

        [$data, $error] = $this->validateInput();
        if ($error) {
            Helper::jsonResponse(false, $error, [], 422);
        }

        $this->supplierModel->update($id, $data);
        $this->userModel->logActivity((int) SessionManager::get('user_id'), 'SUPPLIER_UPDATE', "Updated supplier #{$id}: {$data['supplier_name']}");

        Helper::jsonResponse(true, 'Supplier updated successfully.');
    }

    private function delete(): void
    {
        Security::requireValidCsrfFromRequest();

        $id = (int) ($_POST['supplier_id'] ?? 0);
        $existing = $id > 0 ? $this->supplierModel->find($id) : null;
        if (!$existing) {
            Helper::jsonResponse(false, 'Supplier not found.', [], 404);
        }

        if ($this->supplierModel->isInUse($id)) {
            Helper::jsonResponse(false, 'This supplier has products assigned to it and cannot be deleted. Deactivate it instead.', [], 409);
        }

        $this->supplierModel->delete($id);
        $this->userModel->logActivity((int) SessionManager::get('user_id'), 'SUPPLIER_DELETE', "Deleted supplier #{$id}: {$existing['supplier_name']}");

        Helper::jsonResponse(true, 'Supplier deleted successfully.');
    }

    private function validateInput(): array
    {
        $data = [
            'supplier_name'  => Security::sanitize(trim($_POST['supplier_name'] ?? '')),
            'contact_person' => Security::sanitize(trim($_POST['contact_person'] ?? '')),
            'phone'          => Security::sanitize(trim($_POST['phone'] ?? '')),
            'email'          => Security::sanitize(trim($_POST['email'] ?? '')),
            'address'        => Security::sanitize(trim($_POST['address'] ?? '')),
            'is_active'      => !empty($_POST['is_active']),
        ];

        if ($data['supplier_name'] === '' || mb_strlen($data['supplier_name']) < 2) {
            return [$data, 'Supplier name is required (minimum 2 characters).'];
        }
        if ($data['email'] !== '' && !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            return [$data, 'Please enter a valid email address.'];
        }

        return [$data, null];
    }
}

if (basename($_SERVER['SCRIPT_FILENAME']) === basename(__FILE__)) {
    SessionManager::start();
    (new SupplierController())->dispatch();
}
