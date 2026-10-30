<?php

declare(strict_types=1);
define('BASEPATH', dirname(__DIR__) . '/system/');
define('APPPATH', dirname(__DIR__) . '/application/');
require APPPATH . 'services/PaymentGatewayCatalog.php';
require BASEPATH . 'helpers/form_helper.php';

class IdealFormHooks { public function add_action($name, $callback) {} }
function hooks() { static $hooks; return $hooks ??= new IdealFormHooks(); }
function register_payment_gateway($name, $module) {}
function register_activation_hook($name, $callback) {}
function is_staff_logged_in() { return $GLOBALS['canManage'] ?? false; }
function staff_can($action, $resource) { return $GLOBALS['canManage'] ?? false; }
function config_item($name) { return 'UTF-8'; }
function html_escape($value) { return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8'); }
function e($value) { return html_escape($value); }
function site_url($path = '') { return 'https://crm.example.test/' . $path; }
$ci = (object) [
    'config' => new class {
        public bool $csrf = false;
        public function item($key) { return $this->csrf; }
        public function base_url() { return site_url(); }
    },
    'security' => new class {
        public function get_random_bytes($count) { return chr(0); }
        public function get_csrf_token_name() { return 'global_csrf'; }
        public function get_csrf_hash() { return 'framework-token'; }
    },
    'session' => new class {
        public array $data = [];
        public function userdata($key) { return $this->data[$key] ?? null; }
        public function set_userdata($key, $value) { $this->data[$key] = $value; }
    },
];
function &get_instance() { global $ci; return $ci; }
require dirname(__DIR__) . '/modules/ideal/ideal.php';
$checks = 0;
function same($expected, $actual, $context)
{
    global $checks;
    $checks++;
    if ($expected !== $actual) { throw new RuntimeException($context . ': ' . var_export($actual, true)); }
}
function renderedForms(): string { ob_start(); idealModuleManagementForms(); return (string) ob_get_clean(); }
putenv('CRM_ENABLE_IDEAL=false');
$GLOBALS['canManage'] = true;
same('', idealModuleManagementButton('create_webhook', 'Criar'), 'Disabled feature has no management button');
putenv('CRM_ENABLE_IDEAL=true');
$GLOBALS['canManage'] = false;
same('', idealModuleManagementButton('create_webhook', 'Criar'), 'No permission has no management button');
$GLOBALS['canManage'] = true;
same('', idealModuleManagementButton('other_action', 'Invalid'), 'Unknown action rejected');
$create = idealModuleManagementButton('create_webhook', 'Criar <webhook>');
$recreate = idealModuleManagementButton('create_webhook', 'Recriar', true);
$enable = idealModuleManagementButton('enable_webhook', 'Habilitar');
same(false, str_contains($create . $recreate . $enable, '<form'), 'Buttons never nest forms inside Settings');
same(true, str_contains($create, 'form="ideal-management-create_webhook"'), 'External form association');
same(true, str_contains($create, 'Criar &lt;webhook&gt;'), 'Label escaped');
$html = renderedForms();
same(3, substr_count($html, '<form '), 'Three independent management forms');
same(3, substr_count($html, 'method="post"'), 'All management actions use POST');
same(3, substr_count($html, 'name="ideal_management_csrf"'), 'Own CSRF token included with global CSRF off');
same(1, substr_count($html, 'name="recreate" value="1"'), 'Recreate intent in POST body only');
same(false, str_contains($html, '?recreate='), 'No state-changing query link');
same(64, strlen($ci->session->userdata('ideal_management_csrf')), 'Session token generated securely');
$originalToken = $ci->session->userdata('ideal_management_csrf');
$ci->config->csrf = true;
$html = renderedForms();
same(3, substr_count($html, 'name="global_csrf"'), 'Global CSRF still included by real CI helper');
same(3, substr_count($html, 'name="ideal_management_csrf"'), 'Own CSRF retained with global CSRF on');
same($originalToken, $ci->session->userdata('ideal_management_csrf'), 'Token remains valid across repeated renders');
putenv('CRM_ENABLE_IDEAL=false');
same('', renderedForms(), 'Flag revocation hides management forms');
putenv('CRM_ENABLE_IDEAL');
echo "iDEAL management forms: {$checks} checks passed (real CI form helper, no network).\n";
