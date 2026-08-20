<?php
/**
 * app/models/Setting.php
 * -----------------------------------------------------------------------
 * Data access for the Settings table (a plain key/value store - see the
 * seed data in database/pos_store.sql for the keys this app expects:
 * store_name, store_address, store_phone, currency_symbol, tax_inclusive,
 * receipt_footer). Only the known keys are ever written - this is not a
 * generic arbitrary-key editor, to keep the settings form predictable.
 */

if (!defined('POS_APP')) {
    die('Direct access not permitted.');
}

class Setting
{
    private PDO $db;

    /** The only keys this app reads/writes. Anything else in the table (future keys) is left alone. */
    public const KNOWN_KEYS = [
        'store_name', 'store_address', 'store_phone',
        'currency_symbol', 'tax_inclusive', 'receipt_footer', 'show_store_on_receipt',
        'show_receipt_after_sale', 'auto_print_receipt', 'mobile_fullscreen',
        'cash_payment_only', 'receipt_template', 'printer_width',
        'loyalty_spend_amount', 'loyalty_points_awarded', 'loyalty_point_value',
        'email_password_reset_enabled', 'email_smtp_host', 'email_smtp_port',
        'email_smtp_username', 'email_smtp_password', 'store_icon_path',
    ];

    /** These are only returned by the administrator Settings controller. */
    private const PRIVATE_KEYS = ['email_smtp_host', 'email_smtp_port', 'email_smtp_username', 'email_smtp_password'];

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    /** Returns setting_key => setting_value for every known key, defaulting missing ones to ''. */
    public function getAll(bool $includePrivate = false): array
    {
        $stmt = $this->db->query("SELECT setting_key, setting_value FROM Settings");
        $rows = $stmt->fetchAll();

        $keys = $includePrivate ? self::KNOWN_KEYS : array_values(array_diff(self::KNOWN_KEYS, self::PRIVATE_KEYS));
        $map = array_fill_keys($keys, '');
        foreach ($rows as $row) {
            if (in_array($row['setting_key'], $keys, true)) {
                $map[$row['setting_key']] = (string) $row['setting_value'];
            }
        }
        return $map;
    }

    /** SMTP values are intentionally read only server-side; the password is never returned in a web response. */
    public function getSmtpConfig(): array
    {
        $defaults = ['host' => MAIL_SMTP_HOST, 'port' => (string) MAIL_SMTP_PORT, 'username' => MAIL_SMTP_USERNAME, 'password' => MAIL_SMTP_PASSWORD];
        $stmt = $this->db->query("SELECT setting_key, setting_value FROM Settings WHERE setting_key IN ('email_smtp_host', 'email_smtp_port', 'email_smtp_username', 'email_smtp_password')");
        foreach ($stmt->fetchAll() as $row) {
            $key = str_replace('email_smtp_', '', $row['setting_key']);
            if (array_key_exists($key, $defaults) && (string) $row['setting_value'] !== '') $defaults[$key] = (string) $row['setting_value'];
        }
        return $defaults;
    }

    public function getIconPath(): string
    {
        $stmt = $this->db->prepare("SELECT setting_value FROM Settings WHERE setting_key = 'store_icon_path'");
        $stmt->execute();
        $row = $stmt->fetch();
        return $row ? (string) $row['setting_value'] : '';
    }

    /** Upserts each given key/value pair. Unknown keys are silently ignored. */
    public function updateMany(array $values): void
    {
        $update = $this->db->prepare("UPDATE Settings SET setting_value = :value, updated_at = GETDATE() WHERE setting_key = :key");
        $insert = $this->db->prepare("INSERT INTO Settings (setting_key, setting_value, updated_at) VALUES (:key, :value, GETDATE())");

        $this->db->beginTransaction();
        try {
            foreach (self::KNOWN_KEYS as $key) {
                if (!array_key_exists($key, $values)) {
                    continue;
                }
                $value = (string) $values[$key];

                $update->bindValue(':key', $key, PDO::PARAM_STR);
                $update->bindValue(':value', $value, PDO::PARAM_STR);
                $update->execute();

                if ($update->rowCount() === 0) {
                    $insert->bindValue(':key', $key, PDO::PARAM_STR);
                    $insert->bindValue(':value', $value, PDO::PARAM_STR);
                    $insert->execute();
                }
            }
            $this->db->commit();
        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }
}
