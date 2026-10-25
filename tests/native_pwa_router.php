<?php
// Disposable HTTP fixture, never a CRM controller or database connection.
$root = getenv('PWA_TEST_ROOT');
if (!$root || !is_dir($root)) { http_response_code(500); exit; }
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$prefix = str_starts_with($path, '/crm/') ? '/crm/' : '/';
$route = substr($path, strlen($prefix));
header('Cache-Control: no-store');
if ($route === 'pwa.php') {
    $_SERVER['SCRIPT_NAME'] = $prefix . 'pwa.php';
    require $root . '/pwa.php'; exit;
}
if ($route === 'assets/pwa/install.js') {
    header('Content-Type: application/javascript'); readfile($root . '/assets/pwa/install.js'); exit;
}
if ($route === 'api/private') {
    header('Content-Type: application/json');
    echo json_encode(['synthetic_private_marker'=>'PRIVATE_FIXTURE_NOT_FOR_OFFLINE']); exit;
}
if ($route === 'api/mutate') {
    $counter = $root . '/mutation-count';
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        file_put_contents($counter, (string) ((int) @file_get_contents($counter) + 1));
    }
    echo (string) (int) @file_get_contents($counter); exit;
}
if ($route === 'outside') { header('Content-Type: text/html'); echo '<!doctype html><meta name="viewport" content="width=device-width,initial-scale=1"><p>Outside PWA scope</p>'; exit; }
if ($route !== 'admin') { http_response_code(404); exit; }
define('BASEPATH', $root);
function e($value) { return htmlspecialchars($value, ENT_QUOTES, 'UTF-8'); }
function base_url($value = '') { global $prefix; return $prefix . $value; }
header('Content-Type: text/html; charset=utf-8');
echo '<!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">';
require $root . '/application/views/includes/pwa.php';
echo '</head><body><h1>Disposable PWA fixture</h1><p>PRIVATE_FIXTURE_NOT_FOR_OFFLINE</p><form method="post" action="'.$prefix.'api/mutate"><label for="synthetic">Synthetic unsaved fixture field</label><input id="synthetic" name="synthetic" value="unsaved"><button>Submit</button></form></body></html>';
