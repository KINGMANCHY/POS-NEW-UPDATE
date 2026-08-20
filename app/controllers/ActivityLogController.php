<?php
/**
 * app/controllers/ActivityLogController.php
 * -----------------------------------------------------------------------
 * AJAX endpoint for the Activity Logs page. Read-only, Administrator-only.
 */

if (!defined('POS_APP') && basename($_SERVER['SCRIPT_FILENAME']) === basename(__FILE__)) {
    require_once dirname(__DIR__, 2) . '/config/config.php';
}

if (!defined('POS_APP')) {
    die('Direct access not permitted.');
}

class ActivityLogController
{
    private ActivityLog $logModel;

    public function __construct()
    {
        $this->logModel = new ActivityLog();
    }

    public function dispatch(): void
    {
        SessionManager::requireRole(['Administrator']);

        switch ($_REQUEST['action'] ?? '') {
            case 'list':      $this->list(); break;
            case 'form_data': $this->formData(); break;
            default: Helper::jsonResponse(false, 'Unknown action.', [], 400);
        }
    }

    private function list(): void
    {
        $filters = [
            'search'    => Security::sanitize(trim($_GET['search'] ?? '')),
            'user_id'   => !empty($_GET['user_id']) ? (int) $_GET['user_id'] : null,
            'date_from' => Security::sanitize($_GET['date_from'] ?? ''),
            'date_to'   => Security::sanitize($_GET['date_to'] ?? ''),
        ];

        $result = $this->logModel->paginate($filters, (int) ($_GET['page'] ?? 1), (int) ($_GET['per_page'] ?? 25));
        Helper::jsonResponse(true, '', ['result' => $result]);
    }

    private function formData(): void
    {
        Helper::jsonResponse(true, '', ['users' => $this->logModel->usersWithActivity()]);
    }
}

if (basename($_SERVER['SCRIPT_FILENAME']) === basename(__FILE__)) {
    SessionManager::start();
    (new ActivityLogController())->dispatch();
}
