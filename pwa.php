<?php
/** Public, database/session-free install metadata. Never bootstrap the CRM here. */
require_once __DIR__ . '/application/helpers/app_branding_helper.php';
header('Cache-Control: no-store, max-age=0');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: no-referrer');
if (!in_array($_SERVER['REQUEST_METHOD'] ?? 'GET', ['GET', 'HEAD'], true)) {
    header('Allow: GET, HEAD'); http_response_code(405); exit;
}
$script = parse_url($_SERVER['SCRIPT_NAME'] ?? '/pwa.php', PHP_URL_PATH);
$root = rtrim(str_replace('\\', '/', dirname($script)), '/') . '/';
// Reject malformed server routing; never trust Host or forwarded headers for scope.
if (!preg_match('#^/(?:[a-zA-Z0-9_.%~-]+/)*$#', $root)) {
    http_response_code(400); exit;
}
$resource = $_GET['resource'] ?? 'manifest';
if ($resource === 'worker') {
    header('Content-Type: application/javascript; charset=utf-8');
    header('Service-Worker-Allowed: ' . $root);
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'HEAD') readfile(__DIR__ . '/assets/pwa/worker.js');
    exit;
}
if (!in_array($resource, ['manifest', 'icon-192', 'icon-512', 'icon-maskable-512'], true)) { http_response_code(404); exit; }
$config = [];
require __DIR__ . '/application/config/branding.php';
$icons = [];
foreach (['192' => 192, '512' => 512, 'maskable-512' => 512] as $variant => $size) {
    $relative = $config['pwa_icon_' . str_replace('-', '_', (string) $variant)] ?? '';
    $file = is_string($relative) ? realpath(__DIR__ . '/' . $relative) : false;
    $public = realpath(__DIR__ . '/assets');
    $info = $file && strpos($file, $public . DIRECTORY_SEPARATOR) === 0 ? @getimagesize($file) : false;
    // Only exact-size local public PNGs, never tenant uploads, URLs or SVG scripts.
    if (!$info || $info[0] !== $size || $info[1] !== $size || $info[2] !== IMAGETYPE_PNG
        || !preg_match('~^assets/[a-zA-Z0-9_./-]+\.png$~', $relative) || strpos($relative, '..') !== false) {
        $relative = 'assets/pwa/icon-' . $variant . '.png';
    }
    if ($resource === 'icon-' . $variant) {
        header('Content-Type: image/png');
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'HEAD') readfile(__DIR__ . '/' . $relative);
        exit;
    }
    $icons[] = ['src' => $root . 'pwa.php?resource=icon-' . $variant, 'sizes' => $size . 'x' . $size, 'type' => 'image/png', 'purpose' => $variant === 'maskable-512' ? 'maskable' : 'any'];
}
$theme = $config['pwa_theme_color'] ?? '#2563eb';
if (!is_string($theme) || !preg_match('/^#[a-fA-F0-9]{6}$/', $theme)) $theme = '#2563eb';
header('Content-Type: application/manifest+json; charset=utf-8');
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'HEAD') exit;
echo json_encode([
    'id' => $root, 'name' => crm_app_display_name(), 'short_name' => crm_app_display_name(),
    'lang' => 'pt-BR', 'start_url' => $root . 'admin', 'scope' => $root,
    'display' => 'standalone', 'background_color' => '#ffffff', 'theme_color' => $theme,
    'icons' => $icons,
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
