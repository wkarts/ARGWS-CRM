<?php
/**
 * Essential-module integration tests. App_modules, its activation hook and the
 * native App_module_migration runner are production code. Only framework I/O
 * (hooks/loader/query-builder) is adapted; every stored row uses a real PDO DB.
 * SQLite translates MySQL DDL only and does not prove MySQL/InnoDB behavior.
 * Run with ISSUERS_TEST_DSN/USER/PASSWORD for an isolated MySQL test database.
 */
declare(strict_types=1);

define('BASEPATH', dirname(__DIR__) . '/system/');
define('APPPATH', dirname(__DIR__) . '/application/');
define('APP_MODULES_PATH', dirname(__DIR__) . '/modules/');
require APPPATH . 'libraries/App_modules.php';
require APPPATH . 'libraries/App_module_migration.php';
require APPPATH . 'services/EssentialIssuerModule.php';

function db_prefix() { return $GLOBALS['essentialPrefix']; }
function &get_instance() { return $GLOBALS['CI']; }
function hooks() { return $GLOBALS['essentialHooks']; }
function register_activation_hook($name, $callback) { hooks()->add_action('activate_' . $name . '_module', $callback); }
function register_language_files($name, $files) {}
function log_message($level, $message) { $GLOBALS['essentialLogs'][] = [$level, $message]; }

final class EssentialTestResult
{
    public function __construct(private array $rows) {}
    public function row() { return isset($this->rows[0]) ? (object) $this->rows[0] : null; }
    public function result_array(): array { return $this->rows; }
    public function result(): array { return array_map(static fn($row) => (object) $row, $this->rows); }
    public function num_rows(): int { return count($this->rows); }
}

final class EssentialTestHooks
{
    public array $actions = [];
    public array $trace = [];
    public $observer = null;
    public function add_action($name, $callback, ...$unused): void { $this->actions[$name][] = $callback; }
    public function do_action($name, ...$args): void
    {
        $this->trace[] = $name;
        if ($this->observer !== null) { ($this->observer)($name, $args); }
        foreach ($this->actions[$name] ?? [] as $callback) { $callback(...$args); }
    }
    public function apply_filters($name, $value, ...$unused) { return $value; }
}

final class EssentialTestLoader
{
    public array $views = [];
    public array $variables = [];
    public int $forgeLoads = 0;
    public function helper($name): void {}
    public function dbforge(): void { ++$this->forgeLoads; }
    public function model($name): void {}
    public function view($name, $data): void { $this->views[] = [$name, $data]; }
    public function vars($variables): void { $this->variables = $variables; }
}

