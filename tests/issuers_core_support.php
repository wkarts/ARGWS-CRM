<?php
/**
 * Essential-module integration tests. App_modules, its activation hook and the
 * native App_module_migration runner are production code. Only framework I/O
 * (hooks/loader/query-builder) is adapted; every stored row uses a real PDO DB.
 * SQLite translates MySQL DDL only and does not prove MySQL/InnoDB behavior.
 * Run with ISSUERS_TEST_DSN/USER/PASSWORD for an isolated MySQL test database.
 */
declare(strict_types=1);
require_once __DIR__.'/issuers_test_support.php';

define('BASEPATH', dirname(__DIR__) . '/system/');
define('APPPATH', dirname(__DIR__) . '/application/');
define('APP_MODULES_PATH', dirname(__DIR__) . '/modules/');
require APPPATH . 'libraries/App_modules.php';
require APPPATH . 'libraries/App_module_migration.php';
require APPPATH . 'services/issuers/IssuerCore.php';

function db_prefix() { return $GLOBALS['essentialPrefix']; }
function &get_instance() { return $GLOBALS['CI']; }
function hooks() { return $GLOBALS['essentialHooks']; }
function register_activation_hook($name, $callback) { hooks()->add_action('activate_' . $name . '_module', $callback); }
function register_language_files($name, $files) {}
function log_message($level, $message) { $GLOBALS['essentialLogs'][] = [$level, $message]; }

final class EssentialTestResult
{
    public function __construct(private array $rows) {}
    public function row_array(): array { return $this->rows[0] ?? []; }
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
    /** Last failed statement for integration-test diagnostics; bound values are never retained. */
    public ?array $lastQueryFailure = null;
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
            $metadata = issuerSqliteIntegrationColumn($this->pdo, $sql, $params);
            if ($metadata !== null) { return new EssentialTestResult($metadata); }
            $sql = preg_replace('/(?:int|bigint) unsigned NOT NULL AUTO_INCREMENT PRIMARY KEY/', 'INTEGER PRIMARY KEY AUTOINCREMENT', $sql);
            $sql = str_replace(' CHARACTER SET ascii COLLATE ascii_bin','',$sql);
            $sql = str_replace(' FOR UPDATE', '', $sql);
            $sql = preg_replace('/ALTER TABLE `([^`]+)` ADD INDEX `([^`]+)` \(([^)]+)\)/','CREATE INDEX `$2` ON `$1` ($3)',$sql);
            $sql = str_replace('INSERT IGNORE', 'INSERT OR IGNORE', $sql);
            $sql = preg_replace('/ ENGINE=InnoDB.*$/s', '', $sql);
            $sql = preg_replace('/UNIQUE KEY `[^`]+` \(/', 'UNIQUE (', $sql);
            $sql = preg_replace('/,?\s*KEY `[^`]+` \([^)]*\)/', '', $sql);
        }
        try {
            $statement = $this->pdo->prepare($sql);
            $statement->execute($params);
            return new E