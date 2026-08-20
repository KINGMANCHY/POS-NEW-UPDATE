<?php
/**
 * app/controllers/ReconciliationController.php
 * -----------------------------------------------------------------------
 * AJAX endpoint for the Transaction Record & End of Day Reconciliation
 * page. Restricted to Administrator/Manager, same as Sales History and
 * Reports - closing out the drawer is a supervisory action.
 */

if (!defined('POS_APP') && basename($_SERVER['SCRIPT_FILENAME']) === basename(__FILE__)) {
    require_once dirname(__DIR__, 2) . '/config/config.php';
}

if (!defined('POS_APP')) {
    die('Direct access not permitted.');
}

class ReconciliationController
{
    private Reconciliation $model;

    public function __construct()
    {
        $this->model = new Reconciliation();
    }

    public function dispatch(): void
    {
        SessionManager::requireRole(['Administrator', 'Manager']);

        switch ($_REQUEST['action'] ?? '') {
            case 'day':  $this->day(); break;
            case 'save': $this->save(); break;
            default: Helper::jsonResponse(false, 'Unknown action.', [], 400);
        }
    }

    private function resolveDate(): string
    {
        $date = $_REQUEST['date'] ?? '';
        // A stray/invalid date silently falling back to "today" is safer
        // here than either crashing or querying with a bad string.
        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) ? $date : date('Y-m-d');
    }

    private function day(): void
    {
        $date = $this->resolveDate();

        $existing = $this->model->getForDate($date);
        $movement = $this->model->cashMovementForDate($date);
        $openingFloat = $existing['opening_float'] ?? $this->model->suggestedOpeningFloat($date);
        $expectedCash = round($openingFloat + $movement['cash_collected'] - $movement['change_given'], 2);

        Helper::jsonResponse(true, '', [
            'date'                => $date,
            'transactions'        => $this->model->transactionsForDate($date),
            'payment_breakdown'   => $this->model->paymentBreakdownForDate($date),
            'cash_movement'       => $movement,
            'suggested_opening_float' => $openingFloat,
            'expected_cash'       => $expectedCash,
            'existing'            => $existing,
        ]);
    }

    private function save(): void
    {
        Security::requireValidCsrfFromRequest();

        $date         = $this->resolveDate();
        $openingFloat = (float) ($_POST['opening_float'] ?? 0);
        $countedCash  = (float) ($_POST['counted_cash'] ?? 0);
        $notes        = Security::sanitize(trim($_POST['notes'] ?? ''));

        if ($openingFloat < 0 || $countedCash < 0) {
            Helper::jsonResponse(false, 'Opening float and counted cash cannot be negative.', [], 422);
        }

        $result = $this->model->save($date, (int) SessionManager::get('user_id'), $openingFloat, $countedCash, $notes);

        Helper::jsonResponse(true, 'Reconciliation saved.', [
            'expected_cash' => $result['expected_cash'],
            'variance'      => $result['variance'],
        ]);
    }
}

if (basename($_SERVER['SCRIPT_FILENAME']) === basename(__FILE__)) {
    SessionManager::start();
    (new ReconciliationController())->dispatch();
}
