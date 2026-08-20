<?php
define('POS_APP', true);
require_once __DIR__ . '/config/config.php';
SessionManager::start();
Security::applySecurityHeaders();
SessionManager::requireLogin();
$pageTitle = 'Access Denied';
$csrfToken = Security::generateCsrfToken();
?><!DOCTYPE html><html lang="en"><head><?php require __DIR__ . '/includes/header.php'; ?></head><body>
<?php require __DIR__ . '/includes/navbar.php'; ?><div class="pos-shell"><?php require __DIR__ . '/includes/sidebar.php'; ?><main class="pos-main"><div class="container-fluid py-4"><div class="card pos-card border-0 shadow-sm mx-auto" style="max-width:600px"><div class="card-body text-center py-5"><i class="bi bi-shield-lock text-danger" style="font-size:3rem"></i><h1 class="h4 mt-3">Access Denied</h1><p class="text-muted mb-4">Your role does not have permission to open this menu. Ask an administrator to enable it in Roles &amp; Permissions.</p><a class="btn pos-btn-primary" href="<?= APP_URL ?>/index.php">Back to Dashboard</a></div></div></div></main></div><?php require __DIR__ . '/includes/footer.php'; ?></body></html>
