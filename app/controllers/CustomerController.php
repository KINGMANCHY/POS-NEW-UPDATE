<?php
/**
 * app/controllers/CustomerController.php
 * -----------------------------------------------------------------------
 * AJAX CRUD endpoint for Customers. Same resource-controller pattern as
 * CategoryController.php: ?action=list|get|create|update|delete
 */

if (!defined('POS_APP') && basename($_SERVER['SCRIPT_FILENAME']) === basename(__FILE__)) {
    require_once dirname(__DIR__, 2) . '/config/config.php';
}

if (!defined('POS_APP')) {
    die('Direct access not permitted.');
}

class CustomerController
{
    private Customer $customerModel;
    private User $userModel;

    public function __construct()
    {
        $this->customerModel = new Customer();
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
        $result = $this->customerModel->paginate(
            Security::sanitize(trim($_GET['search'] ?? '')),
            Security::sanitize($_GET['sort_by'] ?? 'full_name'),
            Security::sanitize($_GET['sort_dir'] ?? 'ASC'),
            (int) ($_GET['page'] ?? 1),
            (int) ($_GET['per_page'] ?? 10)
        );
        Helper::jsonResponse(true, '', ['result' => $result]);
    }

    private function get(): void
    {
        $id = (int) ($_GET['id'] ?? 0);
        $customer = $id > 0 ? $this->customerModel->find($id) : null;
        if (!$customer) {
            Helper::jsonResponse(false, 'Customer not found.', [], 404);
        }
        Helper::jsonResponse(true, '', ['customer' => $customer]);
    }

    private function create(): void
    {
        Security::requireValidCsrfFromRequest();
        [$data, $error] = $this->validateInput();
        if ($error) {
            Helper::jsonResponse(false, $error, [], 422);
        }

        $id = $this->customerModel->create($data);
        $this->userModel->logActivity((int) SessionManager::get('user_id'), 'CUSTOMER_CREATE', "Created customer #{$id}: {$data['full_name']}");

        Helper::jsonResponse(true, 'Customer created successfully.', ['customer_id' => $id]);
    }

    private function update(): void
    {
        Security::requireValidCsrfFromRequest();

        $id = (int) ($_POST['customer_id'] ?? 0);
        if ($id <= 0 || !$this->customerModel->find($id)) {
            Helper::jsonResponse(false, 'Customer not found.', [], 404);
        }

        [$data, $error] = $this->validateInput();
        if ($error) {
            Helper::jsonResponse(false, $error, [], 422);
        }

        $this->customerModel->update($id, $data);
        $this->userModel->logActivity((int) SessionManager::get('user_id'), 'CUSTOMER_UPDATE', "Updated customer #{$id}: {$data['full_name']}");

        Helper::jsonResponse(true, 'Customer updated successfully.');
    }

    private function delete(): void
    {
        Security::requireValidCsrfFromRequest();

        $id = (int) ($_POST['customer_id'] ?? 0);
        $existing = $id > 0 ? $this->customerModel->find($id) : null;
        if (!$existing) {
            Helper::jsonResponse(false, 'Customer not found.', [], 404);
        }

        if ($this->customerModel->isInUse($id)) {
            Helper::jsonResponse(false, 'This customer has sales history and cannot be deleted. Deactivate it instead.', [], 409);
        }

        $this->customerModel->delete($id);
        $this->userModel->logActivity((int) SessionManager::get('user_id'), 'CUSTOMER_DELETE', "Deleted customer #{$id}: {$existing['full_name']}");

        Helper::jsonResponse(true, 'Customer deleted successfully.');
    }

    private function validateInput(): array
    {
        $data = [
            'full_name' => Security::sanitize(trim($_POST['full_name'] ?? '')),
            'phone'     => Security::sanitize(trim($_POST['phone'] ?? '')),
            'email'     => Security::sanitize(trim($_POST['email'] ?? '')),
            'address'   => Security::sanitize(trim($_POST['address'] ?? '')),
            'is_active' => !empty($_POST['is_active']),
        ];

        if ($data['full_name'] === '' || mb_strlen($data['full_name']) < 2) {
            return [$data, 'Customer name is required (minimum 2 characters).'];
        }
        if ($data['email'] !== '' && !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            return [$data, 'Please enter a valid email address.'];
        }

        return [$data, null];
    }
}

if (basename($_SERVER['SCRIPT_FILENAME']) === basename(__FILE__)) {
    SessionManager::start();
    (new CustomerController())->dispatch();
}
