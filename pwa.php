<?php
/** Public installation metadata; independent of the CRM framework and session. */
require_once __DIR__ . '/application/helpers/app_branding_helper.php';
require_once __DIR__ . '/application/helpers/pwa_helper.php';

header('Cache-Control: no-store, max-age=0');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: no-referrer');
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if (!in_array($method, ['GET', 'HEAD'], true)) {
    header('Allow: GET, HEAD');
    http_response_code(405);
    exit;
}
$script = parse_url($_SERVER['SCRIPT_NAME'] ?? '/pwa.php', PHP_URL_PATH);
$root = rtrim(str_replace('\\', '/', dirname($script)), '/') . '/';
if (!preg_match('#^/(?:[a-zA-Z0-9_.%~-]+/)*$#', $root)) {
    http_response_code(400);
    exit;
}
$resource = $_GET['resource'] ?? 'manifest';
$assets = [
    'worker' => ['assets/pwa/worker.js', 'application/javascript; charset=utf-8'],
    'icon-192' => ['assets/pwa/icon-192.png', 'image/png'],
    'icon-512' => ['assets/pwa/icon-512.png', 'image/png'],
    'icon-maskable-512' => ['assets/pwa/icon-maskable-512.png', 'image/png'],
    'apple-touch-icon-180' => ['assets/pwa/apple-touch-icon-180.png', 'image/png'],
    'favicon-16' => ['assets/images/argws/favicon-16.png', 'image/png'],
    'favicon-32' => ['assets/images/argws/favicon-32.png', 'image/png'],
    'favicon-ico' => ['assets/images/argws/favicon.ico', 'image/x-icon'],
];
if (!is_string($resource) || ($resource !== 'manifest' && !isset($assets[$resource]))) {
    http_response_code(404);
    exit;
}
if (isset($assets[$resource])) {
    header('Content-Type: ' . $assets[$resource][1]);
    if ($resource === 'worker') {
        header('Service-Worker-Allowed: ' . $root);
    }
    if ($method !== 'HEAD') {
        readfile(__DIR__ . '/' . $assets[$resource][0]);
    }
    exit;
}
$config = [];
require __DIR__ . '/application/config/branding.php';
$name = trim($config['pwa_installation_name'] ?? '');
if ($name === '') {
    $name = crm_resolve_app_display_name($config, getenv('APP_DISPLAY_NAME'));
}
if ($name === 'CRM') {
    $name = 'ARGWS CRM';
}
// Split by Unicode code point, without requiring mbstring at this public endpoint.
preg_match_all('/./us', $name, $characters);
$shortName = count($characters[0]) <= 32 ? $name : implode('', array_slice($characters[0], 0, 29)) . '...';
$theme = $config['pwa_theme_color'] ?? '#2563eb';
if (!is_string($theme) || !preg_match('/^#[a-fA-F0-9]{6}$/D', $theme)) {
    $theme = '#2563eb';
}
$icons = [];
foreach (['icon-192' => 192, 'icon-512' => 512, 'icon-maskable-512' => 512] as $key => $size) {
    $icons[] = [
        'src' => $root . 'pwa.php?resource=' . $key . '&v=' . native_pwa_revision(),
        'sizes' => $size . 'x' . $size,
        'type' => 'image/png',
        'purpose' => $key === 'icon-maskable-512' ? 'maskable' : 'any',
    ];
}
header('Content-Type: application/manifest+json; charset=utf-8');
if ($method !== 'HEAD') {
    echo json_encode([
        'id' => $root, 'name' => $name, 'short_name' => $shortName, 'lang' => 'pt-BR',
        'start_url' => $root . 'admin', 'scope' => $root, 'display' => 'standalone',
        'background_color' => '#ffffff', 'theme_color' => $theme, 'icons' => $icons,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
}
