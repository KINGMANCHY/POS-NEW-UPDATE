<?php
/**
 * app/controllers/InventoryController.php
 * -----------------------------------------------------------------------
 * AJAX endpoint for the Inventory page: list stock levels and record
 * manual count corrections. Restricted to Administrator/Manager.
 */

if (!defined('POS_APP') && basename($_SERVER['SCRIPT_FILENAME']) === basename(__FILE__)) {
    require_once dirname(__DIR__, 2) . '/config/config.php';
}

if (!defined('POS_APP')) {
    die('Direct access not permitted.');
}

class InventoryController
{
    private Inventory $inventoryModel;
    private Category $categoryModel;
    private User $userModel;

    public function __construct()
    {
        $this->inventoryModel = new Inventory();
        $this->categoryModel  = new Category();
        $this->userModel      = new User();
    }

    public function dispatch(): void
    {
        // The low-stock bell is global (every role sees it in the navbar,
        // including Cashiers on the POS Screen) - only the actual
        // inventory-management actions below it are Admin/Manager-only.
        if (($_REQUEST['action'] ?? '') === 'low_stock_alerts') {
            SessionManager::requireLogin();
            $this->lowStockAlerts();
            return;
        }

        SessionManager::requireRole(['Administrator', 'Manager']);

        switch ($_REQUEST['action'] ?? '') {
            case 'list':      $this->list(); break;
            case 'form_data': $this->formData(); break;
            case 'adjust':    $this->adjust(); break;
            default: Helper::jsonResponse(false, 'Unknown action.', [], 400);
        }
    }

    private function lowStockAlerts(): void
    {
        Helper::jsonResponse(true, '', [
            'count' => $this->inventoryModel->lowStockCount(),
            'items' => $this->inventoryModel->lowStockList(8),
        ]);
    }

    private function list(): void
    {
        $filters = [
            'search'         => Security::sanitize(trim($_GET['search'] ?? '')),
            'category_id'    => !empty($_GET['category_id']) ? (int) $_GET['category_id'] : null,
            'low_stock_only' => !empty($_GET['low_stock_only']),
        ];

        $result = $this->inventoryModel->paginate(
            $filters,
            Security::sanitize($_GET['sort_by'] ?? 'product_name'),
            Security::sanitize($_GET['sort_dir'] ?? 'ASC'),
            (int) ($_GET['page'] ?? 1),
            (int) ($_GET['per_page'] ?? 10)
        );

        Helper::jsonResponse(true, '', [
            'result'          => $result,
            'low_stock_count' => $this->inventoryModel->lowStockCount(),
        ]);
    }

    private function formData(): void
    {
        Helper::jsonResponse(true, '', ['categories' => $this->categoryModel->allActive()]);
    }

    private function adjust(): void
    {
        Security::requireValidCsrfFromRequest();

        $productId = (int) ($_POST['product_id'] ?? 0);
        $quantity  = (int) ($_POST['quantity'] ?? -1);
        $changeType = $_POST['change_type'] ?? 'adjustment';
        $reason    = Security::sanitize(trim($_POST['reason'] ?? ''));

        if ($productId <= 0) {
            Helper::jsonResponse(false, 'Product not found.', [], 404);
        }
        if ($reason === '') {
            Helper::jsonResponse(false, 'Please give a reason for this adjustment.', [], 422);
        }

        if (!in_array($changeType, ['stock_in', 'stock_out', 'adjustment'], true)) {
            Helper::jsonResponse(false, 'Invalid change type.', [], 422);
        }

        // Stock in/out accepts the entered amount; adjustment remains an exact count.
        if ($changeType !== 'adjustment') {
            $current = (int) ($_POST['current_quantity'] ?? 0);
            if ($quantity < 0) Helper::jsonResponse(false, 'Quantity cannot be negative.', [], 422);
            $quantity = $changeType === 'stock_in' ? $current + $quantity : $current - $quantity;
        }

        [$oldQuantity, $error] = $this->inventoryModel->setQuantity($productId, $quantity, ucfirst(str_replace('_', ' ', $changeType)) . ': ' . $reason, (int) SessionManager::get('user_id'));
        if ($error) {
            Helper::jsonResponse(false, $error, [], 422);
        }

        $delta = $quantity - $oldQuantity;
        $sign = $delta >= 0 ? '+' : '';
        $this->userModel->logActivity(
            (int) SessionManager::get('user_id'),
            'INVENTORY_ADJUST',
            "Product #{$productId}: {$oldQuantity} -> {$quantity} ({$sign}{$delta}). Reason: {$reason}"
        );

        Helper::jsonResponse(true, 'Stock updated.', ['quantity_on_hand' => $quantity]);
    }
}

if (basename($_SERVER['SCRIPT_FILENAME']) === basename(__FILE__)) {
    SessionManager::start();
    (new InventoryController())->dispatch();
}
