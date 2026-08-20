<?php
/**
 * app/controllers/PurchaseController.php
 * -----------------------------------------------------------------------
 * AJAX endpoint for the Purchases module: record stock received from a
 * supplier, list purchase history, view a purchase's line items, and
 * cancel a received purchase (reversing its stock impact).
 * Restricted to Administrator/Manager.
 */

if (!defined('POS_APP') && basename($_SERVER['SCRIPT_FILENAME']) === basename(__FILE__)) {
    require_once dirname(__DIR__, 2) . '/config/config.php';
}

if (!defined('POS_APP')) {
    die('Direct access not permitted.');
}

class PurchaseController
{
    private Purchase $purchaseModel;
    private Supplier $supplierModel;
    private Product $productModel;
    private User $userModel;

    public function __construct()
    {
        $this->purchaseModel = new Purchase();
        $this->supplierModel = new Supplier();
        $this->productModel  = new Product();
        $this->userModel     = new User();
    }

    public function dispatch(): void
    {
        SessionManager::requireRole(['Administrator', 'Manager']);

        switch ($_REQUEST['action'] ?? '') {
            case 'list':      $this->list(); break;
            case 'get':       $this->get(); break;
            case 'create':    $this->create(); break;
            case 'cancel':    $this->cancel(); break;
            case 'form_data': $this->formData(); break;
            case 'products':  $this->products(); break;
            default: Helper::jsonResponse(false, 'Unknown action.', [], 400);
        }
    }

    private function list(): void
    {
        $filters = [
            'search'      => Security::sanitize(trim($_GET['search'] ?? '')),
            'status'      => Security::sanitize($_GET['status'] ?? ''),
            'supplier_id' => !empty($_GET['supplier_id']) ? (int) $_GET['supplier_id'] : null,
            'date_from'   => Security::sanitize($_GET['date_from'] ?? ''),
            'date_to'     => Security::sanitize($_GET['date_to'] ?? ''),
        ];

        $result = $this->purchaseModel->paginate(
            $filters,
            Security::sanitize($_GET['sort_by'] ?? 'purchased_at'),
            Security::sanitize($_GET['sort_dir'] ?? 'DESC'),
            (int) ($_GET['page'] ?? 1),
            (int) ($_GET['per_page'] ?? 10)
        );

        Helper::jsonResponse(true, '', ['result' => $result]);
    }

    private function get(): void
    {
        $id = (int) ($_GET['id'] ?? 0);
        $purchase = $id > 0 ? $this->purchaseModel->find($id) : null;
        if (!$purchase) {
            Helper::jsonResponse(false, 'Purchase not found.', [], 404);
        }
        Helper::jsonResponse(true, '', ['purchase' => $purchase]);
    }

    /** Suppliers for the form's dropdown. */
    private function formData(): void
    {
        Helper::jsonResponse(true, '', [
            'suppliers' => $this->supplierModel->allActive(),
        ]);
    }

    /** Product picker for the line-item rows: active products, name/code search. */
    private function products(): void
    {
        $search = Security::sanitize(trim($_GET['search'] ?? ''));
        $result = $this->productModel->paginate($search, null, 'product_name', 'ASC', 1, 20);

        $products = array_map(function ($row) {
            return [
                'product_id'   => $row['product_id'],
                'product_code' => $row['product_code'],
                'product_name' => $row['product_name'],
                'unit'         => $row['unit'],
                'cost_price'   => $row['cost_price'],
            ];
        }, array_values(array_filter($result['rows'], fn($row) => $row['is_active'])));

        Helper::jsonResponse(true, '', ['products' => $products]);
    }

    private function create(): void
    {
        Security::requireValidCsrfFromRequest();

        $supplierId = (int) ($_POST['supplier_id'] ?? 0);
        $items = json_decode($_POST['items'] ?? '[]', true);
        $items = is_array($items) ? $items : [];

        $invoiceReceipt = Security::sanitize(trim($_POST['invoice_receipt'] ?? ''));
        [$purchaseId, $error] = $this->purchaseModel->create($supplierId, $items, (int) SessionManager::get('user_id'), $invoiceReceipt);
        if ($error) {
            Helper::jsonResponse(false, $error, [], 422);
        }

        $this->userModel->logActivity((int) SessionManager::get('user_id'), 'PURCHASE_CREATE', "Recorded purchase #{$purchaseId}");
        Helper::jsonResponse(true, 'Purchase recorded and stock updated.', ['purchase_id' => $purchaseId]);
    }

    private function cancel(): void
    {
        Security::requireValidCsrfFromRequest();

        $id = (int) ($_POST['purchase_id'] ?? 0);
        if ($id <= 0) {
            Helper::jsonResponse(false, 'Purchase not found.', [], 404);
        }

        $error = $this->purchaseModel->cancel($id, (int) SessionManager::get('user_id'));
        if ($error) {
            Helper::jsonResponse(false, $error, [], 422);
        }

        $this->userModel->logActivity((int) SessionManager::get('user_id'), 'PURCHASE_CANCEL', "Cancelled purchase #{$id}");
        Helper::jsonResponse(true, 'Purchase cancelled and stock reversed.');
    }
}

if (basename($_SERVER['SCRIPT_FILENAME']) === basename(__FILE__)) {
    SessionManager::start();
    (new PurchaseController())->dispatch();
}
