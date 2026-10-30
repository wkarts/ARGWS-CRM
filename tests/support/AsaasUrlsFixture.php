<?php
/** Presentation-only fixture: the shipped URL view with synthetic, non-secret data. */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
define('BASEPATH', __DIR__);
function html_escape($value) { return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8'); }
$asaas_summary = [
    'api_urls' => ['production' => 'https://api.asaas.com/v3', 'sandbox' => 'https://api-sandbox.asaas.com/v3'],
    'state' => 'invalid_base',
    'integrations' => [],
];
require dirname(__DIR__, 2) . '/modules/asaas/views/settings_summary.php';