final class EssentialTestDb
{
    public array $queries = [];
    public ?string $failOn = null;
    public ?string $failVersion = null;
    public ?int $failActive = null;
    public bool $failInsert = false;
    public bool $pretendVersionWrite = false;
    public ?int $pretendActiveWrite = null;
    public bool $db_debug = true;
    public array $data_cache = [];
    public string $dbdriver = 'pdo';
    public string $subdriver;
    private array $predicates = [];
    public function __construct(public PDO $pdo, public string $prefix)
    {
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->subdriver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
    }
    public function platform(): string { return $this->subdriver; }
    public function driver(): string { return $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME); }
    public function query($sql, $params = [])
    {
        $this->queries[] = [$sql, $params];
        if ($this->failOn !== null && str_contains($sql, $this->failOn)) { return false; }
        if ($this->driver() === 'sqlite') {
            $sql = preg_replace('/(?:int|bigint) unsigned NOT NULL AUTO_INCREMENT PRIMARY KEY/', 'INTEGER PRIMARY KEY AUTOINCREMENT', $sql);
            $sql = str_replace('INSERT IGNORE', 'INSERT OR IGNORE', $sql);
            $sql = preg_replace('/ ENGINE=InnoDB.*$/s', '', $sql);
            $sql = preg_replace('/UNIQUE KEY `[^`]+` \(/', 'UNIQUE (', $sql);
            $sql = preg_replace('/,?\s*KEY `[^`]+` \([^)]*\)/', '', $sql);
        }
        $statement = $this->pdo->prepare($sql);
        $statement->execute($params);
        return new EssentialTestResult($statement->columnCount() ? $statement->fetchAll(PDO::FETCH_ASSOC) : []);
    }
    public function where($column, $value): self { $this->predicates[$column] = $value; return $this; }
    public function select($columns): self { return $this; }
    public function get($table)
    {
        [$clause, $params] = $this->takeWhere();
        return $this->query('SELECT * FROM ' . $this->table($table) . $clause, $params);
    }
    public function insert($table, $data): bool
    {
        if ($this->failInsert) { $this->queries[] = ['FAILED INSERT ' . $table, $data]; return false; }
        return $this->query('INSERT INTO ' . $this->table($table) . ' (' . implode(',', array_keys($data)) . ') VALUES ('
            . implode(',', array_fill(0, count($data), '?')) . ')', array_values($data)) !== false;
    }
    public function update($table, $data): bool
    {
        [$clause, $params] = $this->takeWhere();
        if (($this->failVersion !== null && ($data['installed_version'] ?? null) === $this->failVersion)
            || ($this->failActive !== null && ($data['active'] ?? null) === $this->failActive)) {
            $this->queries[] = ['FAILED UPDATE ' . $table, $data];
            return false;
        }
        if (($this->pretendVersionWrite && isset($data['installed_version']) && $data['installed_version'] === essentialHeaderVersion())
            || ($this->pretendActiveWrite !== null && ($data['active'] ?? null) === $this->pretendActiveWrite)) {
            $this->queries[] = ['IGNORED UPDATE ' . $table, $data];
            return true;
        }
        return $this->query('UPDATE ' . $this->table($table) . ' SET '
            . implode(',', array_map(static fn($column) => $column . ' = ?', array_keys($data))) . $clause,
            array_merge(array_values($data), $params)) !== false;
    }
    public function table_exists($table): bool
    {
        if (!isset($this->data_cache['table_names'])) {
            $sql = $this->driver() === 'sqlite'
                ? "SELECT name AS table_name FROM sqlite_master WHERE type='table'"
                : 'SELECT TABLE_NAME AS table_name FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE()';
            $this->data_cache['table_names'] = array_column($this->query($sql)->result_array(), 'table_name');
        }
        return in_array($table, $this->data_cache['table_names'], true);
    }
    public function list_fields($table): array
    {
        // This repository's CI3 driver caches table names, but queries fields fresh.
        if ($this->driver() === 'sqlite') {
            return array_column($this->query('PRAGMA table_info(' . $table . ')')->result_array(), 'name');
        }
        return array_column($this->query('SELECT COLUMN_NAME AS field_name FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?', [$table])->result_array(), 'field_name');
    }
    public function field_exists($field, $table): bool { return in_array($field, $this->list_fields($table), true); }
    public function writes(): array
    {
        return array_values(array_filter($this->queries, static fn($query) => (bool) preg_match('/^(?:INSERT|UPDATE|DELETE|CREATE|ALTER|DROP|FAILED|IGNORED)\b/i', $query[0])));
    }
    public function ddl(): array
    {
        return array_values(array_filter($this->queries, static fn($query) => (bool) preg_match('/^(?:CREATE|ALTER|DROP)\b/i', $query[0])));
    }
    public function metadataReads(): array
    {
        return array_values(array_filter($this->queries, static fn($query) => (bool) preg_match('/^(?:PRAGMA|SHOW\s+(?:INDEX|COLUMNS))\b|(?:sqlite_master|information_schema\.)/i', $query[0])));
    }
    private function table($table): string { return $table === 'staff' ? $this->prefix . 'staff' : $table; }
    private function takeWhere(): array
    {
        $where = $this->predicates;
        $this->predicates = [];
        return [$where ? ' WHERE ' . implode(' AND ', array_map(static fn($column) => $column . ' = ?', array_keys($where))) : '', array_values($where)];
    }
}

$GLOBALS['essentialHooks'] = new EssentialTestHooks();
$GLOBALS['essentialFixtures'] = [];
$GLOBALS['essentialChecks'] = 0;
$GLOBALS['essentialLogs'] = [];

