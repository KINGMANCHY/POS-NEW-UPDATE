<?php
/**
 * categories.php
 * -----------------------------------------------------------------------
 * Front controller for the Categories module page (Phase 2).
 */

define('POS_APP', true);
require_once __DIR__ . '/config/config.php';

SessionManager::start();
Security::applySecurityHeaders();
SessionManager::requireLogin();
SessionManager::requireRole(['Administrator', 'Manager']);

$pageTitle = 'Categories';
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
                <?php require_once __DIR__ . '/views/categories.php'; ?>
            </div>
        </main>
    </div>

    <?php require_once __DIR__ . '/includes/footer.php'; ?>
    <script src="<?= Helper::versionedAsset('/js/categories.js') ?>"></script>
    <script src="<?= Helper::versionedAsset('/js/catalog.js') ?>"></script>
    <script>
    document.querySelectorAll('[data-catalog-tab]').forEach(function (button) { button.addEventListener('click', function () { var tab=this.dataset.catalogTab; document.querySelectorAll('[data-catalog-tab]').forEach(function(x){x.classList.toggle('active',x===button);}); ['categories','units','brands'].forEach(function(name){document.getElementById(name+'Pane').classList.toggle('d-none',name!==tab);}); document.getElementById('btnAddCategory').classList.toggle('d-none',tab!=='categories'); }); });
    </script>
</body>
</html>
