<?php
/**
 * Outbound email configuration.
 *
 * This file is deliberately server-side only. Do not place SMTP credentials
 * in Settings or JavaScript, because Settings are returned to the browser.
 */
if (!defined('POS_APP')) {
    die('Direct access not permitted.');
}

define('MAIL_SMTP_HOST', 'smtp.gmail.com');
define('MAIL_SMTP_PORT', 587);
define('MAIL_SMTP_USERNAME', 'notifications.service.do.not.reply@gmail.com');
define('MAIL_SMTP_PASSWORD', 'oxqahcsaxwzmhniu');
define('MAIL_FROM_EMAIL', MAIL_SMTP_USERNAME);
define('MAIL_FROM_NAME', 'POS STORE Notifications');
