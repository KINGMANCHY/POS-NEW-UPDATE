<?php
/**
 * app/controllers/ReportController.php
 * -----------------------------------------------------------------------
 * AJAX endpoint for the Reports page. Single bundled action so switching
 * date ranges is one round trip instead of five. Read-only; restricted
 * to Administrator/Manager like Sales History and Purchases.
 */

if (!defined('POS_APP') && basename($_SERVER['SCRIPT_FILENAME']) === basename(__FILE__)) {
    require_once dirname(__DIR__, 2) . '/config/config.php';
}

if (!defined('POS_APP')) {
    die('Direct access not permitted.');
}

class ReportController
{
    private Report $reportModel;
    private Expense $expenseModel;

    public function __construct()
    {
        $this->reportModel  = new Report();
        $this->expenseModel = new Expense();
    }

    public function dispatch(): void
    {
        SessionManager::requireRole(['Administrator', 'Manager']);

        switch ($_REQUEST['action'] ?? '') {
            case 'summary':      $this->summary(); break;
            case 'add_expense':  $this->addExpense(); break;
            case 'delete_expense': $this->deleteExpense(); break;
            default: Helper::jsonResponse(false, 'Unknown action.', [], 400);
        }
    }

    private function summary(): void
    {
        [$dateFrom, $dateTo] = $this->resolveDateRange();

        $summary = $this->reportModel->summary($dateFrom, $dateTo);
        $expenseTotal = $this->expenseModel->totalForRange($dateFrom, $dateTo);
        $summary['expense_total'] = $expenseTotal;
        $summary['net_profit'] = round($summary['gross_profit'] - $expenseTotal, 2);

        Helper::jsonResponse(true, '', [
            'date_from'          => $dateFrom,
            'date_to'            => $dateTo,
            'summary'            => $summary,
            'sales_by_day'       => $this->reportModel->salesByDay($dateFrom, $dateTo),
            'top_products'       => $this->reportModel->topProducts($dateFrom, $dateTo, 10),
            'payment_breakdown'  => $this->reportModel->paymentBreakdown($dateFrom, $dateTo),
            'purchase_summary'   => $this->reportModel->purchaseSummary($dateFrom, $dateTo),
            'returns_summary'    => $this->reportModel->returnsSummary($dateFrom, $dateTo),
            'expenses'           => $this->expenseModel->listForRange($dateFrom, $dateTo),
            'expenses_by_category' => $this->reportModel->expensesByCategory($dateFrom, $dateTo),
            'expense_categories' => Expense::CATEGORIES,
        ]);
    }

    private function addExpense(): void
    {
        Security::requireValidCsrfFromRequest();

        $category    = Security::sanitize(trim($_POST['category'] ?? ''));
        $description = Security::sanitize(trim($_POST['description'] ?? ''));
        $amount      = (float) ($_POST['amount'] ?? 0);
        $expenseDate = $_POST['expense_date'] ?? '';

        [$expenseId, $error] = $this->expenseModel->create(
            $category, $description, $amount, $expenseDate, (int) SessionManager::get('user_id')
        );

        if ($error) {
            Helper::jsonResponse(false, $error, [], 422);
        }

        Helper::jsonResponse(true, 'Expense recorded.', ['expense_id' => $expenseId]);
    }

    private function deleteExpense(): void
    {
        Security::requireValidCsrfFromRequest();

        $id = (int) ($_POST['expense_id'] ?? 0);
        if ($id <= 0 || !$this->expenseModel->delete($id)) {
            Helper::jsonResponse(false, 'That expense no longer exists.', [], 404);
        }

        Helper::jsonResponse(true, 'Expense removed.');
    }

    /** Defaults to the current calendar month if no valid range is given. */
    private function resolveDateRange(): array
    {
        $from = $_GET['date_from'] ?? '';
        $to   = $_GET['date_to'] ?? '';

        $validFrom = preg_match('/^\d{4}-\d{2}-\d{2}$/', $from) ? $from : date('Y-m-01');
        $validTo   = preg_match('/^\d{4}-\d{2}-\d{2}$/', $to) ? $to : date('Y-m-d');

        if ($validFrom > $validTo) {
            [$validFrom, $validTo] = [$validTo, $validFrom];
        }

        return [$validFrom, $validTo];
    }
}

if (basename($_SERVER['SCRIPT_FILENAME']) === basename(__FILE__)) {
    SessionManager::start();
    (new ReportController())->dispatch();
}
