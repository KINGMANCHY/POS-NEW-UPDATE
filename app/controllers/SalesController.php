<?php
/**
 * app/controllers/SalesController.php
 * -----------------------------------------------------------------------
 * AJAX endpoint for the Sales History page. This is a read/manage view
 * over transactions already recorded by PosController - it never writes
 * a new sale, only lists, retrieves, and voids existing ones.
 * Restricted to Administrator/Manager (Cashiers work the POS screen only).
 */

if (!defined('POS_APP') && basename($_SERVER['SCRIPT_FILENAME']) === basename(__FILE__)) {
    require_once dirname(__DIR__, 2) . '/config/config.php';
}

if (!defined('POS_APP')) {
    die('Direct access not permitted.');
}

class SalesController
{
    private Sale $saleModel;
    private User $userModel;

    public function __construct()
    {
        $this->saleModel = new Sale();
        $this->userModel = new User();
    }

    public function dispatch(): void
    {
        SessionManager::requireRole(['Administrator', 'Manager']);

        switch ($_REQUEST['action'] ?? '') {
            case 'list':  $this->list(); break;
            case 'get':   $this->get(); break;
            case 'void':  $this->void(); break;
            case 'export_excel': $this->export('excel'); break;
            case 'export_pdf':   $this->export('pdf'); break;
            default: Helper::jsonResponse(false, 'Unknown action.', [], 400);
        }
    }

    private function list(): void
    {
        $filters = [
            'search'    => Security::sanitize(trim($_GET['search'] ?? '')),
            'status'    => Security::sanitize($_GET['status'] ?? ''),
            'date_from' => Security::sanitize($_GET['date_from'] ?? ''),
            'date_to'   => Security::sanitize($_GET['date_to'] ?? ''),
        ];

        $result = $this->saleModel->paginate(
            $filters,
            Security::sanitize($_GET['sort_by'] ?? 'created_at'),
            Security::sanitize($_GET['sort_dir'] ?? 'DESC'),
            (int) ($_GET['page'] ?? 1),
            (int) ($_GET['per_page'] ?? 10)
        );

        Helper::jsonResponse(true, '', ['result' => $result]);
    }

    private function get(): void
    {
        $id = (int) ($_GET['id'] ?? 0);
        $sale = $id > 0 ? $this->saleModel->getReceipt($id) : null;
        if (!$sale) {
            Helper::jsonResponse(false, 'Sale not found.', [], 404);
        }
        Helper::jsonResponse(true, '', ['sale' => $sale]);
    }

    private function void(): void
    {
        Security::requireValidCsrfFromRequest();

        $id = (int) ($_POST['sale_id'] ?? 0);
        if ($id <= 0) {
            Helper::jsonResponse(false, 'Sale not found.', [], 404);
        }

        $error = $this->saleModel->void($id, (int) SessionManager::get('user_id'));
        if ($error) {
            Helper::jsonResponse(false, $error, [], 422);
        }

        $this->userModel->logActivity((int) SessionManager::get('user_id'), 'SALE_VOID', "Voided sale #{$id}");
        Helper::jsonResponse(true, 'Sale voided and stock restored.');
    }

    private function export(string $format): void
    {
        $filters = ['search' => Security::sanitize(trim($_GET['search'] ?? '')), 'status' => Security::sanitize($_GET['status'] ?? ''), 'date_from' => Security::sanitize($_GET['date_from'] ?? ''), 'date_to' => Security::sanitize($_GET['date_to'] ?? '')];
        $data = array_map(static function ($r) { return [$r['invoice_no'], $r['created_at'], $r['customer_name'] ?: 'Walk-in', $r['cashier_name'], $r['item_count'], strtoupper($r['payment_method']), (float) $r['grand_total'], ucfirst($r['status'])]; }, $this->saleModel->exportRows($filters));
        $headers = ['Invoice', 'Date', 'Customer', 'Cashier', 'Items', 'Payment', 'Total', 'Status'];
        if ($format === 'excel') XlsxWriter::stream('sales_' . date('Ymd_His'), $headers, $data, 'Sales');
        PdfWriter::stream('sales_' . date('Ymd_His'), 'Sales History', 'Generated ' . date('Y-m-d H:i'), $headers, [72, 78, 80, 72, 32, 50, 55, 50], $data);
    }
}

if (basename($_SERVER['SCRIPT_FILENAME']) === basename(__FILE__)) {
    SessionManager::start();
    (new SalesController())->dispatch();
}
