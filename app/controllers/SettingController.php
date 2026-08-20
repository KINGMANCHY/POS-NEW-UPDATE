<?php
/**
 * app/controllers/SettingController.php
 * -----------------------------------------------------------------------
 * AJAX endpoint for the Settings page. Administrator-only.
 */

if (!defined('POS_APP') && basename($_SERVER['SCRIPT_FILENAME']) === basename(__FILE__)) {
    require_once dirname(__DIR__, 2) . '/config/config.php';
}

if (!defined('POS_APP')) {
    die('Direct access not permitted.');
}

class SettingController
{
    private Setting $settingModel;
    private User $userModel;

    public function __construct()
    {
        $this->settingModel = new Setting();
        $this->userModel    = new User();
    }

    public function dispatch(): void
    {
        SessionManager::requireRole(['Administrator']);

        switch ($_REQUEST['action'] ?? '') {
            case 'get':  $this->get(); break;
            case 'save': $this->save(); break;
            default: Helper::jsonResponse(false, 'Unknown action.', [], 400);
        }
    }

    private function get(): void
    {
        $settings = $this->settingModel->getAll();
        $smtp = $this->settingModel->getSmtpConfig();
        $settings['email_smtp_host'] = $smtp['host'];
        $settings['email_smtp_port'] = $smtp['port'];
        $settings['email_smtp_username'] = $smtp['username'];
        $settings['email_smtp_password_set'] = $smtp['password'] !== '' ? '1' : '0';
        Helper::jsonResponse(true, '', ['settings' => $settings]);
    }

    private function save(): void
    {
        Security::requireValidCsrfFromRequest();

        $storeName = Security::sanitize(trim($_POST['store_name'] ?? ''));
        if ($storeName === '') {
            Helper::jsonResponse(false, 'Store name is required.', [], 422);
        }
        $loyaltySpend = (float) ($_POST['loyalty_spend_amount'] ?? 0);
        $loyaltyPoints = (int) ($_POST['loyalty_points_awarded'] ?? 0);
        $loyaltyPointValue = (float) ($_POST['loyalty_point_value'] ?? 1);
        if ($loyaltySpend < 0 || $loyaltyPoints < 0 || $loyaltyPointValue < 0) {
            Helper::jsonResponse(false, 'Loyalty amounts cannot be negative.', [], 422);
        }
        $receiptTemplate = trim($_POST['receipt_template'] ?? '');
        if (!in_array($receiptTemplate, ['classic', 'modern'], true)) {
            $receiptTemplate = 'classic';
        }
        $printerWidth = trim($_POST['printer_width'] ?? '');
        if (!in_array($printerWidth, ['58', '80'], true)) {
            $printerWidth = '80';
        }
        $smtpHost = trim($_POST['email_smtp_host'] ?? '');
        $smtpPort = (int) ($_POST['email_smtp_port'] ?? 0);
        $smtpUsername = trim($_POST['email_smtp_username'] ?? '');
        if ($smtpHost === '' || preg_match('/[\r\n\s]/', $smtpHost) || $smtpPort < 1 || $smtpPort > 65535 || !filter_var($smtpUsername, FILTER_VALIDATE_EMAIL)) {
            Helper::jsonResponse(false, 'Please enter valid SMTP host, port, and email address.', [], 422);
        }

        $values = [
            'store_name'      => $storeName,
            'store_address'   => Security::sanitize(trim($_POST['store_address'] ?? '')),
            'store_phone'     => Security::sanitize(trim($_POST['store_phone'] ?? '')),
            'currency_symbol' => Security::sanitize(trim($_POST['currency_symbol'] ?? '')) ?: '₱',
            'tax_inclusive'   => !empty($_POST['tax_inclusive']) ? '1' : '0',
            'receipt_footer'  => Security::sanitize(trim($_POST['receipt_footer'] ?? '')),
            'show_store_on_receipt' => !empty($_POST['show_store_on_receipt']) ? '1' : '0',
            'show_receipt_after_sale' => !empty($_POST['show_receipt_after_sale']) ? '1' : '0',
            'auto_print_receipt' => !empty($_POST['auto_print_receipt']) ? '1' : '0',
            'receipt_template' => $receiptTemplate,
            'printer_width'    => $printerWidth,
            'cash_payment_only' => !empty($_POST['cash_payment_only']) ? '1' : '0',
            'mobile_fullscreen' => !empty($_POST['mobile_fullscreen']) ? '1' : '0',
            'loyalty_spend_amount' => (string) $loyaltySpend,
            'loyalty_points_awarded' => (string) $loyaltyPoints,
            'loyalty_point_value' => (string) $loyaltyPointValue,
            'email_password_reset_enabled' => !empty($_POST['email_password_reset_enabled']) ? '1' : '0',
            'email_smtp_host' => $smtpHost,
            'email_smtp_port' => (string) $smtpPort,
            'email_smtp_username' => $smtpUsername,
        ];
        $smtpPassword = trim($_POST['email_smtp_password'] ?? '');
        if ($smtpPassword !== '') $values['email_smtp_password'] = $smtpPassword;
        if (!empty($_FILES['store_icon']['tmp_name'])) {
            $icon = $_FILES['store_icon'];
            $extension = strtolower(pathinfo($icon['name'] ?? '', PATHINFO_EXTENSION));
            $iconHeader = @file_get_contents($icon['tmp_name'], false, null, 0, 6);
            $isIco = is_string($iconHeader)
                && strlen($iconHeader) === 6
                && substr($iconHeader, 0, 4) === "\x00\x00\x01\x00"
                && unpack('vcount', substr($iconHeader, 4, 2))['count'] > 0;
            if (($icon['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || $extension !== 'ico' || ($icon['size'] ?? 0) > 1024 * 1024 || !$isIco) {
                Helper::jsonResponse(false, 'Store icon must be a valid .ico file no larger than 1 MB.', [], 422);
            }
            $uploadDirectory = BASE_PATH . '/assets/uploads/settings';
            if (!is_dir($uploadDirectory) && !mkdir($uploadDirectory, 0755, true) && !is_dir($uploadDirectory)) {
                Helper::jsonResponse(false, 'Could not create the icon upload directory.', [], 500);
            }
            $filename = 'store-icon-' . bin2hex(random_bytes(8)) . '.ico';
            if (!move_uploaded_file($icon['tmp_name'], $uploadDirectory . '/' . $filename)) {
                Helper::jsonResponse(false, 'Could not upload the store icon.', [], 500);
            }
            $values['store_icon_path'] = '/assets/uploads/settings/' . $filename;
        }

        $this->settingModel->updateMany($values);
        $this->userModel->logActivity((int) SessionManager::get('user_id'), 'SETTINGS_UPDATE', 'Updated store settings');
        Helper::jsonResponse(true, 'Settings saved.', ['settings' => $values]);
    }
}

if (basename($_SERVER['SCRIPT_FILENAME']) === basename(__FILE__)) {
    SessionManager::start();
    (new SettingController())->dispatch();
}
