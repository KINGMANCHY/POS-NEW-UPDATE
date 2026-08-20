<?php
/**
 * Dynamic web-app manifest.
 *
 * The icon is deliberately read from Settings so a newly uploaded store
 * icon is also the icon offered when the POS is installed on a device.
 */
define('POS_APP', true);
require_once __DIR__ . '/config/config.php';

$iconPath = '';
try {
    $iconPath = (new Setting())->getIconPath();
    if (str_starts_with($iconPath, '/assets/uploads/settings/')
        && !is_file(BASE_PATH . $iconPath)) $iconPath = '';
} catch (Throwable $e) {
    // Keep the install experience functional if the database is unavailable.
}
if ($iconPath === '') {
    $uploadedIcons = glob(BASE_PATH . '/assets/uploads/settings/store-icon-*.ico') ?: [];
    usort($uploadedIcons, static fn(string $a, string $b): int => filemtime($b) <=> filemtime($a));
    if ($uploadedIcons) $iconPath = '/assets/uploads/settings/' . basename($uploadedIcons[0]);
}

// Android's launcher expects PNG install icons. pwa-icon.php extracts the
// PNG stored inside a Settings .ico and returns the requested standard size.
$iconFile = $iconPath !== '' ? BASE_PATH . $iconPath : '';
$iconVersion = $iconFile !== '' && is_file($iconFile) ? basename($iconPath) . '-' . filemtime($iconFile) : '';
$customIcon = $iconVersion !== '';

header('Content-Type: application/manifest+json; charset=utf-8');
// The Settings icon can change at any time. Never let an intermediary retain
// an earlier manifest which would point a new installation at the old icon.
header('Cache-Control: no-store, max-age=0, must-revalidate');
echo json_encode([
    'name' => APP_NAME,
    'short_name' => APP_NAME,
    'start_url' => APP_URL . '/',
    'scope' => APP_URL . '/',
    'display' => 'standalone',
    'background_color' => '#f7f8fb',
    'theme_color' => '#14213d',
    'icons' => $customIcon ? [
        [
            'src' => APP_URL . '/pwa-icon.php?size=192&v=' . rawurlencode($iconVersion),
            'sizes' => '192x192',
            'type' => 'image/png',
            'purpose' => 'any maskable',
        ],
        [
            'src' => APP_URL . '/pwa-icon.php?size=512&v=' . rawurlencode($iconVersion),
            'sizes' => '512x512',
            'type' => 'image/png',
            'purpose' => 'any maskable',
        ],
    ] : [[
        'src' => APP_URL . '/assets/img/pwa-icon.svg',
        'sizes' => 'any',
        'type' => 'image/svg+xml',
        'purpose' => 'any',
    ]],
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
