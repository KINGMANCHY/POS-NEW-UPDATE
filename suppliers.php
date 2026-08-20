<?php
define('POS_APP', true);
require_once __DIR__ . '/config/config.php';

SessionManager::start();
Security::applySecurityHeaders();
SessionManager::requireLogin();
SessionManager::requireRole(['Administrator', 'Manager']);

$pageTitle = 'Suppliers';
$csrfToken = Security::generateCsrfToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<?php require_once __DIR__ . '/includes/header.php'; ?>
</head>
<body>
    <?php require_once __DIR__ . '/includes/navbar.php'; ?>
    <div class="pos-shell">
        <?php require_once __DIR__ . '/includes/sidebar.php'; ?>
        <main class="pos-main">
            <div class="container-fluid py-4">
                <?php require_once __DIR__ . '/views/suppliers.php'; ?>
            </div>
        </main>
    </div>
    <?php require_once __DIR__ . '/includes/footer.php'; ?>
    <script src="<?= Helper::versionedAsset('/js/suppliers.js') ?>"></script>
</body>
</html>
