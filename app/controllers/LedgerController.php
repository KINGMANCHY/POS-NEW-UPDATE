<?php
/**
 * app/controllers/LedgerController.php
 * -----------------------------------------------------------------------
 * AJAX endpoint for the Item Ledger page: a classic per-product stock
 * card (opening balance -> in/out movements -> closing balance) backed
 * by InventoryMovements, plus Excel/PDF export of the same data.
 * Administrator/Manager only, like Inventory and Reports.
 */

if (!defined('POS_APP') && basename($_SERVER['SCRIPT_FILENAME']) === basename(__FILE__)) {
    require_once dirname(__DIR__, 2) . '/config/config.php';
}

if (!defined('POS_APP')) {
    die('Direct access not permitted.');
}

class LedgerController
{
    private InventoryMovement $movementModel;
    private Product $productModel;

    public const TYPE_LABELS = [
        'purchase'        => 'Purchase (In)',
        'purchase_cancel' => 'Purchase Cancelled (Out)',
        'sale'            => 'Sale (Out)',
        'sale_void'       => 'Sale Voided (In)',
        'adjustment'      => 'Manual Adjustment',
    ];

    public function __construct()
    {
        $this->movementModel = new InventoryMovement();
        $this->productModel  = new Product();
    }

    public function dispatch(): void
    {
        SessionManager::requireRole(['Administrator', 'Manager']);

        switch ($_REQUEST['action'] ?? '') {
            case 'products':     $this->products(); break;
            case 'ledger':       $this->ledger(); break;
            case 'export_excel': $this->exportExcel(); break;
            case 'export_pdf':   $this->exportPdf(); break;
            default: Helper::jsonResponse(false, 'Unknown action.', [], 400);
        }
    }

    private function products(): void
    {
        $term = Security::sanitize(trim($_GET['search'] ?? ''));
        Helper::jsonResponse(true, '', ['products' => $this->productModel->searchForPos($term, 15)]);
    }

    private function ledger(): void
    {
        [$productId, $dateFrom, $dateTo, $product, $error] = $this->resolveRequest();
        if ($error) {
            Helper::jsonResponse(false, $error, [], 422);
        }

        $result = $this->movementModel->ledger($productId, $dateFrom, $dateTo);
        Helper::jsonResponse(true, '', [
            'product'   => $product,
            'date_from' => $dateFrom,
            'date_to'   => $dateTo,
            'result'    => $this->withLabels($result),
        ]);
    }

    private function exportExcel(): void
    {
        [$productId, $dateFrom, $dateTo, $product, $error] = $this->resolveRequest();
        if ($error) {
            die($error); // A GET download link has no JSON channel to report errors through - a plain message is the best a browser download can show.
        }

        $result = $this->movementModel->ledger($productId, $dateFrom, $dateTo);
        $headers = ['Date', 'Type', 'Reference', 'In', 'Out', 'Balance', 'Notes', 'By'];

        $rows = [];
        $rows[] = ['', 'Opening Balance', '', '', '', $result['opening_balance'], '', ''];
        foreach ($result['movements'] as $m) {
            $rows[] = [
                $m['created_at'],
                self::TYPE_LABELS[$m['movement_type']] ?? $m['movement_type'],
                $m['reference_code'] ?? '',
                $m['quantity_change'] > 0 ? $m['quantity_change'] : '',
                $m['quantity_change'] < 0 ? abs($m['quantity_change']) : '',
                $m['balance_after'],
                $m['notes'] ?? '',
                $m['user_name'] ?? '',
            ];
        }
        $rows[] = ['', 'Closing Balance', '', $result['total_in'], $result['total_out'], $result['closing_balance'], '', ''];

        $filename = 'ledger_' . $product['product_code'] . '_' . $dateFrom . '_to_' . $dateTo;
        XlsxWriter::stream($filename, $headers, $rows, substr($product['product_name'], 0, 28));
    }

    private function exportPdf(): void
    {
        [$productId, $dateFrom, $dateTo, $product, $error] = $this->resolveRequest();
        if ($error) {
            die($error);
        }

        $result = $this->movementModel->ledger($productId, $dateFrom, $dateTo);
        $headers = ['Date', 'Type', 'Reference', 'In', 'Out', 'Balance'];
        $colWidths = [80, 130, 90, 40, 40, 55];

        $rows = [];
        $rows[] = ['', 'Opening Balance', '', '', '', (string) $result['opening_balance']];
        foreach ($result['movements'] as $m) {
            $rows[] = [
                substr($m['created_at'], 0, 16),
                self::TYPE_LABELS[$m['movement_type']] ?? $m['movement_type'],
                $m['reference_code'] ?? '',
                $m['quantity_change'] > 0 ? (string) $m['quantity_change'] : '',
                $m['quantity_change'] < 0 ? (string) abs($m['quantity_change']) : '',
                (string) $m['balance_after'],
            ];
        }
        $rows[] = ['', 'Closing Balance', '', (string) $result['total_in'], (string) $result['total_out'], (string) $result['closing_balance']];

        $title = 'Item Ledger - ' . $product['product_name'] . ' (' . $product['product_code'] . ')';
        $subtitle = $dateFrom . ' to ' . $dateTo;
        $filename = 'ledger_' . $product['product_code'] . '_' . $dateFrom . '_to_' . $dateTo;

        PdfWriter::stream($filename, $title, $subtitle, $headers, $colWidths, $rows);
    }

    /** Adds a human-readable label to each movement row for display (kept out of the model - that's a presentation concern). */
    private function withLabels(array $result): array
    {
        $result['movements'] = array_map(function ($m) {
            $m['type_label'] = self::TYPE_LABELS[$m['movement_type']] ?? $m['movement_type'];
            return $m;
        }, $result['movements']);
        return $result;
    }

    /** Shared validation for all four actions. Returns [productId, dateFrom, dateTo, product, error]. */
    private function resolveRequest(): array
    {
        $productId = (int) ($_GET['product_id'] ?? 0);
        $from = $_GET['date_from'] ?? '';
        $to   = $_GET['date_to'] ?? '';

        $dateFrom = preg_match('/^\d{4}-\d{2}-\d{2}$/', $from) ? $from : date('Y-m-01');
        $dateTo   = preg_match('/^\d{4}-\d{2}-\d{2}$/', $to) ? $to : date('Y-m-d');
        if ($dateFrom > $dateTo) {
            [$dateFrom, $dateTo] = [$dateTo, $dateFrom];
        }

        if ($productId <= 0) {
            return [0, $dateFrom, $dateTo, null, 'Please select a product.'];
        }

        $product = $this->productModel->find($productId);
        if (!$product) {
            return [0, $dateFrom, $dateTo, null, 'Product not found.'];
        }

        return [$productId, $dateFrom, $dateTo, $product, null];
    }
}

if (basename($_SERVER['SCRIPT_FILENAME']) === basename(__FILE__)) {
    SessionManager::start();
    (new LedgerController())->dispatch();
}
