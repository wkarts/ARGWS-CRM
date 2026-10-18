<?php

declare(strict_types=1);

define('BASEPATH', dirname(__DIR__) . '/system/');
define('APPPATH', dirname(__DIR__) . '/application/');
define('APP_MODULES_PATH', dirname(__DIR__) . '/modules/');
require APPPATH . 'hooks/App_Autoloader.php';
App_Autoloader::register();
use app\services\PaymentGatewayCatalog as Catalog;

$checks = 0;
function same($expected, $actual, $context)
{
    global $checks;
    $checks++;
    if ($expected !== $actual) { throw new RuntimeException($context . ': ' . var_export($actual, true)); }
}
function denies(callable $action, string $expected, string $context)
{
    try { $action(); } catch (RuntimeException $error) {
        same($expected, $error->getMessage(), $context);
        return;
    }
    throw new RuntimeException($context . ': access was not denied');
}
putenv('CRM_ENABLE_IDEAL');
$ids = array_merge(Catalog::HIDDEN_NATIVE_IDS, ['Ideal_gateway', 'asaas', 'paypal', 'paypal_checkout', 'stripe_connect', 7]);
$modes = array_map(static fn($id) => ['id' => $id, 'name' => (string) $id], $ids);
$visible = ['asaas', 'paypal', 'paypal_checkout', 'stripe_connect', 7];
same($visible, array_column(Catalog::forSelection($modes), 'id'), 'Exact native IDs hidden, unrelated integrations preserved');
foreach (Catalog::HIDDEN_NATIVE_IDS as $id) {
    same(true, Catalog::isHidden($id), 'Hidden ' . $id);
    same(false, Catalog::allowsCheckout($id, serialize(['asaas'])), 'New invoice blocks ' . $id);
    same(true, Catalog::allowsCheckout($id, serialize([$id])), 'Issued invoice preserves ' . $id);
}
same(['asaas', 7], Catalog::normalizeModes(['stripe', 'two_checkout', 'Ideal_gateway', 'asaas', 7]), 'New invoice filters IDs');
same(['asaas', 'stripe'], Catalog::normalizeModes(['asaas', 'two_checkout'], serialize(['stripe'])), 'Editing preserves only already authorized hidden ID');
same(['stripe'], Catalog::normalizeModes([], ['stripe']), 'Unrelated edit does not erase existing link');
same(['stripe', 'asaas', 'paypal', 'paypal_checkout', 'stripe_connect', 7], array_column(Catalog::forSelection($modes, ['stripe']), 'id'), 'Legacy checkout list');
same([], Catalog::decodeModes('bad input'), 'Malformed serialized modes');
same([], Catalog::decodeModes(serialize((object) ['id' => 'stripe'])), 'Serialized object not accepted');
same([], Catalog::decodeModes([new stdClass(), ['stripe'], null, false]), 'Non-scalar values ignored');

class CatalogHooks
{
    public array $filters = [];
    public function add_filter($hook, $callback) { $this->filters[$hook] = $callback; }
    public function apply_filters($hook, $value) { return isset($this->filters[$hook]) ? ($this->filters[$hook])($value) : $value; }
}
function hooks() { static $hooks; return $hooks ??= new CatalogHooks(); }
function db_prefix() { return 'tbl'; }
class CatalogDb
{
    public array $queries = [];
    public $original = 'a:1:{i:0;s:6:"stripe";}';
    public function select($field) { $this->queries[] = ['select', $field]; return $this; }
    public function where($key, $value) { $this->queries[] = ['where', $key, $value]; return $this; }
    public function get($table) { $this->queries[] = ['get', $table]; return $this; }
    public function row() { return (object) ['allowed_payment_modes' => $this->original]; }
}
$instance = (object) ['db' => new CatalogDb()];
function &get_instance() { global $instance; return $instance; }
require APPPATH . 'helpers/payment_gateways_helper.php';
same(['before_invoice_added', 'before_update_invoice'], array_keys(hooks()->filters), 'Hooks registered centrally');
$new = hooks()->apply_filters('before_invoice_added', ['data' => ['allowed_payment_modes' => serialize(['stripe', 'asaas']), 'clientid' => 12], 'items' => [3]]);
same(['asaas'], unserialize($new['data']['allowed_payment_modes']), 'New invoice hook filters server-side');
same(12, $new['data']['clientid'], 'New invoice other data preserved');
same([3], $new['items'], 'New invoice items preserved');
$edited = hooks()->apply_filters('before_update_invoice', ['id' => 42, 'data' => ['allowed_payment_modes' => serialize(['two_checkout', 'asaas'])], 'items' => [3]]);
same(['asaas', 'stripe'], unserialize($edited['data']['allowed_payment_modes']), 'Edit hook reads original hidden IDs');
same([['select', 'allowed_payment_modes'], ['where', 'id', 42], ['get', 'tblinvoices']], $instance->db->queries, 'Preservation reads only target invoice');

$settings = ['paymentmethod_stripe_active' => '0', 'paymentmethod_stripe_api_secret_key' => 'new-value', 'paymentmethod_stripe_connect_active' => '1', 'companyname' => 'Cliente'];
$gateways = [['id' => 'stripe', 'instance' => new class {
    public function getSettings() { return [['name' => 'paymentmethod_stripe_active'], ['name' => 'paymentmethod_stripe_api_secret_key']]; }
}]];
same(['paymentmethod_stripe_connect_active' => '1', 'companyname' => 'Cliente'], Catalog::filterSettings($settings, $gateways), 'Only exact registered options filtered');

