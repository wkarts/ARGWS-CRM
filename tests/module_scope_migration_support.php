<?php
/** Isolated lifecycle harness: real production runners/hooks, SQLite and I/O adapters. */
declare(strict_types=1);

$moduleName = $argv[1] ?? '';
if (!in_array($moduleName, ['api', 'exports', 'einvoice'], true)) {
    throw new InvalidArgumentException('Usage: php ' . basename($argv[0]) . ' api|exports|einvoice');
}
$sourceRoot = dirname(__DIR__);
$fixtureRoot = sys_get_temp_dir() . '/scope-migrations-' . $moduleName . '-' . bin2hex(random_bytes(6));
mkdir($fixtureRoot . '/' . $moduleName . '/migrations', 0700, true);
$modulePath = $fixtureRoot . '/' . $moduleName . '/';
foreach (array_merge([$sourceRoot . '/modules/' . $moduleName . '/' . $moduleName . '.php'],
    glob($sourceRoot . '/modules/' . $moduleName . '/install*.php'),
    glob($sourceRoot . '/modules/' . $moduleName . '/migrations/*.php')) as $source) {
    $relative = substr($source, strlen($sourceRoot . '/modules/' . $moduleName . '/'));
    if (!copy($source, $modulePath . $relative)) { throw new RuntimeException('fixture_copy_failed'); }
}
// Composer/SDK bootstrap is not under test. einvoice/vendor is intentionally not
// tracked in the repository. Source hooks and every migration remain unmodified.
mkdir($modulePath . 'vendor', 0700);
file_put_contents($modulePath . 'vendor/autoload.php', '<?php // Explicit test-only dependency autoloader adapter.');
register_shutdown_function(static function () use ($fixtureRoot): void {
    $paths = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($fixtureRoot, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($paths as $path) { $path->isDir() ? rmdir($path->getPathname()) : unlink($path->getPathname()); }
    rmdir($fixtureRoot);
});
define('BASEPATH', $sourceRoot . '/system/');
define('APPPATH', $sourceRoot . '/application/');
define('APP_MODULES_PATH', $fixtureRoot . '/');
require APPPATH . 'libraries/App_modules.php';
require APPPATH . 'libraries/App_module_migration.php';

function &get_instance() { return $GLOBALS['CI']; }
function db_prefix() { return 'scope_'; }
function hooks() { return $GLOBALS['scopeHooks']; }
function register_activation_hook($module, $callback) { hooks()->add_action('activate_' . $module . '_module', $callback); }
function register_language_files($module, $files) {}
function log_message($level, $message) { $GLOBALS['scopeLogs'][] = [$level, $message]; }
function is_admin() { return true; }
function staff_can(...$args) { return true; }
function _l($name) { return $name; }
function admin_url($path) { return '/admin/' . $path; }
function register_staff_capabilities(...$args) { $GLOBALS['scopeCapabilities'][] = $args; }
function add_option($name, $value = '') {
    $db = get_instance()->db;
    if ($db->where('name', $name)->get(db_prefix() . 'options')->row()) { return false; }
    return $db->insert(db_prefix() . 'options', ['name' => $name, 'value' => $value]);
}
final class ScopeMigrationResult {
    public function __construct(private array $rows) {}
    public function row() { return isset($this->rows[0]) ? (object) $this->rows[0] : null; }
    public function result_array() { return $this->rows; }
}
final class ScopeMigrationHooks {
    public array $actions = [];
    public array $trace = [];
    public function add_action($name, $callback, ...$unused) { $this->actions[$name][] = $callback; }
    public function add_filter($name, $callback, ...$unused) {}
    public function do_action($name, ...$args) {
        $this->trace[] = $name;
        foreach ($this->actions[$name] ?? [] as $callback) { $callback(...$args); }
    }
}
final class ScopeMigrationDb {
    public array $queries = [];
    public array $writes = [];
    public ?string $failVersion = null;
    public ?string $ignoreVersion = null;
    public bool $failInsert = false;
    private array $predicates = [];
    public function __construct(public PDO $pdo) { $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION); }
    public function query($sql, $params = []) {
        $this->queries[] = $sql;
        // Only legacy API fresh-install MySQL AUTO_INCREMENT is translated.
        $sql = preg_replace('/`id` INT\(11\) NOT NULL AUTO_INCREMENT,/', '`id` INTEGER PRIMARY KEY AUTOINCREMENT,', $sql);
        $sql = preg_replace('/,\s*PRIMARY KEY \(`id`\)/', '', $sql);
        $statement = $this->pdo->prepare($sql);
        $statement->execute($params);
        return new ScopeMigrationResult($statement->columnCount() ? $statement->fetchAll(PDO::FETCH_ASSOC) : []);
    }
    public function select($columns) { return $this; }
    public function where($key, $value) { $this->predicates[$key] = $value; return $this; }
    private function takeWhere(): array {
        $where = $this->predicates; $this->predicates = [];
        return [$where ? ' WHERE ' . implode(' AND ', array_map(static fn($key) => $key . ' = ?', array_keys($where))) : '', array_values($where)];
    }
    public function get($table) { [$where, $params] = $this->takeWhere(); return $this->query('SELECT * FROM ' . $table . $where, $params); }
    public function insert($table, $data) {
        $this->writes[] = ['insert', $table, $data];
        if ($this->failInsert) { return false; }
        return $this->query('INSERT INTO ' . $table . ' (' . implode(',', array_keys($data)) . ') VALUES (' . implode(',', array_fill(0, count($data), '?')) . ')', array_values($data)) !== false;
    }
    public function update($table, $data) {
        [$where, $params] = $this->takeWhere();
        $this->writes[] = ['update', $table, $data];
        if (isset($data['installed_version']) && $data['installed_version'] === $this->failVersion) { return false; }
        if (isset($data['installed_version']) && $data['installed_version'] === $this->ignoreVersion) { return true; }
        return $this->query('UPDATE ' . $table . ' SET ' . implode(',', array_map(static fn($key) => $key . ' = ?', array_keys($data))) . $where, array_merge(array_values($data), $params)) !== false;
    }
    public function table_exists($table) { return $this->query("SELECT name FROM sqlite_master WHERE type='table' AND name=?", [$table])->row() !== null; }
    public function field_exists($field, $table) { return in_array($field, array_column($this->query('PRAGMA table_info(' . $table . ')')->result_array(), 'name'), true); }
}
$target = $moduleName === 'api' ? '2.0.2' : '1.0.1';
$baseline = $moduleName === 'api' ? '2.0.1' : '1.0.0';
$GLOBALS['scopeChecks'] = 0;
$GLOBALS['scopeHooks'] = new ScopeMigrationHooks();
$GLOBALS['scopeLogs'] = [];
$GLOBALS['scopeCapabilities'] = [];
function scopeCheck($condition, string $name): void {
    ++$GLOBALS['scopeChecks'];
    if (!$condition) { throw new RuntimeException('FAIL: ' . $name); }
}
function scopeThrows(callable $action, string $expected, string $name): void {
    $message = null;
    try { $action(); } catch (RuntimeException $exception) { $message = $exception->getMessage(); }
    scopeCheck($message === $expected, $name . ' (actual=' . var_export($message, true) . ')');
}
function scopeFixture(?string $version = null, bool $apiSchema = true): object {
    global $moduleName, $modulePath, $target;
    $db = new ScopeMigrationDb(new PDO('sqlite::memory:'));
    $db->query('CREATE TABLE scope_modules(module_name TEXT PRIMARY KEY, installed_version TEXT, active INT NOT NULL)');
    $db->query('CREATE TABLE scope_options(name TEXT PRIMARY KEY, value TEXT)');
    $db->query("INSERT INTO scope_options VALUES('companyname','Synthetic company'),('einvoice_default_invoice_template','custom-template')");
    $db->query('CREATE TABLE scope_invoices(id INT PRIMARY KEY,total INT)');
    $db->query('INSERT INTO scope_invoices VALUES(42,125000)');
    if ($apiSchema && $moduleName === 'api') {
        $db->query('CREATE TABLE scope_user_api(id INT PRIMARY KEY,user TEXT,name TEXT,token TEXT,expiration_date TEXT,permission_enable INT)');
        $db->query('CREATE TABLE scope_user_api_permissions(api_id INT,feature TEXT,capability TEXT)');
        $db->query("INSERT INTO scope_user_api VALUES(7,'synthetic','Synthetic','not-a-real-token','2030-01-01',1)");
        $db->query("INSERT INTO scope_user_api_permissions VALUES(7,'invoices','view')");
    }
    if ($version !== null) { $db->insert('scope_modules', ['module_name' => $moduleName, 'installed_version' => $version, 'active' => 0]); }
    $ci = (object) [
        'db' => $db,
        'load' => new class {
            public int $forgeLoads = 0;
            public function helper($name) {}
            public function dbforge() { ++$this->forgeLoads; }
        },
        // Historical API 201 must never be called in this release's activation.
        'dbforge' => new class { public function __call($method, $args) { throw new RuntimeException('historical_api_ddl_replayed'); } },
        'lang' => new class { public function load($name) {} public function line($name) { return $name; } },
        'app_menu' => new class {
            public array $entries = [];
            public function add_sidebar_menu_item(...$args) { $this->entries[] = $args; }
            public function add_sidebar_children_item(...$args) { $this->entries[] = $args; }
        },
        'app' => new class {
            public array $settings = [];
            public function add_settings_section_child(...$args) { $this->settings[] = $args; }
        },
    ];
    $reflection = new ReflectionClass(App_modules::class);
    $modules = $reflection->newInstanceWithoutConstructor();
    $module = ['system_name' => $moduleName, 'headers' => ['version' => $target], 'init_file' => $modulePath . $moduleName . '.php', 'path' => $modulePath, 'installed_version' => $version ?? false, 'activated' => 0];
    foreach (['ci' => $ci, 'modules' => [$moduleName => $module], 'db_modules' => [], 'active_modules' => []] as $property => $value) {
        $reflection->getProperty($property)->setValue($modules, $value);
    }
    $ci->app_modules = $modules;
    $GLOBALS['CI'] = $ci;
    $db->queries = []; $db->writes = []; hooks()->trace = [];
    return $ci;
}
function scopeRow() { return get_instance()->db->where('module_name', $GLOBALS['moduleName'])->get('scope_modules')->row(); }
function scopeDdl(): array { return array_values(array_filter(get_instance()->db->queries, static fn($sql) => (bool) preg_match('/^(CREATE|ALTER|DROP)\b/i', $sql))); }
function scopeBusinessSnapshot(): array {
    $db = get_instance()->db;
    $tables = ['scope_invoices', 'scope_options'];
    if ($GLOBALS['moduleName'] === 'api') { $tables = array_merge($tables, ['scope_user_api', 'scope_user_api_permissions']); }
    $snapshot = [];
    foreach ($tables as $table) { $snapshot[$table] = $db->get($table)->result_array(); }
    return $snapshot;
}
