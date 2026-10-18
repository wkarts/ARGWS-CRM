<?php
/**
 * Actual setup controller and AdminController gate with real App_modules/native
 * migrations/PDO storage from the lifecycle harness. HTTP/session/loader and the
 * App_Controller dependency-injection boundary are explicit framework doubles.
 */
declare(strict_types=1);
define('ISSUERS_ESSENTIAL_SUPPORT_ONLY', true);
require __DIR__ . '/issuers_essential.php';
require APPPATH . 'services/PaymentGatewayCatalog.php';
define('ENVIRONMENT', 'testing');

final class EssentialHttpStop extends RuntimeException {}
final class EssentialRedirectStop extends RuntimeException {}
function is_staff_logged_in(): bool { return $GLOBALS['essentialAuth']['logged_in']; }
function is_admin(): bool { return $GLOBALS['essentialAuth']['admin']; }
function get_staff_user_id(): int { return 7; }
function admin_url($path = ''): string { return '/admin/' . $path; }
function redirect($path): void
{
    $GLOBALS['essentialRedirects'][] = $path;
    if ($GLOBALS['essentialStopOnRedirect'] ?? false) { throw new EssentialRedirectStop($path); }
}
function show_error($message, $status = 500): void { throw new EssentialHttpStop($message, $status); }
function init_admin_assets(): void { ++$GLOBALS['essentialAssets']; }
function _l($message): string { return $message; }
function init_head(): void {}
function init_tail(): void {}
function html_escape($value): string { return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8'); }
function form_open($action, $attributes = []): string { return '<form method="post" action="' . html_escape($action) . '">'; }
function form_close(): string { return '</form>'; }


class App_Controller
{
    public $config;
    public $input;
    public $session;
    public $output;
    public $load;
    public $db;
    public $staff_model;
    public $app_modules;
    public $app;
    public $uri;
    public $tasks_model;
    public $issuers_model;
    public $app_menu;
    public $authentication_model;
    public $current_db_version = 321;
    public function __construct()
    {
        $ci = &get_instance();
        foreach (get_object_vars($ci) as $name => $value) {
            if (property_exists($this, $name)) { $this->$name = $value; }
        }
    }
}
final class EssentialControllerInput
{
    public function __construct(public string $verb, public array $data = []) {}
    public function method($uppercase) { return $uppercase ? strtoupper($this->verb) : strtolower($this->verb); }
    public function post($key) { return $this->data[$key] ?? null; }
    public function is_ajax_request(): bool { return true; }
}
final class EssentialControllerSession
{
    public array $data = [];
    public function userdata($key) { return $this->data[$key] ?? null; }
    public function set_userdata($key, $value): void { $this->data[$key] = $value; }
    public function unset_userdata($key): void { unset($this->data[$key]); }
    public function has_userdata($key): bool { return array_key_exists($key, $this->data); }
}
final class EssentialControllerOutput
{
    public int $status = 200;
    public function set_status_header($status): self { $this->status = $status; return $this; }
}
final class EssentialControllerStaff
{
    public function __construct(private EssentialTestDb $db) {}
    public function get($id) { return $this->db->where('staffid', $id)->get($this->db->prefix . 'staff')->row(); }
}

require APPPATH . 'controllers/admin/Essential_modules.php';
require APPPATH . 'core/AdminController.php';
require APPPATH . 'controllers/admin/Mods.php';

function essentialRequest(object $fixture, string $method = 'GET', array $post = [], ?EssentialControllerSession $session = null, bool $loggedIn = true, bool $admin = true): object
{
    essentialUse($fixture);
    EssentialIssuerModule::invalidateSchemaCache($fixture->db); // Simulate a new request, including request-local schema metadata.
    $GLOBALS['essentialAuth'] = ['logged_in' => $loggedIn, 'admin' => $admin];
    $GLOBALS['essentialRedirects'] = [];
    $GLOBALS['essentialStopOnRedirect'] = false;
    $GLOBALS['essentialAssets'] = 0;
    $fixture->ci->input = new EssentialControllerInput($method, $post);
    $fixture->ci->session = $session ?? new EssentialControllerSession();
    $fixture->ci->output = new EssentialControllerOutput();
    $fixture->ci->load = new EssentialTestLoader();
    $fixture->ci->staff_model = new EssentialControllerStaff($fixture->db);
    $fixture->ci->app = new class { public function is_db_upgrade_required($version) { return false; } };
    $fixture->ci->uri = new class { public function segment($number) { return null; } };
    $fixture->ci->tasks_model = new class { public function get_statuses() { return []; } };
    $fixture->ci->issuers_model = new class { public function available($staffId) { return []; } };
    $fixture->ci->app_menu = new class { public array $items = []; public function add_sidebar_menu_item($name, $item) { $this->items[$name] = $item; } };
    $fixture->db->queries = [];
    return $fixture->ci;
}
function essentialDispatch(): Essential_modules
{
    $controller = new Essential_modules();
    if ($GLOBALS['essentialRedirects'] === []) { $controller->index(); }
    return $controller;
}
function essentialHttp(callable $operation, int $status, string $label): void
{
    $actual = null;
    try { $operation(); } catch (EssentialHttpStop $exception) { $actual = $exception->getCode(); }
    essentialCheck($actual === $status, $label . ' (actual: ' . var_export($actual, true) . ')');
}
function essentialNoInstallation(object $fixture, string $label): void
{
    essentialCheck($fixture->db->ddl() === [], $label . ': no DDL');
    essentialCheck(!in_array('activate_issuers_module', hooks()->trace, true), $label . ': no activation hook');
}

try {
    essentialCheck(get_parent_class(Essential_modules::class) === App_Controller::class, 'setup does not inherit gated AdminController');
    $fresh = essentialFixture();
    $ci = essentialRequest($fresh);
    essentialDispatch();
    $token = $ci->session->userdata('essential_modules_csrf');
    essentialCheck(is_string($token) && preg_match('/^[a-f0-9]{64}$/D', $token) === 1, 'GET creates a cryptographically shaped session token');
    essentialCheck(count($ci->load->views) === 1 && $ci->load->views[0][0] === 'admin/modules/essential_setup', 'GET renders dedicated setup view');
    essentialCheck($ci->load->views[0][1] === ['token' => $token, 'error' => null], 'GET view receives token and no error');
    essentialCheck($ci->output->status === 200 && $GLOBALS['essentialRedirects'] === [], 'pending setup GET is readable');
    essentialCheck($fresh->db->writes() === [] && essentialRow($fresh) === null, 'GET neither inserts marker nor writes business DB');
    essentialNoInstallation($fresh, 'GET pending setup');
    $session = $ci->session;
    essentialRequest($fresh, 'GET', [], $session); essentialDispatch();
    essentialCheck($session->userdata('essential_modules_csrf') === $token, 'GET preserves existing valid token');
    essentialCheck($fresh->db->writes() === [], 'repeated GET remains read-only');

    foreach ([
        [[], $token, 'missing submitted token'],
        [['essential_modules_csrf' => 'wrong'], $token, 'mismatched token'],
        [['essential_modules_csrf' => []], $token, 'array submitted token'],
        [['essential_modules_csrf' => ['value' => $token]], $token, 'nested submitted token'],
        [['essential_modules_csrf' => $token], null, 'missing session token'],
        [['essential_modules_csrf' => $token], [], 'array session token'],
        [['essential_modules_csrf' => str_repeat('g', 64)], str_repeat('g', 64), 'non-hex matching tokens'],
        [['essential_modules_csrf' => str_repeat('a', 63)], str_repeat('a', 63), 'wrong-length matching tokens'],
        [['essential_modules_csrf' => 7], 7, 'integer tokens'],
    ] as [$post, $stored, $label]) {
        $badSession = new EssentialControllerSession();
        if ($stored !== null) { $badSession->set_userdata('essential_modules_csrf', $stored); }
        essentialRequest($fresh, 'POST', $post, $badSession);
        essentialHttp(static fn() => essentialDispatch(), 403, $label . ' is rejected');
        essentialCheck($fresh->db->writes() === [], $label . ' cannot mutate DB');
        essentialNoInstallation($fresh, $label);
    }
    foreach (['HEAD', 'PUT', 'DELETE', 'PATCH', 'OPTIONS'] as $method) {
        essentialRequest($fresh, $method, ['essential_modules_csrf' => $token], $session);
        essentialHttp(static fn() => essentialDispatch(), 405, $method . ' is rejected');
        essentialCheck($fresh->db->writes() === [], $method . ' does not mutate DB');
    }
    essentialRequest($fresh, 'POST', ['essential_modules_csrf' => $token], $session, true, false);
    essentialHttp(static fn() => essentialDispatch(), 403, 'logged-in non-admin cannot reach setup');
    essentialCheck($fresh->db->writes() === [], 'non-admin cannot mutate DB');
    $fresh->db->where('staffid', 7)->update($fresh->prefix . 'staff', ['active' => 0]);
    essentialRequest($fresh, 'POST', ['essential_modules_csrf' => $token], $session);
    essentialHttp(static fn() => essentialDispatch(), 403, 'inactive administrator is rejected from live staff record');
    essentialCheck($fresh->db->writes() === [], 'inactive administrator cannot mutate DB');
    $fresh->db->where('staffid', 7)->update($fresh->prefix . 'staff', ['active' => 1]);
    essentialRequest($fresh, 'POST', ['essential_modules_csrf' => $token], $session, false, true);
    essentialDispatch();
    essentialCheck($GLOBALS['essentialRedirects'] === ['/admin/authentication'], 'guest is redirected to authentication');
    essentialCheck($fresh->db->writes() === [], 'guest request cannot mutate DB');
    essentialNoInstallation($fresh, 'guest request');
    $deleted = essentialFixture(); $deleted->db->query('DELETE FROM ' . $deleted->prefix . 'staff');
    essentialRequest($deleted, 'GET');
    essentialHttp(static fn() => essentialDispatch(), 403, 'deleted staff session is rejected');
    essentialCheck($deleted->db->writes() === [], 'deleted administrator cannot mutate DB');

    // Exercise the action's defense in depth even if a dispatcher bypassed __construct.
    essentialRequest($fresh, 'POST', ['essential_modules_csrf' => $token], $session, true, false);
    $controller = (new ReflectionClass(Essential_modules::class))->newInstanceWithoutConstructor();
    foreach (['input', 'session', 'output', 'load', 'app_modules', 'staff_model'] as $property) { $controller->$property = $fresh->ci->$property; }
    essentialHttp(static fn() => $controller->index(), 403, 'direct dispatch still rejects non-admin');
    essentialCheck($fresh->db->writes() === [], 'direct unauthorized dispatch cannot mutate DB');

    foreach (['active', 'admin'] as $revoked) {
        essentialRequest($fresh, 'POST', ['essential_modules_csrf' => $token], $session);
        $controller = new Essential_modules();
        $fresh->db->where('staffid', 7)->update($fresh->prefix . 'staff', [$revoked => 0]);
        $fresh->db->queries = [];
        essentialHttp(static fn() => $controller->index(), 403, 'index revalidates live ' . $revoked . ' after constructor');
        essentialCheck($fresh->db->writes() === [], 'revoked ' . $revoked . ' cannot mutate DB');
        essentialNoInstallation($fresh, 'revoked ' . $revoked);
        $fresh->db->where('staffid', 7)->update($fresh->prefix . 'staff', [$revoked => 1]);
    }
    $ci = essentialRequest($fresh, 'POST', ['essential_modules_csrf' => $token], $session);
    $fresh->db->failOn = 'CREATE TABLE IF NOT EXISTS `' . $fresh->prefix . 'issuers_staff_defaults';
    essentialDispatch();
    essentialCheck($ci->output->status === 503, 'native partial DDL failure returns 503');
    essentialCheck($GLOBALS['essentialRedirects'] === [], 'failed setup does not redirect to dashboard');
    essentialCheck((int) essentialRow($fresh)->active === 0 && !$fresh->app_modules->essential_modules_ready(), 'failed setup remains inactive and gated');
    essentialCheck($session->userdata('essential_modules_csrf') === $token, 'failure keeps token for explicit retry');
    essentialCheck(count($ci->load->views) === 1 && str_contains($ci->load->views[0][1]['error'], 'painel permanece bloqueado'), 'failure view explains blocked dashboard and retry');
    essentialCheck(!str_contains($ci->load->views[0][1]['error'], $fresh->prefix), 'failure view does not expose SQL or schema identifiers');
    essentialCheck(essentialLegacy($fresh) === $fresh->legacy, 'failed controller setup preserves legacy rows');
    essentialCheck(count($GLOBALS['essentialLogs']) > 0, 'setup failure is logged');
    $fresh->db->failOn = null;
    $ci = essentialRequest($fresh, 'GET', [], $session); essentialDispatch();
    essentialCheck($fresh->db->writes() === [], 'GET after failure does not retry installation');
    essentialNoInstallation($fresh, 'GET after failure');
    $ci = essentialRequest($fresh, 'POST', ['essential_modules_csrf' => $token], $session); essentialDispatch();
    essentialCheck($GLOBALS['essentialRedirects'] === ['/admin/'], 'valid explicit retry redirects to dashboard');
    essentialCheck($ci->output->status === 200 && $ci->load->views === [], 'successful retry does not render an error');
    essentialCheck($fresh->app_modules->essential_modules_ready(), 'valid POST completes actual native migrations and activation');
    essentialCheck($session->userdata('essential_modules_csrf') === null, 'successful setup consumes session token');
    essentialCheck(essentialLegacy($fresh) === $fresh->legacy, 'successful controller retry preserves legacy rows');
    essentialRequest($fresh, 'POST', ['essential_modules_csrf' => $token], $session);
    essentialHttp(static fn() => essentialDispatch(), 403, 'replaying consumed POST token is rejected');
    essentialCheck($fresh->db->writes() === [], 'consumed token replay performs no writes');
    $ci = essentialRequest($fresh, 'GET', [], $session); essentialDispatch();
    essentialCheck($GLOBALS['essentialRedirects'] === ['/admin/'] && $ci->load->views === [], 'GET ready setup redirects without setup form');
    essentialCheck($fresh->db->writes() === [], 'GET ready setup has no writes');
    essentialNoInstallation($fresh, 'GET ready setup');

    $absent = essentialFixture(null, 0, false);
    $absentSession = new EssentialControllerSession(); $absentSession->set_userdata('essential_modules_csrf', $token);
    $ci = essentialRequest($absent, 'POST', ['essential_modules_csrf' => $token], $absentSession); essentialDispatch();
    essentialCheck($ci->output->status === 503 && $GLOBALS['essentialRedirects'] === [], 'missing module/false ensure returns 503');
    essentialCheck($absent->db->writes() === [], 'missing module cannot mutate DB through controller');
    essentialNoInstallation($absent, 'missing module POST');
    $invalidSession = new EssentialControllerSession(); $invalidSession->set_userdata('essential_modules_csrf', []);
    $ci = essentialRequest($absent, 'GET', [], $invalidSession); essentialDispatch();
    essentialCheck(is_string($invalidSession->userdata('essential_modules_csrf')) && strlen($invalidSession->userdata('essential_modules_csrf')) === 64, 'GET replaces malformed session token safely');
    essentialCheck($absent->db->writes() === [], 'malformed session GET never installs');

    foreach ([true, false] as $admin) {
        $pending = essentialFixture();
        essentialRequest($pending, 'GET', [], null, true, $admin);
        if ($admin) {
            new AdminController();
            essentialCheck($GLOBALS['essentialRedirects'] === ['/admin/essential_modules'], 'real AdminController sends pending administrator to setup');
        } else {
            essentialHttp(static fn() => new AdminController(), 503, 'real AdminController blocks pending ordinary staff with 503');
            essentialCheck($GLOBALS['essentialRedirects'] === [], 'ordinary staff cannot enter administrator setup');
        }
        essentialNoInstallation($pending, 'AdminController pending gate');
        essentialCheck(essentialRow($pending) === null, 'AdminController gate never prewrites a migration marker');
        essentialCheck(!in_array('admin_init', hooks()->trace, true) && $GLOBALS['essentialAssets'] === 0, 'pending gate stops before admin hooks and assets');
        essentialCheck(essentialLegacy($pending) === $pending->legacy, 'pending gate leaves legacy data unchanged');
    }
    essentialRequest($fresh, 'GET'); new AdminController();
    essentialCheck($GLOBALS['essentialRedirects'] === [] && $GLOBALS['essentialAssets'] === 1, 'real AdminController admits fully ready installation');
    essentialCheck(in_array('admin_init', hooks()->trace, true), 'ready dashboard initializes its normal admin hooks');
    essentialNoInstallation($fresh, 'ready AdminController');
    essentialCheck(essentialLegacy($fresh) === $fresh->legacy, 'ready dashboard leaves legacy business rows unchanged');

    // CI redirect terminates dispatch. Use an explicit stop only outside the setup
    // action, whose catch(Throwable) intentionally surrounds migration failures.
    foreach ([['1.0.2', 0], ['1.0.1', 0], ['1.0.1', 1]] as [$version, $active]) {
        foreach (['activate', 'upgrade_database'] as $action) {
          foreach (['issuers', 'ideal'] as $moduleName) {
            foreach (['GET', 'POST'] as $method) {
                $bypass = essentialFixture($version, $active);
                essentialRequest($bypass, $method);
                $GLOBALS['essentialStopOnRedirect'] = true;
                $dispatched = false;
                try {
                    $mods = new Mods();
                    $dispatched = true;
                    $mods->$action($moduleName);
                } catch (EssentialRedirectStop $stop) {
                    essentialCheck($stop->getMessage() === '/admin/essential_modules', 'legacy Mods route redirects to authenticated setup');
                } finally {
                    $GLOBALS['essentialStopOnRedirect'] = false;
                }
                essentialCheck(!$dispatched, 'Mods ' . $moduleName . '/' . $action . '/' . $method . ' cannot bypass setup guard for ' . $version . '/active=' . $active);
                essentialNoInstallation($bypass, 'legacy Mods route ' . $action . '/' . $method);
                $row = essentialRow($bypass);
                essentialCheck($row->installed_version === $version && (int) $row->active === $active, 'legacy route does not mutate module marker or activation');
                essentialCheck(essentialLegacy($bypass) === $bypass->legacy, 'legacy route preserves business rows');
            }
          }
        }
    }
    foreach ([['1.0.2', 0, true], ['1.0.1', 1, true], ['1.0.2', 1, false]] as [$version, $active, $needsSetup]) {
        $catalog = essentialFixture($version, $active);
        $module = $catalog->app_modules->get('issuers');
        foreach ([null, false] as $catalogFlag) {
            $resource = array_merge($module, ['name' => 'Emitentes', 'description' => 'Synthetic catalog entry', 'requires' => []]);
            if ($catalogFlag !== null) { $resource['essential'] = $catalogFlag; }
            $view = new class($catalog->app_modules) {
                public function __construct(public App_modules $app_modules) {}
                public function render(array $resources): string
                {
                    ob_start();
                    try { require APPPATH . 'views/admin/modules/list.php'; return ob_get_contents(); }
                    finally { ob_end_clean(); }
                }
            };
            $catalog->db->queries = [];
            $html = $view->render([$resource]);
            essentialCheck(str_contains($html, 'Essencial — obrigatório'), 'rendered catalog gets essential status from backend without catalog flag');
            essentialCheck(!str_contains($html, '/modules/deactivate/issuers'), 'rendered issuer row never exposes deactivation');
            essentialCheck(!str_contains($html, '/modules/activate/issuers'), 'rendered issuer row never exposes generic activation');
            essentialCheck(!str_contains($html, '/modules/upgrade_database/issuers'), 'rendered issuer row never exposes generic upgrade');
            essentialCheck(str_contains($html, '/admin/essential_modules') === $needsSetup, 'only pending issuer row links to controlled setup');
            essentialCheck($catalog->db->writes() === [], 'rendering resources never installs or mutates module state');
        }
    }

    $combined = essentialFixture(essentialHeaderVersion(), 1);
    essentialSeedSchema($combined);
    $combined->db->insert($combined->prefix . 'modules', ['module_name'=>'ideal','installed_version'=>'1.0.0','active'=>0]);
    $property = (new ReflectionClass(App_modules::class))->getProperty('modules');
    $catalog = $property->getValue($combined->app_modules);
    $catalog['issuers']['headers']['module_name'] = 'Emitentes';
    $catalog['ideal'] = ['system_name'=>'ideal','headers'=>['version'=>'1.0.1','module_name'=>'iDEAL'],'installed_version'=>'1.0.0','activated'=>0];
    $property->setValue($combined->app_modules, $catalog);
    essentialRequest($combined, 'GET');
    $combined->ci->config = new class {
        public function load($name) {}
        public function item($name) { return [
            'issuers'=>['name'=>'Emitentes','description'=>'Essential synthetic','requires'=>[]],
            'ideal'=>['name'=>'iDEAL','description'=>'Optional synthetic','requires'=>[]],
        ]; }
    };
    putenv('CRM_ENABLE_IDEAL=false'); putenv('CRM_HIDDEN_RESOURCES=issuers,ideal');
    $mods = new Mods(); $mods->index();
    $data = $combined->ci->load->views[0][1];
    essentialCheck(array_column($data['resources'],'system_name') === ['issuers'], 'hidden optional iDEAL never hides essential issuer catalog');
    essentialCheck($data['ideal_maintenance']['system_name'] === 'ideal', 'installed hidden iDEAL keeps maintenance prompt');
    essentialCheck(preg_match('/^[a-f0-9]{64}$/D', $data['ideal_migration_csrf']) === 1, 'maintenance prompt has its own session CSRF');
    $view = new class($combined->app_modules) {
        public function __construct(public App_modules $app_modules) {}
        public function render(array $data): string {
            extract($data); ob_start();
            try { require APPPATH . 'views/admin/modules/list.php'; return ob_get_contents(); }
            finally { ob_end_clean(); }
        }
    };
    $html = $view->render($data);
    essentialCheck(str_contains($html, '/modules/upgrade_database/ideal') && str_contains($html, 'ideal_module_migration_csrf'), 'combined view preserves POST maintenance and token');
    essentialCheck(str_contains($html, 'Essencial — obrigatório') && !str_contains($html, '/modules/deactivate/issuers') && !str_contains($html, '/modules/activate/issuers'), 'combined view preserves essential issuer restrictions');
    essentialCheck(!str_contains($html, '/modules/activate/ideal'), 'hidden iDEAL maintenance does not expose activation');
    essentialNoInstallation($combined, 'combined resources GET');
    putenv('CRM_ENABLE_IDEAL'); putenv('CRM_HIDDEN_RESOURCES');

    echo 'Essential issuers setup/controller gate: ' . $GLOBALS['essentialChecks'] . ' checks passed (real controllers, App_modules + native migrations, '
        . $fresh->db->driver() . ($fresh->db->driver() === 'sqlite' ? '; translated DDL, not MySQL proof' : '') . ").\n";
} finally {
    essentialCleanup();
}
