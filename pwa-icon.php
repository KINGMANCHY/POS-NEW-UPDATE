<?php
/**
 * Returns a Settings .ico as a standard PNG PWA icon.
 * Android install surfaces are much more reliable with PNG 192px/512px icons
 * than with an .ico URL, even when the .ico itself contains those images.
 */
define('POS_APP', true);
require_once __DIR__ . '/config/config.php';

function latestStoreIcon(): string
{
    try {
        $path = (new Setting())->getIconPath();
        if (str_starts_with($path, '/assets/uploads/settings/') && is_file(BASE_PATH . $path)) return BASE_PATH . $path;
    } catch (Throwable $e) {
        // Use the newest upload when Settings cannot be read.
    }
    $icons = glob(BASE_PATH . '/assets/uploads/settings/store-icon-*.ico') ?: [];
    usort($icons, static fn(string $a, string $b): int => filemtime($b) <=> filemtime($a));
    return $icons[0] ?? '';
}

function pngFromIco(string $path): string
{
    $ico = @file_get_contents($path);
    if (!is_string($ico) || strlen($ico) < 6 || substr($ico, 0, 4) !== "\x00\x00\x01\x00") return '';
    $count = unpack('vcount', substr($ico, 4, 2))['count'];
    $best = '';
    $bestArea = 0;
    for ($index = 0; $index < $count; $index++) {
        $entryOffset = 6 + ($index * 16);
        if ($entryOffset + 16 > strlen($ico)) break;
        $width = ord($ico[$entryOffset]) ?: 256;
        $height = ord($ico[$entryOffset + 1]) ?: 256;
        $bytes = unpack('Vsize', substr($ico, $entryOffset + 8, 4))['size'];
        $offset = unpack('Voffset', substr($ico, $entryOffset + 12, 4))['offset'];
        if ($offset + $bytes > strlen($ico)) continue;
        $candidate = substr($ico, $offset, $bytes);
        if (substr($candidate, 0, 8) === "\x89PNG\r\n\x1a\n" && $width * $height > $bestArea) {
            $best = $candidate;
            $bestArea = $width * $height;
        }
    }
    return $best;
}

$size = (int) ($_GET['size'] ?? 192);
if (!in_array($size, [192, 512], true)) $size = 192;
$png = pngFromIco(latestStoreIcon());
$source = $png !== '' ? @imagecreatefromstring($png) : false;
if ($source === false) {
    http_response_code(404);
    exit;
}

$output = imagecreatetruecolor($size, $size);
imagealphablending($output, false);
imagesavealpha($output, true);
imagefilledrectangle($output, 0, 0, $size, $size, imagecolorallocatealpha($output, 0, 0, 0, 127));
imagecopyresampled($output, $source, 0, 0, 0, 0, $size, $size, imagesx($source), imagesy($source));
header('Content-Type: image/png');
header('Cache-Control: no-store, max-age=0, must-revalidate');
imagepng($output);
imagedestroy($source);
imagedestroy($output);
