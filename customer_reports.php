<?php

define('POS_APP', true);

require_once __DIR__ . '/config/config.php';

SessionManager::start();
Security::applySecurityHeaders();
SessionManager::requireRole(['Administrator', 'Manager']);

$pageTitle = 'Customer Reports';
$csrfToken = Security::generateCsrfToken();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php require __DIR__ . '/includes/header.php'; ?>
</head>
<body>
    <?php require __DIR__ . '/includes/navbar.php'; ?>
    <div class="pos-shell">
        <?php require __DIR__ . '/includes/sidebar.php'; ?>
        <main class="pos-main">
            <div class="container-fluid py-4">
                <?php require __DIR__ . '/views/customer_reports.php'; ?>
            </div>
        </main>
    </div>
    <?php require __DIR__ . '/includes/footer.php'; ?>
    <script src="<?= Helper::versionedAsset('/js/customer_reports.js') ?>"></script>
</body>


