<?php
/**
 * Module maintenance integration contract: real App_modules, Mods and native
 * App_module_migration over disposable SQLite. Only CI HTTP/query-builder and
 * MySQL advisory-lock I/O are adapted. No application configuration or data is loaded.
 */
declare(strict_types=1);
define('BASEPATH', dirname(__DIR__) . '/system/');
define('APPPATH', dirname(__DIR__) . '/application/');
$fixtureRoot = sys_get_temp_dir() . '/crm-module-maintenance-' . bin2hex(random_bytes(6));
mkdir($fixtureRoot, 0700);
define('APP_MODULES_PATH', $fixtureRoot . '/');
$checks = 0;
function maintenanceCheck($condition, string $message): void
{
    ++$GLOBALS['checks'];
    if (!$condition) { throw new RuntimeException($message); }
}
final class MaintenanceHttpStop extends RuntimeException {}
function db_prefix() { return 'fixture_'; }
function &get_instance() { return $GLOBALS['maintenanceCi']; }
function is_admin() { return $GLOBALS['maintenanceAdmin']; }
function access_denied($resource) { throw new MaintenanceHttpStop('denied:' . $resource, 403); }
function show_404() { throw new MaintenanceHttpStop('not_found', 404); }
function show_error($message, $status = 500) { throw new MaintenanceHttpStop($message, $status); }
function admin_url($path = '') { return 'https://crm.example.test/admin/' . $path; }
function redirect($url) { $GLOBALS['maintenanceRedirects'][] = $url; }
function set_alert($level, $message) { $GLOBALS['maintenanceAlerts'][] = [$level, $message]; }
function log_message($level, $message) { $GLOBALS['maintenanceLogs'][] = [$level, $message]; }
function init_head() {}
function init_tail() {}
function html_escape($value) { return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8'); }
function form_open($url) { return '<form method="post" action="' . html_escape($url) . '">'; }
function form_close() { return '</form>'; }
function read_file($path) { return file_get_contents($path); }
function directory_map($path, $depth = 1) { return array_values(array_filter(scandir($path), fn($name) => $name !== '.' && $name !== '..')); }
function hooks() { return new class { public function do_action($name, ...$arguments) { $GLOBALS['maintenanceHooks'][] = $name; } }; }
final class MaintenanceResult
{
    public function __construct(private array $rows) {}
    public function row() { return $this->rows[0] ?? null; }
    public function result() { return $this->rows; }
    public function row_array() { return isset($this->rows[0]) ? (array) $this->rows[0] : []; }
    public function result_array() { return array_map(fn($row) => (array) $row, $this->rows); }
    public function num_rows() { return count($this->rows); }
}
final class MaintenanceDb
{
    public PDO $pdo;
    public string $database = 'synthetic_module_maintenance';
    public bool $db_debug = false;
    public bool $failTracking = false;
    public bool $ignoreTracking = false;
    public bool $allowLock = true;
    public array $locks = [];
    public array $writes = [];
    private array $where = [];
    public function __construct()
    {
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->exec('CREATE TABLE fixture_modules(module_name TEXT PRIMARY KEY, installed_version TEXT, active INTEGER NOT NULL)');
        $this->pdo->exec('CREATE TABLE fixture_payload(module_name TEXT PRIMARY KEY, value TEXT NOT NULL)');
    }
    public function seed(string $name, string $version, int $active): void
    {
        $this->pdo->prepare('INSERT INTO fixture_modules VALUES (?, ?, ?)')->execute([$name, $version, $active]);
    }
    public function select($columns) { return $this; }
    public function where($key, $value) { if ($key !== 'module_name') { throw new RuntimeException('unexpected_scope'); } $this->where[$key] = $value; return $this; }
    public function get($table)
    {
        if ($table !== 'fixture_modules') { throw new RuntimeException('unexpected_table:' . $table); }
        $where = $this->where; $this->where = [];
        $query = $this->pdo->prepare('SELECT * FROM fixture_modules' . ($where ? ' WHERE module_name = ?' : '') . ' ORDER BY module_name');
        $query->execute($where ? [$where['module_name']] : []);
        return new MaintenanceResult($query->fetchAll(PDO::FETCH_OBJ));
    }
    public function update($table, $data)
    {
        $where = $this->where; $this->where = [];
        if ($table !== 'fixture_modules' || !isset($where['module_name']) || array_keys($data) !== ['installed_version']) {
            throw new RuntimeException('unexpected_mutation');
        }
        $this->writes[] = [$where['module_name'], $data];
        if ($this->failTracking) { return false; }
        if ($this->ignoreTracking) { return true; }
        return $this->pdo->prepare('UPDATE fixture_modules SET installed_version = ? WHERE module_name = ?')->execute([$data['installed_version'], $where['module_name']]);
    }
    public function query($sql, $bindings = [])
    {
        if (preg_match('/\b(GET_LOCK|RELEASE_LOCK)\s*\(/i', $sql, $match)) {
            $this->locks[] = [strtoupper($match[1]), $sql, $bindings];
            preg_match('/\bAS\s+[`"]?([a-z_]+)/i', $sql, $alias);
            return new MaintenanceResult([(object) [($alias[1] ?? 'locked') => $match[1] === 'GET_LOCK' ? (int) $this->allowLock : 1]]);
        }
        throw new RuntimeException('unexpected_sql:' . $sql);
    }
    public function escape($value) { return $this->pdo->quote((string) $value); }
    public function row(string $name) { return $this->where('module_name', $name)->get('fixture_modules')->row(); }
    public function snapshot(): array { return $this->pdo->query('SELECT * FROM fixture_modules ORDER BY module_name')->fetchAll(PDO::FETCH_ASSOC); }
}
final class MaintenanceLoader
{
    public array $views = [];
    public function helper($name) {}
    public function dbforge() {}
    public function view($name, $data) { $this->views[] = [$name, $data]; }
}
final class MaintenanceSession
{
    public array $data = [];
    public function userdata($key) { return $this->data[$key] ?? null; }
    public function set_userdata($key, $value) { $this->data[$key] = $value; }
}
final class MaintenanceInput
{
    public string $verb = 'POST';
    public array $data = [];
    public function method($uppercase = false) { return $uppercase ? strtoupper($this->verb) : strtolower($this->verb); }
    public function post($key) { return $this->data[$key] ?? null; }
}
class AdminController
{
    public $db, $load, $session, $input, $app_modules, $config;
    public function __construct() { foreach (get_object_vars(get_instance()) as $key => $value) { if (property_exists($this, $key)) { $this->$key = $value; } } }
}
require APPPATH . 'services/PaymentGatewayCatalog.php';
require APPPATH . 'libraries/App_modules.php';
require APPPATH . 'libraries/App_module_migration.php';
require APPPATH . 'controllers/admin/Mods.php';
function maintenanceFixture(): object
{
    $db = new MaintenanceDb();
    foreach ([
        ['visible', '1.0.0', 1], ['hidden', '1.0.0', 0], ['ideal', '1.0.0', 0],
        ['outside_catalog', '1.0.0', 0], ['missing', '1.0.0', 0], ['downgrade', '1.0.2', 1],
        ['invalid_code', '1.0.0', 0], ['invalid_database', '1.00.0', 0], ['aligned', '1.0.1', 0],
        ['no_migrations', '1.0.0', 0], ['bad_runner', '1.0.0', 0], ['initial_gap', '1.0.0', 0], ['duplicate_step', '1.0.0', 0], ['issuers', '9.0.0', 1],
    ] as [$name, $version, $active]) { $db->seed($name, $version, $active); }
    $GLOBALS['maintenanceAdmin'] = true;
    $GLOBALS['maintenanceAlerts'] = $GLOBALS['maintenanceRedirects'] = $GLOBALS['maintenanceLogs'] = $GLOBALS['maintenanceHooks'] = $GLOBALS['maintenanceUpCalls'] = [];
    $GLOBALS['maintenanceFailureMode'] = null;
    $ci = (object) [
        'db' => $db, 'load' => new MaintenanceLoader(), 'session' => new MaintenanceSession(), 'input' => new MaintenanceInput(),
        'app' => new class { public function get_current_db_version() { return 450; } },
        'lang' => new class { public function load($name) {} public function line($name) { return $name . ' %s'; } },
        'config' => new class { public function load($name) {} public function item($key) { return ['visible' => ['name' => 'Visible', 'description' => 'Fixture', 'requires' => []], 'hidden' => ['name' => 'Hidden', 'description' => 'Fixture', 'requires' => []]]; } },
        'app_object_cache' => new class { private array $items = []; public function get($key) { return $this->items[$key] ?? false; } public function add($key, $value) { $this->items[$key] = $value; } },
    ];
    $GLOBALS['maintenanceCi'] = $ci;
    $ci->app_modules = new App_modules();
    $controller = new Mods();
    $ci->session->data['module_migration_csrf'] = str_repeat('a', 64);
    $ci->input->data['module_migration_csrf'] = str_repeat('a', 64);
    return (object) ['ci' => $ci, 'db' => $db, 'modules' => $ci->app_modules, 'controller' => $controller];
}
function maintenanceBlocked(callable $operation, string $label, ?int $expected = null): void
{
    $status = null;
    try { $operation(); } catch (MaintenanceHttpStop $error) { $status = $error->getCode(); }
    maintenanceCheck($status !== null && $status >= 400 && ($expected === null || $status === $expected), $label . ': expected blocked HTTP, got ' . var_export($status, true));
}
function maintenanceRejected(callable $operation, string $label): void
{
    $status = null;
    try { $operation(); } catch (MaintenanceHttpStop $error) { $status = $error->getCode(); }
    $danger = count(array_filter($GLOBALS['maintenanceAlerts'], fn($alert) => $alert[0] === 'danger'));
    maintenanceCheck(($status !== null && $status >= 400) || $danger === 1, $label . ': failure is reported');
    maintenanceCheck(count(array_filter($GLOBALS['maintenanceAlerts'], fn($alert) => $alert[0] === 'success')) === 0, $label . ': no false success');
}
function maintenanceNoMutation(object $fixture, array $before, string $label): void
{
    maintenanceCheck($fixture->db->snapshot() === $before, $label . ': stored rows preserved');
    maintenanceCheck($fixture->db->writes === [] && $GLOBALS['maintenanceUpCalls'] === [] && $GLOBALS['maintenanceHooks'] === [], $label . ': no runner, marker writes or activation hooks');
}
function maintenanceRunMigration(string $name): void
{
    $GLOBALS['maintenanceUpCalls'][] = $name;
    if ($GLOBALS['maintenanceFailureMode'] === 'throw') { throw new RuntimeException('synthetic_runner_failure'); }
    $ci = get_instance();
    $ci->db->pdo->prepare('INSERT OR IGNORE INTO fixture_payload VALUES (?, ?)')->execute([$name, 'synthetic']);
    if ($GLOBALS['maintenanceFailureMode'] === 'change_activation') { $ci->db->pdo->prepare('UPDATE fixture_modules SET active = 1 WHERE module_name = ?')->execute([$name]); }
}
function maintenanceCleanup(string $path): void
{
    foreach (scandir($path) as $name) { if ($name === '.' || $name === '..') { continue; } $file = $path . '/' . $name; is_dir($file) ? maintenanceCleanup($file) : unlink($file); }
    rmdir($path);
}
$previousHidden = getenv('CRM_HIDDEN_RESOURCES');
$previousIdeal = getenv('CRM_ENABLE_IDEAL');
try {
    foreach (['visible', 'hidden', 'ideal', 'outside_catalog', 'downgrade', 'invalid_code', 'invalid_database', 'aligned', 'uninstalled', 'no_migrations', 'bad_runner', 'initial_gap', 'duplicate_step', 'issuers'] as $name) {
        mkdir(APP_MODULES_PATH . $name . '/migrations', 0700, true);
        $version = $name === 'invalid_code' ? '1.0.10' : ($name === 'initial_gap' ? '1.0.3' : '1.0.1');
        file_put_contents(APP_MODULES_PATH . $name . '/' . $name . '.php', "<?php\n/*\nModule Name: Synthetic $name\nVersion: $version\n*/\nthrow new RuntimeException('module_init_must_not_execute');\n");
        if ($name !== 'no_migrations' && $name !== 'initial_gap') {
            file_put_contents(APP_MODULES_PATH . $name . '/migrations/101_maintenance_' . $name . '.php', "<?php\nclass Migration_Maintenance_$name extends App_module_migration { public function up() { maintenanceRunMigration('$name'); } public function down() { throw new RuntimeException('downgrade_must_not_execute'); } }\n");
        }
    }
    file_put_contents(APP_MODULES_PATH . 'bad_runner/migrations/101_maintenance_bad_runner.php', '<?php // Synthetic migration missing its native class.');
    file_put_contents(APP_MODULES_PATH . 'initial_gap/migrations/102_gap_fixture.php', '<?php // Synthetic absent initial 101 step');
    file_put_contents(APP_MODULES_PATH . 'initial_gap/migrations/103_gap_fixture.php', '<?php // Synthetic target');
    file_put_contents(APP_MODULES_PATH . 'duplicate_step/migrations/101_duplicate_fixture.php', '<?php // Synthetic duplicate 101 identifier');
    putenv('CRM_HIDDEN_RESOURCES=hidden,outside_catalog');
    putenv('CRM_ENABLE_IDEAL=false');
    $fixture = maintenanceFixture();
    $before = $fixture->db->snapshot();
    $fixture->controller->index();
    $visibleNames = array_column($fixture->ci->load->views[0][1]['resources'], 'system_name');
    foreach (['hidden', 'outside_catalog', 'ideal', 'uninstalled', 'visible'] as $visibleName) {
        maintenanceCheck(in_array($visibleName, $visibleNames, true), 'all available modules visible: ' . $visibleName);
    }
    maintenanceNoMutation($fixture, $before, 'resource visibility never activates or migrates');
    $fixture->ci->load->views = [];
    $inventory = $fixture->modules->database_maintenance_inventory();
    $expected = ['visible' => 'upgrade', 'hidden' => 'upgrade', 'ideal' => 'upgrade', 'outside_catalog' => 'upgrade', 'no_migrations' => 'missing_migration', 'bad_runner' => 'upgrade', 'initial_gap' => 'missing_migration', 'duplicate_step' => 'missing_migration', 'missing' => 'missing_files', 'downgrade' => 'downgrade', 'invalid_code' => 'invalid_version', 'invalid_database' => 'invalid_version'];
    maintenanceCheck(count($inventory) === count($expected), 'inventory contains all and only pending installed modules');
    foreach ($expected as $name => $status) {
        maintenanceCheck(isset($inventory[$name]), $name . ': inventory keyed by system name');
        maintenanceCheck($inventory[$name]['system_name'] === $name && $inventory[$name]['status'] === $status, $name . ': accurate maintenance status');
        maintenanceCheck($inventory[$name]['can_upgrade'] === ($status === 'upgrade'), $name . ': automatic maintenance eligibility');
        maintenanceCheck((int) $inventory[$name]['activated'] === (int) $fixture->db->row($name)->active, $name . ': activation state preserved in inventory');
    }
    maintenanceCheck($inventory['hidden']['installed_version'] === '1.0.0' && $inventory['hidden']['code_version'] === '1.0.1', 'inventory exposes installed and filesystem versions separately');
    maintenanceCheck(!isset($inventory['issuers']) && !isset($inventory['uninstalled']) && !isset($inventory['aligned']), 'core issuer history, uninstalled and aligned modules excluded');
    maintenanceCheck($fixture->modules->number_of_modules_that_require_database_upgrade() === count($inventory), 'badge count derives from same inventory as maintenance');
    maintenanceNoMutation($fixture, $before, 'inventory and badge reads');
    $fixture->ci->session->data = [];
    $fixture->controller->maintenance();
    $token = $fixture->ci->session->userdata('module_migration_csrf');
    maintenanceCheck(is_string($token) && preg_match('/^[a-f0-9]{64}$/D', $token) === 1, 'maintenance GET creates a valid session token');
    maintenanceCheck(count($fixture->ci->load->views) === 1 && $fixture->ci->load->views[0][0] === 'admin/modules/maintenance', 'maintenance renders dedicated view');
    maintenanceCheck($fixture->ci->load->views[0][1]['pending'] === $inventory, 'maintenance receives same inventory used by badge');
    $pending = $inventory; $module_migration_csrf = $token;
    ob_start(); require APPPATH . 'views/admin/modules/maintenance.php'; $html = ob_get_clean();
    maintenanceCheck(substr_count($html, '<form ') === 5 && substr_count($html, 'name="module_migration_csrf"') === 5, 'only eligible upgrades render POST forms with CSRF');
    maintenanceCheck(str_contains($html, '/upgrade_database/hidden') && str_contains($html, '/upgrade_database/ideal') && !str_contains($html, '/upgrade_database/downgrade') && !str_contains($html, '/upgrade_database/missing'), 'hidden resources remain actionable while unsafe states have no form');
    $pending = [['system_name' => '<script>fixture</script>', 'installed_version' => '<bad>', 'code_version' => '"invalid"', 'activated' => 0, 'status' => 'invalid_version', 'can_upgrade' => false]];
    ob_start(); require APPPATH . 'views/admin/modules/maintenance.php'; $escaped = ob_get_clean();
    maintenanceCheck(!str_contains($escaped, '<script>fixture</script>') && str_contains($escaped, '&lt;script&gt;fixture&lt;/script&gt;') && str_contains($escaped, '&lt;bad&gt;'), 'maintenance escapes stored module names and versions');
    $fixture->controller->maintenance();
    maintenanceCheck($fixture->ci->session->userdata('module_migration_csrf') === $token, 'maintenance GET preserves a valid session token');
    maintenanceNoMutation($fixture, $before, 'maintenance GET');

    foreach (['visible', 'hidden', 'ideal', 'outside_catalog'] as $name) {
        $fixture = maintenanceFixture();
        $active = $fixture->db->row($name)->active;
        $fixture->controller->upgrade_database($name);
        maintenanceCheck($fixture->db->row($name)->installed_version === '1.0.1', $name . ': native upgrade persists final marker');
        maintenanceCheck($GLOBALS['maintenanceUpCalls'] === [$name], $name . ': actual native runner executes exactly one step');
        maintenanceCheck($fixture->db->row($name)->active === $active && $GLOBALS['maintenanceHooks'] === [], $name . ': maintenance does not activate/deactivate or load module init');
        maintenanceCheck(count(array_filter($GLOBALS['maintenanceAlerts'], fn($alert) => $alert[0] === 'success')) === 1, $name . ': success only after confirmed storage');
        maintenanceCheck(array_column($fixture->db->locks, 0) === ['GET_LOCK', 'RELEASE_LOCK'], $name . ': advisory lock is acquired and released');
        maintenanceCheck($fixture->db->locks[0][2] === $fixture->db->locks[1][2] && strlen($fixture->db->locks[0][2][0]) <= 64, $name . ': same bounded advisory lock key is released');
        maintenanceCheck(getenv('CRM_ENABLE_IDEAL') === 'false' && getenv('CRM_HIDDEN_RESOURCES') === 'hidden,outside_catalog', $name . ': availability settings remain unchanged');
    }
    foreach (['GET', 'HEAD', 'PUT', 'DELETE', 'PATCH'] as $method) {
        $fixture = maintenanceFixture(); $before = $fixture->db->snapshot(); $fixture->ci->input->verb = $method;
        maintenanceBlocked(fn() => $fixture->controller->upgrade_database('hidden'), $method);
        maintenanceNoMutation($fixture, $before, $method);
    }
    $fixture = maintenanceFixture(); $before = $fixture->db->snapshot(); $GLOBALS['maintenanceAdmin'] = false;
    maintenanceBlocked(fn() => $fixture->controller->upgrade_database('hidden'), 'non-admin upgrade', 403);
    maintenanceBlocked(fn() => $fixture->controller->maintenance(), 'non-admin maintenance', 403);
    maintenanceNoMutation($fixture, $before, 'non-admin');
    foreach ([
        [null, str_repeat('a', 64)], ['wrong', str_repeat('a', 64)], [[], str_repeat('a', 64)],
        [str_repeat('a', 64), null], [str_repeat('a', 64), []], [str_repeat('g', 64), str_repeat('g', 64)],
        [str_repeat('a', 63), str_repeat('a', 63)], [7, 7],
    ] as [$submitted, $stored]) {
        $fixture = maintenanceFixture(); $before = $fixture->db->snapshot();
        $fixture->ci->input->data['module_migration_csrf'] = $submitted;
        $fixture->ci->session->data['module_migration_csrf'] = $stored;
        maintenanceBlocked(fn() => $fixture->controller->upgrade_database('hidden'), 'invalid CSRF', 403);
        maintenanceNoMutation($fixture, $before, 'invalid CSRF');
        maintenanceCheck($fixture->db->locks === [], 'invalid CSRF is rejected before acquiring a database lock');
    }
    foreach (['missing', 'downgrade', 'invalid_code', 'invalid_database', 'uninstalled', 'issuers', 'unknown', '../hidden', 'no_migrations', 'initial_gap', 'duplicate_step'] as $name) {
        $fixture = maintenanceFixture(); $before = $fixture->db->snapshot();
        maintenanceRejected(fn() => $fixture->controller->upgrade_database($name), 'ineligible module ' . json_encode($name));
        maintenanceNoMutation($fixture, $before, 'ineligible module');
    }
    foreach (['downgrade' => '1.0.2', 'invalid' => '1.0.10', 'deleted' => null] as $case => $version) {
        $fixture = maintenanceFixture();
        if ($version === null) { $fixture->db->pdo->exec("DELETE FROM fixture_modules WHERE module_name = 'hidden'"); }
        else { $fixture->db->pdo->prepare("UPDATE fixture_modules SET installed_version = ? WHERE module_name = 'hidden'")->execute([$version]); }
        $before = $fixture->db->snapshot();
        maintenanceRejected(fn() => $fixture->controller->upgrade_database('hidden'), 'fresh stored version ' . $case);
        maintenanceNoMutation($fixture, $before, 'fresh stored version ' . $case);
    }
    foreach (['aligned', 'hidden'] as $name) {
        $fixture = maintenanceFixture();
        if ($name === 'hidden') { $fixture->db->pdo->exec("UPDATE fixture_modules SET installed_version = '1.0.1' WHERE module_name = 'hidden'"); }
        $before = $fixture->db->snapshot();
        $fixture->controller->upgrade_database($name);
        maintenanceCheck(count(array_filter($GLOBALS['maintenanceAlerts'], fn($alert) => $alert[0] === 'success')) === 1, $name . ': already aligned replay confirms current storage');
        maintenanceNoMutation($fixture, $before, $name . ': already aligned replay');
    }
    $fixture = maintenanceFixture();
    $fixture->db->pdo->exec("UPDATE fixture_modules SET active = 1 WHERE module_name = 'hidden'");
    $fixture->controller->upgrade_database('hidden');
    maintenanceCheck((int) $fixture->db->row('hidden')->active === 1 && $GLOBALS['maintenanceAlerts'][0][0] === 'success', 'fresh active state is preserved rather than overwritten from module cache');
    $fixture = maintenanceFixture(); $before = $fixture->db->snapshot(); $fixture->db->allowLock = false;
    maintenanceRejected(fn() => $fixture->controller->upgrade_database('hidden'), 'concurrent maintenance');
    maintenanceNoMutation($fixture, $before, 'concurrent maintenance');
    maintenanceCheck(array_column($fixture->db->locks, 0) === ['GET_LOCK'], 'unacquired advisory lock is never released');
    foreach (['native_error', 'throw', 'failed_tracking', 'unconfirmed_tracking', 'change_activation'] as $mode) {
        $fixture = maintenanceFixture();
        $name = $mode === 'native_error' ? 'bad_runner' : 'hidden';
        $fixture->db->failTracking = $mode === 'failed_tracking';
        $fixture->db->ignoreTracking = $mode === 'unconfirmed_tracking';
        $GLOBALS['maintenanceFailureMode'] = $mode;
        $fixture->controller->upgrade_database($name);
        maintenanceCheck(count(array_filter($GLOBALS['maintenanceAlerts'], fn($alert) => $alert[0] === 'danger')) === 1, $mode . ': reports failure');
        maintenanceCheck(count(array_filter($GLOBALS['maintenanceAlerts'], fn($alert) => $alert[0] === 'success')) === 0, $mode . ': cannot report false success');
        maintenanceCheck(array_column($fixture->db->locks, 0) === ['GET_LOCK', 'RELEASE_LOCK'], $mode . ': releases lock even after failure');
        maintenanceCheck($GLOBALS['maintenanceHooks'] === [], $mode . ': no activation hooks');
        if ($mode !== 'change_activation') {
            maintenanceCheck($fixture->db->row($name)->installed_version === '1.0.0' && (int) $fixture->db->row($name)->active === 0, $mode . ': failure retains marker and inactive state');
        }
        if ($mode === 'failed_tracking' || $mode === 'unconfirmed_tracking') {
            $fixture->db->failTracking = $fixture->db->ignoreTracking = false;
            $GLOBALS['maintenanceFailureMode'] = null;
            $fixture->controller->upgrade_database('hidden');
            maintenanceCheck($fixture->db->row('hidden')->installed_version === '1.0.1', $mode . ': safe retry reaches confirmed version');
            maintenanceCheck((int) $fixture->db->pdo->query('SELECT COUNT(*) FROM fixture_payload')->fetchColumn() === 1, $mode . ': idempotent replay preserves one synthetic payload');
        }
    }
    // A glob match is not necessarily a migration accepted by the native filename regex.
    rename(APP_MODULES_PATH . 'hidden/migrations/101_maintenance_hidden.php', APP_MODULES_PATH . 'hidden/migrations/101_bad-name.php');
    file_put_contents(APP_MODULES_PATH . 'hidden/migrations/102_valid_fixture.php', '<?php class Migration_Valid_fixture extends App_module_migration { public function up() { maintenanceRunMigration("hidden"); } }');
    $header = file_get_contents(APP_MODULES_PATH . 'hidden/hidden.php');
    file_put_contents(APP_MODULES_PATH . 'hidden/hidden.php', str_replace('Version: 1.0.1', 'Version: 1.0.2', $header));
    $fixture = maintenanceFixture();
    $before = $fixture->db->snapshot();
    maintenanceCheck($fixture->modules->database_maintenance_inventory()['hidden']['status'] === 'missing_migration', 'native-invalid first filename blocks inventory despite later valid step');
    maintenanceRejected(fn() => $fixture->controller->upgrade_database('hidden'), 'native-invalid first filename');
    maintenanceNoMutation($fixture, $before, 'native-invalid first filename');
    foreach (['hr_profile' => '1.0.2', 'api' => '2.0.2'] as $name => $target) {
        mkdir(APP_MODULES_PATH . $name . '/migrations', 0700, true);
        file_put_contents(APP_MODULES_PATH . $name . '/' . $name . '.php', "<?php /*\nModule Name: $name\nVersion: $target\n*/");
    }
    file_put_contents(APP_MODULES_PATH . 'hr_profile/migrations/102_runtime_fixture.php', '<?php class Migration_Runtime_fixture extends App_module_migration { public function up() { maintenanceRunMigration("hr_profile"); hr_profile_type_of_training_exists("synthetic"); } }');
    foreach ([200, 201, 202] as $step) {
        file_put_contents(APP_MODULES_PATH . 'api/migrations/' . $step . '_api_fixture' . $step . '.php', '<?php class Migration_Api_fixture' . $step . ' extends App_module_migration { public function up() { maintenanceRunMigration("api"); } }');
    }
    $fixture = maintenanceFixture();
    $fixture->db->seed('hr_profile', '1.0.1', 0);
    $fixture->db->seed('api', '1.0.3', 0);
    $fixture->modules = $fixture->ci->app_modules = new App_modules();
    $fixture->controller = new Mods();
    $inventory = $fixture->modules->database_maintenance_inventory();
    maintenanceCheck($inventory['hr_profile']['status'] === 'missing_runtime', 'unavailable legacy helper blocks inventory');
    $before = $fixture->db->snapshot();
    maintenanceRejected(fn() => $fixture->controller->upgrade_database('hr_profile'), 'unavailable legacy helper');
    maintenanceNoMutation($fixture, $before, 'unavailable helper checked before any DDL');
    maintenanceCheck($inventory['api']['can_upgrade'] === true, 'documented API 103 to200 bridge is eligible');
    $GLOBALS['maintenanceAlerts'] = [];
    $fixture->controller->upgrade_database('api');
    maintenanceCheck($fixture->db->row('api')->installed_version === '2.0.2', 'native runner executes audited API 200-202 history');
    maintenanceCheck((int) $fixture->db->row('api')->active === 0, 'API bridge preserves inactive state');
    maintenanceCheck(count(array_filter($GLOBALS['maintenanceAlerts'], fn($alert) => $alert[0] === 'success')) === 1, 'API bridge confirms success');
    maintenanceCheck(App_modules::maintenance_native_steps('unknown', '1.0.3', '2.0.2')[0] === 104, 'legacy bridge cannot authorize a different module gap');
    echo "Module maintenance: {$checks} checks passed (real controller, native runner and synthetic SQLite).\n";
} finally {
    putenv($previousHidden === false ? 'CRM_HIDDEN_RESOURCES' : 'CRM_HIDDEN_RESOURCES=' . $previousHidden);
    putenv($previousIdeal === false ? 'CRM_ENABLE_IDEAL' : 'CRM_ENABLE_IDEAL=' . $previousIdeal);
    maintenanceCleanup($fixtureRoot);
}