// Exercise the real checkout boundary with in-memory collaborators, before network/payment effects.
class App_Model { public $db; public $invoices_model; public $load; public $session; }
require APPPATH . 'models/Payments_model.php';
function is_staff_logged_in() { return $GLOBALS['catalogStaff'] ?? false; }
function staff_can($capability, $resource) { return $GLOBALS['catalogCanEdit'] ?? false; }
$payments = (new ReflectionClass(Payments_model::class))->newInstanceWithoutConstructor();
$payments->db = new class {
    public function field_exists($field, $table) { return false; }
    public function query($sql, $bind = []) {
        return new class { public function row() { return (object) ['id' => 5]; } };
    }
};
$payments->invoices_model = new class {
    public function get($id) { return (object) ['id' => 5, 'issuer_id' => null, 'allowed_payment_modes' => serialize(['asaas'])]; }
};
same(false, $payments->process_payment(['paymentmode' => 'stripe'], 5), 'Direct POST cannot start hidden checkout for new invoice');

// Environment flag controls only the separate module, never Stripe iDEAL native.
foreach (['false', '0', '', 'invalid'] as $value) {
    putenv('CRM_ENABLE_IDEAL=' . $value);
    same(false, Catalog::idealEnabled(), 'Flag disabled ' . $value);
    same(true, Catalog::isHidden('Ideal_gateway'), 'Module gateway hidden ' . $value);
}
putenv('CRM_ENABLE_IDEAL=true');
same(true, Catalog::idealEnabled(), 'Flag true');
same(false, Catalog::isHidden('Ideal_gateway'), 'Separate module made available');
same(true, Catalog::isHidden('stripe_ideal'), 'Native iDEAL remains hidden');
same(['Ideal_gateway'], Catalog::normalizeModes(['Ideal_gateway']), 'Enabled module can be selected');

class AdminController {}
class App_Controller { public $input; public $session; }
function show_404() { throw new RuntimeException('404'); }
function access_denied($permission) { throw new RuntimeException('denied:' . $permission); }
function show_error($message, $status) { throw new RuntimeException('http:' . $status); }
require APPPATH . 'controllers/admin/Mods.php';
require APPPATH . 'libraries/App_modules.php';
require APP_MODULES_PATH . 'ideal/controllers/Ideal.php';
$moduleController = (new ReflectionClass(Mods::class))->newInstanceWithoutConstructor();
$definition = new ReflectionMethod(Mods::class, 'get_resource_definition');
$moduleManager = (new ReflectionClass(App_modules::class))->newInstanceWithoutConstructor();
$idealController = new Ideal();
$guard = new ReflectionMethod(Ideal::class, 'require_gateway_management');
putenv('CRM_ENABLE_IDEAL=false');
same(false, $definition->invoke($moduleController, 'ideal'), 'Direct hidden resource management blocked');
same(false, $moduleManager->activate('ideal'), 'Programmatic activation blocked without DB writes');
$registeredModules = new ReflectionProperty(App_modules::class, 'modules');
$registeredModules->setValue($moduleManager, ['ideal' => ['activated' => 1]]);
same(false, $moduleManager->activate('ideal'), 'Hidden active module still cannot bypass activation policy');
denies(fn() => $idealController->create_webhook(), '404', 'Hidden module cannot create/delete webhook');
putenv('CRM_ENABLE_IDEAL=true');
same(true, $moduleManager->activate('ideal'), 'Enabled module preserves native already-active path through essential wrapper');
same(false, $moduleManager->deactivate('issuers'), 'Essential module remains protected alongside ideal policy');
denies(fn() => $guard->invoke($idealController), 'denied:settings', 'Flag does not bypass RBAC');
$GLOBALS['catalogStaff'] = true;
$GLOBALS['catalogCanEdit'] = false;
denies(fn() => $guard->invoke($idealController), 'denied:settings', 'Staff without edit denied');
$GLOBALS['catalogCanEdit'] = true;
$idealController->input = new class {
    public string $httpMethod = 'GET';
    public $token = null;
    public function method($upper) { return $this->httpMethod; }
    public function post($key) { return $this->token; }
};
$idealController->session = new class {
    public function userdata($key) { return str_repeat('a', 64); }
};
denies(fn() => $guard->invoke($idealController), '404', 'GET cannot mutate webhook');
$idealController->input->httpMethod = 'POST';
denies(fn() => $guard->invoke($idealController), 'http:403', 'POST without own token denied');
$idealController->input->token = 'incorrect-token';
denies(fn() => $guard->invoke($idealController), 'http:403', 'POST with wrong token denied');
$idealController->input->token = ['malformed'];
denies(fn() => $guard->invoke($idealController), 'http:403', 'Array token denied');
$idealController->input->token = str_repeat('a', 64);
same(null, $guard->invoke($idealController), 'Authorized POST with session token allowed');

// A local reversible override does not grant access to the separate ideal module.
define('CRM_HIDDEN_NATIVE_PAYMENT_GATEWAYS', []);
putenv('CRM_ENABLE_IDEAL=false');
same(false, Catalog::isHidden('stripe'), 'Local override restores native catalog without data mutation');
same(true, Catalog::isHidden('Ideal_gateway'), 'Separate module still requires environment flag');
putenv('CRM_ENABLE_IDEAL');
echo "Payment gateway catalog: {$checks} checks passed (in-memory, no provider calls).\n";