function essentialHeaderVersion(): string
{
    preg_match('/^Version:\s*(.+)$/m', file_get_contents(APP_MODULES_PATH . 'issuers/issuers.php'), $match);
    return trim($match[1]);
}
function essentialCheck($condition, string $label): void
{
    ++$GLOBALS['essentialChecks'];
    if (!$condition) { throw new RuntimeException('FAIL: ' . $label); }
}
function essentialThrows(callable $operation, string $message, string $label): void
{
    $caught = null;
    try { $operation(); } catch (RuntimeException $exception) { $caught = $exception->getMessage(); }
    essentialCheck($caught === $message, $label . ' (actual: ' . var_export($caught, true) . ')');
}
function essentialFixture(?string $version = null, int $active = 0, bool $available = true, int $coreVersion = 447): object
{
    $prefix = 'essential_' . bin2hex(random_bytes(5)) . '_';
    $pdo = new PDO(getenv('ISSUERS_TEST_DSN') ?: 'sqlite::memory:', getenv('ISSUERS_TEST_USER') ?: null, getenv('ISSUERS_TEST_PASSWORD') ?: null);
    if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite') { $pdo->exec('PRAGMA foreign_keys=ON'); }
    $db = new EssentialTestDb($pdo, $prefix);
    // Synthetic pre-priority boundary: this minimal fixture does not install the mandatory 448 sidecars.
    // This marker does not prove execution of the complete historical migration chain.
    if ($coreVersion < 1 || $coreVersion > 999) { throw new InvalidArgumentException('Invalid fixture core version'); }
    $db->query("CREATE TABLE {$prefix}migrations(version BIGINT NOT NULL)");
    $db->query("INSERT INTO {$prefix}migrations(version) VALUES(?)", [$coreVersion]);
    $db->query("CREATE TABLE {$prefix}modules(module_name VARCHAR(50) PRIMARY KEY, installed_version VARCHAR(20), active INT NOT NULL)");
    $db->query("CREATE TABLE {$prefix}options(name VARCHAR(80) PRIMARY KEY, value TEXT)");
    $db->query("INSERT INTO {$prefix}options VALUES('companyname','Synthetic global company'),('smtp_password','synthetic-test-only'),('payment_account','legacy-test-only')");
    $db->query("CREATE TABLE {$prefix}invoices(id INT PRIMARY KEY, clientid INT, total INT, paymentmode VARCHAR(80))");
    $db->query("INSERT INTO {$prefix}invoices VALUES(42,9,125000,'legacy-gateway')");
    $db->query("CREATE TABLE {$prefix}clients(userid INT PRIMARY KEY, company VARCHAR(80))");
    $db->query("INSERT INTO {$prefix}clients VALUES(9,'Legacy client')");
    $db->query("CREATE TABLE {$prefix}staff(staffid INT PRIMARY KEY, active INT, admin INT, last_activity VARCHAR(30))");
    $db->query("INSERT INTO {$prefix}staff VALUES(7,1,1,NULL)");
    if ($version !== null) { $db->insert($prefix . 'modules', ['module_name' => 'issuers', 'installed_version' => $version, 'active' => $active]); }
    $ci = (object) [
        'db' => $db,
        'load' => new EssentialTestLoader(),
        'lang' => new class { public function load($name) {} public function line($name) { return $name; } },
    ];
    $reflection = new ReflectionClass(App_modules::class);
    $modules = $reflection->newInstanceWithoutConstructor();
    $module = [
        'system_name' => 'issuers', 'headers' => ['version' => essentialHeaderVersion()],
        'init_file' => APP_MODULES_PATH . 'issuers/issuers.php', 'path' => APP_MODULES_PATH . 'issuers/',
        'installed_version' => $version ?? false, 'activated' => $active,
    ];
    foreach (['ci' => $ci, 'modules' => $available ? ['issuers' => $module] : [], 'db_modules' => [], 'active_modules' => $available && $active ? ['issuers' => $module] : []] as $property => $value) {
        $reflection->getProperty($property)->setValue($modules, $value);
    }
    $ci->app_modules = $modules;
    $fixture = (object) ['ci' => $ci, 'db' => $db, 'prefix' => $prefix, 'app_modules' => $modules];
    $GLOBALS['essentialFixtures'][] = $fixture;
    essentialUse($fixture);
    $fixture->legacy = essentialLegacy($fixture);
    $db->queries = [];
    return $fixture;
}
function essentialUse(object $fixture): void
{
    $GLOBALS['essentialPrefix'] = $fixture->prefix;
    $GLOBALS['CI'] = $fixture->ci;
    hooks()->trace = [];
    hooks()->observer = null;
}
function essentialRow(object $fixture)
{
    return $fixture->db->where('module_name', 'issuers')->get($fixture->prefix . 'modules')->row();
}
function essentialLegacy(object $fixture): array
{
    $snapshot = [];
    foreach (['options' => 'name', 'invoices' => 'id', 'clients' => 'userid'] as $table => $key) {
        $snapshot[$table] = $fixture->db->query('SELECT * FROM ' . $fixture->prefix . $table . ' ORDER BY ' . $key)->result_array();
    }
    return $snapshot;
}
function essentialSeedSchema(object $fixture, ?callable $transform = null): void
{
    require_once APP_MODULES_PATH . 'issuers/services/IssuerSchema.php';
    foreach (IssuerSchema::statements($fixture->prefix) as $sql) { $fixture->db->query($transform ? $transform($sql) : $sql); }
}
function essentialSeedIssuer(object $fixture): array
{
    $p = $fixture->prefix;
    $fixture->db->query("INSERT INTO {$p}issuers_registry(id,legal_name,tax_id,settings_json,created_by,created_at) VALUES(11,'Existing issuer','12345678000195','{\"theme\":\"custom\"}',7,'2026-10-18 00:00:00')");
    $fixture->db->query("INSERT INTO {$p}issuers_staff VALUES(11,7,1,1,1)");
    $fixture->db->query("INSERT INTO {$p}issuers_staff_defaults VALUES(7,11)");
    $fixture->db->query("INSERT INTO {$p}issuers_clients VALUES(9,11,'12345678000195',7,'2026-10-18 00:00:00')");
    $fixture->db->query("INSERT INTO {$p}issuers_client_links VALUES(9,11,7,'2026-10-18 00:00:00')");
    $fixture->db->query("INSERT INTO {$p}issuers_audit(id,actor_id,event,issuer_id,subject_id,created_at) VALUES(21,7,'synthetic',11,9,'2026-10-18 00:00:00')");
    return essentialIssuerSnapshot($fixture);
}
function essentialIssuerSnapshot(object $fixture): array
{
    $snapshot = [];
    foreach (['registry', 'staff', 'staff_defaults', 'clients', 'client_links', 'audit'] as $table) {
        $snapshot[$table] = $fixture->db->query('SELECT * FROM ' . $fixture->prefix . 'issuers_' . $table)->result_array();
    }
    return $snapshot;
}
function essentialCleanup(): void
{
    foreach ($GLOBALS['essentialFixtures'] as $fixture) {
        if ($fixture->db->driver() !== 'mysql') { continue; }
        // Only random-prefix synthetic test tables, children before parents.
        foreach (['issuers_client_links', 'issuers_staff_defaults', 'issuers_clients', 'issuers_staff', 'issuers_audit', 'issuers_registry', 'issuers_lock', 'modules', 'options', 'invoices', 'clients', 'staff'] as $table) {
            $fixture->db->pdo->exec('DROP TABLE IF EXISTS ' . $fixture->prefix . $table);
        }
    }
}

