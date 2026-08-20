<?php
/**
 * includes/header.php
 * -----------------------------------------------------------------------
 * Shared <head> content. Included inside an <html><head> ... </head> block
 * by any authenticated page. Expects $pageTitle to be set by the caller.
 */
if (!defined('POS_APP')) {
    die('Direct access not permitted.');
}
?>
<?php
$faviconAsset = '';
try {
    $iconPath = (new Setting())->getIconPath();
    if ($iconPath !== '' && str_starts_with($iconPath, '/assets/uploads/settings/')) {
        $candidate = substr($iconPath, strlen('/assets'));
        if (is_file(BASE_PATH . '/assets' . $candidate)) $faviconAsset = $candidate;
    }
} catch (Throwable $e) {
    // Fall through to the latest uploaded icon if Settings is unavailable.
}
// A freshly uploaded icon is still usable even if its Settings row has not
// been saved yet. This also avoids a broken browser-tab icon when no legacy
// assets/img/favicon.png file exists.
if ($faviconAsset === '') {
    $icons = glob(BASE_PATH . '/assets/uploads/settings/store-icon-*.ico') ?: [];
    usort($icons, static fn(string $a, string $b): int => filemtime($b) <=> filemtime($a));
    if ($icons) $faviconAsset = '/uploads/settings/' . basename($icons[0]);
}
$faviconUrl = $faviconAsset !== '' ? Helper::versionedAsset($faviconAsset) : '';
// A distinct manifest URL after each icon upload makes browsers fetch the
// changed manifest instead of reusing an earlier install-icon definition.
$manifestVersion = $faviconAsset !== '' ? basename($faviconAsset) : (string) @filemtime(BASE_PATH . '/assets/img/pwa-icon.svg');
$mobileFullscreen = false;
try {
    $mobileFullscreen = (new Setting())->getAll()['mobile_fullscreen'] === '1';
} catch (Throwable $e) {
    // The site remains usable if Settings has not been migrated yet.
}
?>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="default">
<meta name="theme-color" content="#14213d">
<script>
    // Applied before the stylesheet paints, straight from localStorage, so
    // there is no flash of the wrong theme on load. Light is the default
    // for anyone who has never chosen a theme on this device.
    (function () {
        try {
            var saved = localStorage.getItem('pos_theme');
            if (saved === 'dark') document.documentElement.setAttribute('data-theme', 'dark');
        } catch (e) { /* private-mode storage denial - default to light */ }
    })();
</script>
<link rel="manifest" href="<?= Security::escape(APP_URL) ?>/manifest.php?v=<?= Security::escape($manifestVersion) ?>">
<meta name="csrf-token" content="<?= Security::escape($csrfToken ?? '') ?>">
<meta name="robots" content="noindex, nofollow">
<title><?= Security::escape($pageTitle ?? 'Dashboard') ?> · <?= Security::escape(APP_NAME) ?></title>

<?php if ($faviconUrl !== ''): ?>
<!-- Favicon -->
<link rel="shortcut icon" type="image/x-icon" href="<?= Security::escape($faviconUrl) ?>">
<link rel="icon" type="image/x-icon" href="<?= Security::escape($faviconUrl) ?>">
<link rel="apple-touch-icon" href="<?= Security::escape($faviconUrl) ?>">
<?php endif; ?>

<!-- Bootstrap 5 -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<!-- Bootstrap Icons -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
<!-- Google Fonts: Space Grotesk (display) / Inter (body) / JetBrains Mono (codes, prices) -->
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@500;600;700&family=Inter:wght@400;500;600&family=JetBrains+Mono:wght@500;600&display=swap" rel="stylesheet">
<!-- App styles -->
<link rel="stylesheet" href="<?= Helper::versionedAsset('/css/style.css') ?>">

<!--
    window.APP_URL - every page's JS builds its AJAX endpoint URLs from
    this (e.g. window.APP_URL + '/app/controllers/ProductController.php').
    It was never actually defined anywhere, so it silently fell back to
    a relative path - which only happens to work if the app is hosted
    at the web server's root. Any subfolder deployment (BASE_URL is set
    to '/pos_store' below) broke EVERY AJAX call: search, add, edit,
    delete, the POS Screen cart, all of it, all with the same generic
    "Something went wrong" message. Defining it here fixes all of that
    at once instead of needing a fix in every individual .js file.
-->
<script>window.APP_URL = <?= json_encode(APP_URL) ?>; window.POS_CONFIG = { mobileFullscreen: <?= $mobileFullscreen ? 'true' : 'false' ?> };</script>
