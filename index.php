<?php
/**
 * index.php
 * -----------------------------------------------------------------------
 * Front controller for authenticated pages.
 * Phase 1 only ships the Foundation (auth + layout shell) - the real
 * Dashboard widgets (sales cards, charts, etc.) land in Phase 2.
 * This page proves the session/layout/security plumbing works end to end.
 */

define('POS_APP', true);
require_once __DIR__ . '/config/config.php';

SessionManager::start();
Security::applySecurityHeaders();
SessionManager::requireLogin();

$pageTitle = 'Dashboard';
$csrfToken = Security::generateCsrfToken();
$today = date('Y-m-d');
$dashboardReport = (new Report())->summary($today, $today);
$dashboardStock = new Inventory();
$lowStockCount = $dashboardStock->lowStockCount();
$dashboardAnalytics = new Report();
$dashboardAnalyticsFrom = date('Y-m-d', strtotime('-6 days'));
$dashboardTrend = $dashboardAnalytics->salesByDay($dashboardAnalyticsFrom, $today);
$dashboardPayments = $dashboardAnalytics->paymentBreakdown($dashboardAnalyticsFrom, $today);
$dashboardTopProducts = $dashboardAnalytics->topProducts($dashboardAnalyticsFrom, $today, 5)['by_revenue'];
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
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div>
                        <h1 class="h4 mb-0">Welcome back, <?= Security::escape(SessionManager::get('full_name')) ?></h1>
                        <p class="text-muted mb-0">Here's what's happening in your store today.</p>
                    </div>
                    <div class="text-end"><div class="h4 mb-0 font-monospace" id="dashboardClock"></div><div class="small text-muted">Local time</div></div>
                </div>

                <div class="row g-3">
                    <div class="col-6 col-md-3">
                        <div class="card pos-card shadow-sm border-0">
                            <div class="card-body">
                                <div class="text-muted small">Today's sales</div>
                                <div class="fw-semibold">₱<?= number_format($dashboardReport['revenue'], 2) ?></div>
                            </div>
                        </div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="card pos-card shadow-sm border-0">
                            <div class="card-body">
                                <div class="text-muted small">Transactions</div>
                                <div class="fw-semibold"><?= $dashboardReport['transaction_count'] ?></div>
                            </div>
                        </div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="card pos-card shadow-sm border-0">
                            <div class="card-body">
                                <div class="text-muted small">Units sold</div>
                                <div class="fw-semibold text-success"><?= $dashboardReport['units_sold'] ?></div>
                            </div>
                        </div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="card pos-card shadow-sm border-0">
                            <div class="card-body">
                                <div class="text-muted small">Low-stock alerts</div>
                                <div class="fw-semibold <?= $lowStockCount ? 'text-danger' : 'text-success' ?>"><?= $lowStockCount ?></div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="d-flex justify-content-between align-items-center mt-5 mb-3 flex-wrap gap-2">
                    <div><h2 class="h5 mb-0">Sales analytics</h2><p class="text-muted small mb-0">Completed sales over the last 7 days.</p></div>
                    <a class="btn btn-sm btn-outline-secondary" href="reports.php">View full reports <i class="bi bi-arrow-right ms-1"></i></a>
                </div>
                <div class="row g-3">
                    <div class="col-lg-8"><div class="card pos-card border-0 shadow-sm h-100"><div class="card-body"><h3 class="h6 mb-3">Daily revenue</h3><canvas id="dashboardRevenueChart" height="130" aria-label="Daily revenue for the last 7 days"></canvas><div class="text-center text-muted py-4 d-none" id="dashboardRevenueEmpty">No completed sales in the last 7 days.</div></div></div></div>
                    <div class="col-lg-4"><div class="card pos-card border-0 shadow-sm h-100"><div class="card-body"><h3 class="h6 mb-3">Payment methods</h3><canvas id="dashboardPaymentChart" height="170" aria-label="Revenue by payment method"></canvas><div class="text-center text-muted py-4 d-none" id="dashboardPaymentEmpty">No payment data yet.</div></div></div></div>
                </div>
                <div class="row g-3 mt-1"><div class="col-lg-8"><div class="card pos-card border-0 shadow-sm"><div class="card-body"><div class="d-flex justify-content-between align-items-center mb-2"><h3 class="h6 mb-0">Best-selling products</h3><span class="small text-muted">Last 7 days</span></div><div class="table-responsive"><table class="table pos-table align-middle mb-0"><thead><tr><th>Product</th><th class="text-end">Units</th><th class="text-end">Revenue</th></tr></thead><tbody><?php if (!$dashboardTopProducts): ?><tr><td colspan="3" class="text-center text-muted py-3">No completed sales yet.</td></tr><?php else: foreach ($dashboardTopProducts as $product): ?><tr><td><?= Security::escape($product['product_name']) ?></td><td class="text-end"><?= (int) $product['quantity_sold'] ?></td><td class="text-end font-monospace">₱<?= number_format((float) $product['revenue'], 2) ?></td></tr><?php endforeach; endif; ?></tbody></table></div></div></div></div></div>
            </div>
        </main>
    </div>

    <?php require_once __DIR__ . '/includes/footer.php'; ?>
    <script>(() => { const c = document.getElementById('dashboardClock'); const tick = () => c.textContent = new Date().toLocaleTimeString(); tick(); setInterval(tick, 1000); })();</script>
    <script>window.DASHBOARD_ANALYTICS = <?= json_encode(['trend' => $dashboardTrend, 'payments' => $dashboardPayments], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;</script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>
    <script src="<?= Helper::versionedAsset('/js/dashboard.js') ?>"></script>

</body>
</html>