if (!defined('ISSUERS_ESSENTIAL_SUPPORT_ONLY')) {
    try {
        essentialCheck(essentialHeaderVersion() === '1.0.2', 'essential release has additive native migration 102');
        $fresh = essentialFixture();
        essentialCheck(!$fresh->app_modules->essential_modules_ready(), 'fresh install is not ready');
        essentialCheck($fresh->db->writes() === [], 'readiness is read-only on fresh install');
        $prewriteSeen = false;
        hooks()->observer = static function ($name) use ($fresh, &$prewriteSeen) {
            if ($name === 'activate_issuers_module') {
                essentialCheck($fresh->db->db_debug === false, 'controlled activation disables fatal DB debug handling');
                $row = essentialRow($fresh);
                $prewriteSeen = $row->installed_version === essentialHeaderVersion() && (int) $row->active === 0;
                essentialCheck(!$fresh->app_modules->essential_modules_ready(), 'prewritten header with active=0 is not ready');
            }
            if ($name === 'module_activated') {
                EssentialIssuerModule::assertSchema($fresh->db, $fresh->prefix);
                essentialCheck($fresh->app_modules->essential_modules_ready(), 'post-activation hook sees confirmed schema and marker');
            }
        };
        essentialCheck($fresh->app_modules->ensure_essential_modules(), 'fresh ensure succeeds through real activation');
        essentialCheck($prewriteSeen, 'native install sees header marker and explicit active=0');
        essentialCheck($fresh->db->db_debug === true, 'successful activation restores previous DB debug mode');
        essentialCheck($fresh->ci->load->forgeLoads > 0, 'native migration runner loads dbforge');
        essentialCheck(in_array('activate_issuers_module', hooks()->trace, true), 'real activation hook runs');
        essentialCheck($fresh->app_modules->is_active('issuers'), 'active module cache follows confirmed activation');
        essentialCheck(essentialRow($fresh)->installed_version === essentialHeaderVersion(), 'fresh marker equals release header');
        essentialCheck((int) essentialRow($fresh)->active === 1, 'fresh module becomes active');
        essentialCheck((int) $fresh->db->query('SELECT COUNT(*) AS n FROM ' . $fresh->prefix . 'issuers_registry')->row()->n === 0, 'fresh setup creates no company from global configuration');
        essentialCheck(essentialLegacy($fresh) === $fresh->legacy, 'fresh setup preserves global config and finance/customer rows');
        $fresh->db->queries = []; hooks()->trace = []; hooks()->observer = null;
        essentialCheck($fresh->app_modules->ensure_essential_modules(), 'ready replay succeeds');
        essentialCheck($fresh->db->writes() === [] && hooks()->trace === [], 'ready replay has no writes or activation hooks');

        foreach ([0, 1] as $active) {
            $upgrade = essentialFixture('1.0.1', $active);
            essentialSeedSchema($upgrade);
            $before = essentialSeedIssuer($upgrade);
            essentialCheck(!$upgrade->app_modules->essential_modules_ready(), '1.0.1 is pending even when active=' . $active);
            essentialCheck($upgrade->app_modules->upgrade_database('issuers') === true, 'native upgrade entrypoint reconciles active=' . $active);
            essentialCheck($upgrade->app_modules->essential_modules_ready(), 'upgrade marker/schema/activation ready');
            essentialCheck(essentialIssuerSnapshot($upgrade) === $before, 'upgrade preserves issuer settings, memberships, sharing and audit');
            essentialCheck(essentialLegacy($upgrade) === $upgrade->legacy, 'upgrade preserves legacy configuration and invoices');
        }
        $inactive = essentialFixture(essentialHeaderVersion(), 0);
        essentialSeedSchema($inactive); $before = essentialSeedIssuer($inactive);
        essentialCheck(!$inactive->app_modules->essential_modules_ready(), 'current header alone is insufficient while inactive');
        essentialCheck($inactive->app_modules->ensure_essential_modules(), 'inactive current release is reactivated');
        essentialCheck(essentialIssuerSnapshot($inactive) === $before, 'reactivation preserves existing issuer data');
        $inactive->db->where('module_name', 'issuers')->update($inactive->prefix . 'modules', ['active' => 0]);
        essentialCheck($inactive->app_modules->is_active('issuers'), 'fixture intentionally retains stale active cache');
        essentialCheck(!$inactive->app_modules->essential_modules_ready(), 'ready reads real DB instead of stale active cache');
        essentialCheck($inactive->app_modules->ensure_essential_modules(), 'ensure repairs real inactive state despite stale cache');

        foreach (['ddl', 'tracking', 'lying-tracking', 'activate-write', 'lying-activate-write', 'constraint-inspection'] as $failure) {
            $fixture = essentialFixture('1.0.1', 1);
            essentialSeedSchema($fixture); $before = essentialSeedIssuer($fixture);
            if ($failure === 'ddl') { $fixture->db->failOn = 'CREATE TABLE IF NOT EXISTS `' . $fixture->prefix . 'issuers_registry'; }
            if ($failure === 'tracking') { $fixture->db->failVersion = essentialHeaderVersion(); }
            if ($failure === 'lying-tracking') { $fixture->db->pretendVersionWrite = true; }
            if ($failure === 'activate-write') { $fixture->db->failActive = 1; }
            if ($failure === 'lying-activate-write') { $fixture->db->pretendActiveWrite = 1; }
            if ($failure === 'constraint-inspection') { $fixture->db->failOn = $fixture->db->driver() === 'sqlite' ? 'PRAGMA index_list' : 'SHOW INDEX'; }
            $result = false; $exception = null;
            try { $result = $fixture->app_modules->ensure_essential_modules(); } catch (RuntimeException $error) { $exception = $error; }
            essentialCheck($result === false || $exception !== null, $failure . ' cannot report success');
            essentialCheck($fixture->db->db_debug === true, $failure . ' restores original DB debug mode');
            essentialCheck((int) essentialRow($fixture)->active === 0, $failure . ' leaves module inactive');
            essentialCheck(!$fixture->app_modules->essential_modules_ready(), $failure . ' remains gated');
            essentialCheck(!$fixture->app_modules->is_active('issuers'), $failure . ' clears active cache');
            essentialCheck(essentialIssuerSnapshot($fixture) === $before && essentialLegacy($fixture) === $fixture->legacy, $failure . ' preserves all existing business data');
            essentialCheck(!in_array('module_activated', hooks()->trace, true), $failure . ' never emits activated event');
            $fixture->db->failOn = null; $fixture->db->failVersion = null; $fixture->db->pretendVersionWrite = false; $fixture->db->failActive = null; $fixture->db->pretendActiveWrite = null;
            essentialCheck($fixture->app_modules->ensure_essential_modules(), $failure . ' retry completes idempotently');
            essentialCheck(essentialIssuerSnapshot($fixture) === $before, $failure . ' retry preserves issuer state');
        }
        foreach ([false, true] as $lying) {
            $stateFailure = essentialFixture('1.0.1', 1);
            if ($lying) { $stateFailure->db->pretendActiveWrite = 0; } else { $stateFailure->db->failActive = 0; }
            $stateFailure->db->db_debug = false;
            essentialThrows(static fn() => $stateFailure->app_modules->ensure_essential_modules(),
                $lying ? 'issuer_activation_state_unconfirmed' : 'issuer_activation_state_write_failed',
                'failure to persist initial inactive state aborts installation');
            essentialCheck($stateFailure->db->ddl() === [] && hooks()->trace === [], 'unconfirmed inactive state prevents all hooks and DDL');
            essentialCheck(!$stateFailure->app_modules->essential_modules_ready(), 'old marker stays gated if inactive write cannot be confirmed');
            essentialCheck($stateFailure->db->db_debug === false, 'failure preserves previously disabled DB debug mode');
            $stateFailure->db->pretendActiveWrite = null; $stateFailure->db->failActive = null;
            essentialCheck($stateFailure->app_modules->ensure_essential_modules(), 'initial inactive write failure can be retried');
        }
        $partial = essentialFixture();
        $partial->db->failOn = 'CREATE TABLE IF NOT EXISTS `' . $partial->prefix . 'issuers_staff_defaults';
        essentialThrows(static fn() => $partial->app_modules->ensure_essential_modules(), 'issuer_migration_failed', 'fresh partial DDL failure is propagated');
        essentialCheck((int) essentialRow($partial)->active === 0 && $partial->db->table_exists($partial->prefix . 'issuers_registry'), 'partial DDL remains inactive and retryable');
        $partial->db->failOn = null;
        essentialCheck($partial->app_modules->ensure_essential_modules(), 'fresh partial schema retry succeeds');

        foreach ([true, false] as $available) {
            $protected = essentialFixture(essentialHeaderVersion(), 0, $available);
            $protected->db->queries = []; hooks()->trace = [];
            essentialCheck($protected->app_modules->is_essential('issuers'), 'essential invariant survives missing catalog entry');
            essentialCheck($protected->app_modules->deactivate('issuers') === false, 'deactivation rejected before any effects');
            essentialCheck($protected->app_modules->uninstall('issuers') === false, 'uninstall rejected before any effects');
            essentialCheck($protected->db->queries === [] && hooks()->trace === [], 'no DB access or hooks for forbidden lifecycle actions');
        }
        $missing = essentialFixture(null, 0, false);
        essentialCheck(!$missing->app_modules->ensure_essential_modules(), 'missing module cannot be installed or declared ready');
        essentialCheck($missing->db->writes() === [] && hooks()->trace === [], 'missing module has no mutation side effects');
        $future = essentialFixture('9.9.9', 1); essentialSeedSchema($future); $before = essentialSeedIssuer($future);
        $future->db->queries = [];
        essentialThrows(static fn() => $future->app_modules->ensure_essential_modules(), 'issuer_activation_version_invalid', 'older release refuses downgrade');
        essentialCheck($future->db->writes() === [] && hooks()->trace === [], 'downgrade rejected before writes/hooks');
        essentialCheck(essentialRow($future)->installed_version === '9.9.9', 'downgrade preserves newer marker');
        essentialCheck(essentialIssuerSnapshot($future) === $before && essentialLegacy($future) === $future->legacy, 'downgrade refusal preserves data');
        essentialThrows(static fn() => (new Migration_Version_102())->down(), 'issuer_destructive_downgrade_not_supported', '102 destructive down is blocked');

        $malformed = essentialFixture(essentialHeaderVersion(), 0);
        essentialSeedSchema($malformed);
        $malformed->db->query('DELETE FROM ' . $malformed->prefix . 'issuers_lock');
        $malformed->db->where('module_name', 'issuers')->update($malformed->prefix . 'modules', ['active' => 1]);
        $malformed->db->queries = [];
        essentialCheck(!$malformed->app_modules->essential_modules_ready(), 'current active marker without singleton lock is not ready');
        essentialCheck($malformed->db->writes() === [], 'lock readiness verification never repairs schema implicitly');
        essentialThrows(static fn() => EssentialIssuerModule::assertSchema($malformed->db, $malformed->prefix), 'issuer_essential_lock_missing', 'schema assertion requires singleton lock row');
        essentialCheck($malformed->app_modules->ensure_essential_modules(), 'native replay restores missing singleton lock');
        $malformed->db->query('ALTER TABLE ' . $malformed->prefix . 'issuers_registry DROP COLUMN settings_json');
        EssentialIssuerModule::invalidateSchemaCache($malformed->db); // Simulate a subsequent request after external schema corruption.
        essentialThrows(static fn() => EssentialIssuerModule::assertSchema($malformed->db, $malformed->prefix), 'issuer_essential_schema_incomplete', 'schema assertion detects missing required column');
        $malformed->db->queries = [];
        essentialCheck(!$malformed->app_modules->essential_modules_ready(), 'current active marker with missing required column is not ready');
        essentialCheck($malformed->db->writes() === [], 'column readiness verification is read-only');
        essentialThrows(static fn() => $malformed->app_modules->ensure_essential_modules(), 'issuer_essential_schema_incomplete', 'incompatible existing schema fails closed');
        essentialCheck((int) essentialRow($malformed)->active === 0 && !$malformed->app_modules->essential_modules_ready(), 'incompatible schema remains inactive after failed reconciliation');
        essentialCheck(essentialLegacy($malformed) === $malformed->legacy, 'incompatible schema failure preserves global configuration and finance');
        essentialThrows(static fn() => EssentialIssuerModule::assertSchema($malformed->db, 'unsafe-prefix;'), 'issuer_schema_prefix_invalid', 'schema assertion rejects unsafe identifier');
        $requestCache = essentialFixture(essentialHeaderVersion(), 1);
        essentialSeedSchema($requestCache);
        $requestCache->db->queries = [];
        essentialCheck($requestCache->app_modules->essential_modules_ready(), 'first readiness read verifies complete schema');
        essentialCheck(count($requestCache->db->metadataReads()) > 0, 'first readiness read queries schema metadata');
        $requestCache->db->queries = [];
        essentialCheck($requestCache->app_modules->essential_modules_ready(), 'second readiness read remains ready');
        essentialCheck($requestCache->db->metadataReads() === [], 'second readiness read reuses only request-local schema metadata');
        essentialCheck(count($requestCache->db->queries) === 2, 'cached readiness still reads live module state/version and lock');
        $requestCache->db->where('module_name', 'issuers')->update($requestCache->prefix . 'modules', ['active' => 0]);
        $requestCache->db->queries = [];
        essentialCheck(!$requestCache->app_modules->essential_modules_ready(), 'cached schema never hides revoked active state');
        essentialCheck(count($requestCache->db->queries) === 1 && $requestCache->db->metadataReads() === [], 'inactive state is read fresh before schema cache');
        $requestCache->db->where('module_name', 'issuers')->update($requestCache->prefix . 'modules', ['active' => 1, 'installed_version' => '1.0.1']);
        $requestCache->db->queries = [];
        essentialCheck(!$requestCache->app_modules->essential_modules_ready(), 'cached schema never hides pending version');
        essentialCheck(count($requestCache->db->queries) === 1, 'installed version is read fresh before schema cache');
        $requestCache->db->where('module_name', 'issuers')->update($requestCache->prefix . 'modules', ['installed_version' => '9.9.9']);
        essentialCheck(!$requestCache->app_modules->essential_modules_ready(), 'cached schema never approves a newer incompatible version');
        $requestCache->db->where('module_name', 'issuers')->update($requestCache->prefix . 'modules', ['installed_version' => essentialHeaderVersion()]);
        $requestCache->db->query('DELETE FROM ' . $requestCache->prefix . 'issuers_lock');
        $requestCache->db->queries = [];
        essentialCheck(!$requestCache->app_modules->essential_modules_ready(), 'cached schema never hides removed singleton lock');
        essentialCheck(count($requestCache->db->queries) === 2 && $requestCache->db->metadataReads() === [], 'lock is read fresh while schema metadata stays cached');
        $requestCache->db->query('INSERT INTO ' . $requestCache->prefix . 'issuers_lock(id) VALUES(1)');
        essentialCheck($requestCache->app_modules->essential_modules_ready(), 'restored live state becomes ready without cached authorization');
        EssentialIssuerModule::invalidateSchemaCache($requestCache->db);
        $requestCache->db->queries = [];
        essentialCheck($requestCache->app_modules->essential_modules_ready(), 'explicit invalidation permits fresh validation');
        essentialCheck(count($requestCache->db->metadataReads()) > 0, 'explicit invalidation expires request-local schema metadata');
        essentialCheck($requestCache->db->writes() === [], 'metadata validation and cache hits remain read-only');

        // SQL contract only: the facade captures production's MySQL metadata
        // query before execution. This is explicitly not cross-schema MySQL proof.
        $mysqlMetadataContract = new class($requestCache->db) {
            public string $dbdriver = 'mysqli';
            public ?array $foreignQuery = null;
            public function __construct(private EssentialTestDb $source) {}
            public function table_exists($table) { return $this->source->table_exists($table); }
            public function list_fields($table) { return $this->source->list_fields($table); }
            public function where($column, $value) { $this->source->where($column, $value); return $this; }
            public function get($table) { return $this->source->get($table); }
            public function query($sql, $bindings = [])
            {
                if (str_contains($sql, 'information_schema.KEY_COLUMN_USAGE')) {
                    $this->foreignQuery = [$sql, $bindings];
                    throw new RuntimeException('mysql_metadata_contract_captured');
                }
                if (str_contains($sql, 'information_schema.TABLES')) {
                    return new EssentialTestResult(array_map(static fn($table) => ['table_name' => $table, 'engine' => 'InnoDB'], $bindings));
                }
                throw new RuntimeException('unexpected_mysql_metadata_contract_query');
            }
        };
        essentialThrows(static fn() => EssentialIssuerModule::assertSchema($mysqlMetadataContract, $requestCache->prefix),
            'mysql_metadata_contract_captured', 'capture actual production MySQL foreign-key metadata SQL using explicit facade');
        essentialCheck(preg_match('/\bREFERENCED_TABLE_SCHEMA\s*=\s*DATABASE\s*\(\s*\)/i', $mysqlMetadataContract->foreignQuery[0]) === 1,
            'MySQL query contract excludes references to tables in other database schemas');
        essentialCheck(count($mysqlMetadataContract->foreignQuery[1]) === 7
            && count(array_filter($mysqlMetadataContract->foreignQuery[1], static fn($table) => str_starts_with($table, $requestCache->prefix . 'issuers_'))) === 7,
            'MySQL foreign-key metadata query binds only the seven essential fixture tables');

        $cached = essentialFixture(essentialHeaderVersion(), 1);
        essentialCheck(!$cached->app_modules->essential_modules_ready(), 'missing schema populates a negative CI table metadata cache');
        essentialCheck(!$cached->db->field_exists('legal_name', $cached->prefix . 'issuers_registry'), 'missing schema has no required columns');
        essentialCheck(isset($cached->db->data_cache['table_names']), 'fixture retains the real CI3 stale table-name cache');
        $cached->db->data_cache['unrelated_test_metadata'] = 'preserved';
        essentialCheck($cached->app_modules->ensure_essential_modules(), 'migration 102 invalidates stale CI metadata after migration 101 DDL');
        essentialCheck($cached->app_modules->essential_modules_ready(), 'fresh tables and fields are rediscovered after cache invalidation');
        essentialCheck($cached->db->data_cache['unrelated_test_metadata'] === 'preserved', 'metadata invalidation preserves unrelated cache entries');
        essentialCheck(essentialLegacy($cached) === $cached->legacy, 'cache recovery preserves global configuration and financial rows');

        $structuralCases = [
            ['primary key', 'lock', 'keys', static fn($sql) => str_replace(' NOT NULL PRIMARY KEY', ' NOT NULL', $sql)],
            ['registry tax identity uniqueness', 'registry', 'keys', static fn($sql) => preg_replace('/,\s*UNIQUE KEY `tax_id` \(`tax_id`\)/', '', $sql)],
            ['client tax identity uniqueness', 'clients', 'keys', static fn($sql) => preg_replace('/,\s*UNIQUE KEY `tax_id` \(`tax_id`\)/', '', $sql)],
            ['membership issuer foreign key', 'staff', 'foreign_keys', static fn($sql) => preg_replace('/,\s*FOREIGN KEY \(`issuer_id`\) REFERENCES `[^`]+` \(`id`\)/', '', $sql)],
            ['default membership composite foreign key', 'staff_defaults', 'foreign_keys', static fn($sql) => preg_replace('/,\s*FOREIGN KEY \(`issuer_id`, `staff_id`\) REFERENCES `[^`]+` \(`issuer_id`, `staff_id`\)/', '', $sql)],
            ['client owner foreign key', 'clients', 'foreign_keys', static fn($sql) => preg_replace('/,\s*FOREIGN KEY \(`owner_issuer_id`\) REFERENCES `[^`]+` \(`id`\)/', '', $sql)],
            ['share client foreign key', 'client_links', 'foreign_keys', static fn($sql) => preg_replace('/,\s*FOREIGN KEY \(`client_id`\) REFERENCES `[^`]+` \(`client_id`\)/', '', $sql)],
            ['share issuer foreign key', 'client_links', 'foreign_keys', static fn($sql) => preg_replace('/,\s*FOREIGN KEY \(`issuer_id`\) REFERENCES `[^`]+` \(`id`\)/', '', $sql)],
        ];
        foreach ($structuralCases as [$label, $table, $kind, $removeConstraint]) {
            $broken = essentialFixture(essentialHeaderVersion(), 1);
            essentialSeedSchema($broken, static function ($sql) use ($broken, $table, $removeConstraint) {
                return str_starts_with($sql, 'CREATE TABLE IF NOT EXISTS `' . $broken->prefix . 'issuers_' . $table . '`') ? $removeConstraint($sql) : $sql;
            });
            $before = essentialSeedIssuer($broken);
            $message = 'issuer_essential_' . $kind . '_invalid';
            essentialThrows(static fn() => EssentialIssuerModule::assertSchema($broken->db, $broken->prefix), $message, 'schema check detects missing ' . $label);
            $broken->db->queries = [];
            essentialCheck(!$broken->app_modules->essential_modules_ready(), 'current active marker cannot mask missing ' . $label);
            essentialCheck($broken->db->writes() === [], 'structural readiness check is read-only: ' . $label);
            essentialThrows(static fn() => $broken->app_modules->ensure_essential_modules(), $message, 'native replay refuses structurally invalid ' . $label);
            essentialCheck((int) essentialRow($broken)->active === 0 && !$broken->app_modules->essential_modules_ready(), 'structural failure remains inactive: ' . $label);
            essentialCheck(essentialIssuerSnapshot($broken) === $before && essentialLegacy($broken) === $broken->legacy, 'structural failure preserves existing business rows: ' . $label);
        }
        $partialUnique = essentialFixture(essentialHeaderVersion(), 1);
        essentialSeedSchema($partialUnique, static function ($sql) use ($partialUnique) {
            return str_starts_with($sql, 'CREATE TABLE IF NOT EXISTS `' . $partialUnique->prefix . 'issuers_registry`')
                ? preg_replace('/,\s*UNIQUE KEY `tax_id` \(`tax_id`\)/', '', $sql) : $sql;
        });
        if ($partialUnique->db->driver() === 'sqlite') {
            $partialUnique->db->query('CREATE UNIQUE INDEX ' . $partialUnique->prefix . 'partial_tax_id ON ' . $partialUnique->prefix . 'issuers_registry(tax_id) WHERE active=1');
        } else {
            $partialUnique->db->query('CREATE UNIQUE INDEX partial_tax_id ON ' . $partialUnique->prefix . 'issuers_registry(tax_id(4))');
        }
        essentialThrows(static fn() => EssentialIssuerModule::assertSchema($partialUnique->db, $partialUnique->prefix), 'issuer_essential_keys_invalid', 'partial/prefix unique index cannot attest full tax identity uniqueness');
        essentialCheck(!$partialUnique->app_modules->essential_modules_ready(), 'partial/prefix unique identity index remains gated');
        if ($fresh->db->driver() === 'mysql') {
            $nonTransactional = essentialFixture(essentialHeaderVersion(), 1);
            essentialSeedSchema($nonTransactional, static function ($sql) use ($nonTransactional) {
                return str_starts_with($sql, 'CREATE TABLE IF NOT EXISTS `' . $nonTransactional->prefix . 'issuers_lock`')
                    ? str_replace('ENGINE=InnoDB', 'ENGINE=MyISAM', $sql) : $sql;
            });
            essentialThrows(static fn() => EssentialIssuerModule::assertSchema($nonTransactional->db, $nonTransactional->prefix), 'issuer_essential_innodb_required', 'every essential table must use transactional InnoDB');
            essentialCheck(!$nonTransactional->app_modules->essential_modules_ready(), 'nontransactional essential table remains gated');
        }
        $failedInsert = essentialFixture(); $failedInsert->db->failInsert = true;
        essentialCheck(!$failedInsert->app_modules->ensure_essential_modules(), 'module tracking insert failure cannot activate');
        essentialCheck(essentialRow($failedInsert) === null && hooks()->trace === [], 'tracking insert failure never calls activation hook');

        $driver = $fresh->db->driver();
        echo 'Essential issuers lifecycle: ' . $GLOBALS['essentialChecks'] . ' checks passed (real App_modules + App_module_migration, ' . $driver
            . ($driver === 'sqlite' ? '; translated DDL, not MySQL proof' : '') . ").\n";
    } finally {
        essentialCleanup();
    }
}
