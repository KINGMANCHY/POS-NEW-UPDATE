<?php
/**
 * includes/sidebar.php
 * -----------------------------------------------------------------------
 * Left navigation sidebar. Module links are placeholders for now -
 * they route to their real controllers as each phase is delivered.
 */
if (!defined('POS_APP')) {
    die('Direct access not permitted.');
}

$currentPage = basename($_SERVER['PHP_SELF']);

$navItems = [
    ['icon' => 'bi-speedometer2',  'label' => 'Dashboard',  'href' => 'index.php',   'active' => $currentPage === 'index.php'],
    ['icon' => 'bi-cart3',         'label' => 'POS Screen',  'href' => 'pos.php', 'active' => $currentPage === 'pos.php'],
    ['icon' => 'bi-box-seam',      'label' => 'Products',    'href' => 'products.php', 'active' => $currentPage === 'products.php'],
    ['icon' => 'bi-tags',          'label' => 'Categories',  'href' => 'categories.php', 'active' => $currentPage === 'categories.php'],
    ['icon' => 'bi-people',        'label' => 'Customers',   'href' => 'customers.php', 'active' => $currentPage === 'customers.php'],
    ['icon' => 'bi-person-lines-fill', 'label' => 'Customer Reports', 'href' => 'customer_reports.php', 'active' => $currentPage === 'customer_reports.php'],
    ['icon' => 'bi-truck',         'label' => 'Suppliers',   'href' => 'suppliers.php', 'active' => $currentPage === 'suppliers.php'],
    ['icon' => 'bi-boxes',         'label' => 'Inventory',   'href' => 'inventory.php', 'active' => $currentPage === 'inventory.php'],
    ['icon' => 'bi-journal-text',  'label' => 'Item Ledger', 'href' => 'ledger.php', 'active' => $currentPage === 'ledger.php'],
    ['icon' => 'bi-receipt',       'label' => 'Sales',       'href' => 'sales.php', 'active' => $currentPage === 'sales.php'],
    ['icon' => 'bi-calculator',    'label' => 'Reconciliation', 'href' => 'reconciliation.php', 'active' => $currentPage === 'reconciliation.php'],
    ['icon' => 'bi-bag',           'label' => 'Purchases',   'href' => 'purchases.php', 'active' => $currentPage === 'purchases.php'],
    ['icon' => 'bi-bar-chart',     'label' => 'Reports',     'href' => 'reports.php', 'active' => $currentPage === 'reports.php'],
    ['icon' => 'bi-shield-lock',   'label' => 'Roles & Permissions', 'href' => 'roles.php', 'active' => $currentPage === 'roles.php'],
    ['icon' => 'bi-clock-history', 'label' => 'Activity Logs', 'href' => 'activity_logs.php', 'active' => $currentPage === 'activity_logs.php'],
    ['icon' => 'bi-sliders',       'label' => 'Settings',    'href' => 'settings.php', 'active' => $currentPage === 'settings.php'],
];
?>
<aside class="pos-sidebar" id="posSidebar">
    <nav class="nav flex-column pos-sidebar-nav">
        <?php foreach ($navItems as $item): ?>
            <a class="nav-link pos-sidebar-link <?= !empty($item['active']) ? 'active' : '' ?>" href="<?= Security::escape($item['href']) ?>">
                <i class="bi <?= Security::escape($item['icon']) ?>"></i>
                <span class="pos-sidebar-label"><?= Security::escape($item['label']) ?></span>
                <?php if (!empty($item['badge'])): ?>
                    <span class="badge pos-badge-soft ms-auto"><?= Security::escape($item['badge']) ?></span>
                <?php endif; ?>
            </a>
        <?php endforeach; ?>
    </nav>
</aside>
