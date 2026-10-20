<?php
/** Real native executor for recovered440, with real PDO rows and injected I/O failures. */
declare(strict_types=1);
if (PHP_SAPI !== 'cli' || !preg_match('/^essential_[a-f0-9]{10}_$/D', $argv[1] ?? '') || !str_starts_with(getenv('ISSUERS_TEST_DSN') ?: '', 'mysql:')) exit(2);
define('BASEPATH', dirname(__DIR__, 2).'/system/');
define('APPPATH', dirname(__DIR__, 2).'/application/');
require dirname(__DIR__).'/support/BbPdoDatabase.php';
require BASEPATH.'libraries/Migration.php';
require APPPATH.'libraries/App_Migration.php';
require APPPATH.'libraries/App.php';
require APPPATH.'services/issuers/IssuerCore.php';
function db_prefix() { return $GLOBALS['resume440Prefix']; }
function &get_instance() { return $GLOBALS['resume440CI']; }
function config_item($name) { return $name === 'subclass_prefix' ? 'App_' : null; }
function log_message($level, $message) { $GLOBALS['resume440Logs'][] = [$level, $message]; }
function hooks() { return $GLOBALS['resume440Hooks']; }
function delete_option($name) {}
function add_option($name, $value) {}
function resume440Check(bool $ok, string $message): void {
    ++$GLOBALS['resume440Checks']; if (!$ok) throw new RuntimeException($message);
}
final class Resume440Database extends BbPdoDatabase {
    public ?string $failDdl = null;
    public bool $failMarker = false;
    public int $ddlFailures = 0;
    public int $markerFailures = 0;
    public int $ddlQueries = 0;
    public function query($sql, $bind = []) {
        if (str_starts_with($sql, 'CREATE TABLE') && str_contains($sql, db_prefix().'company_invoice_draft_events')) ++$this->ddlQueries;
        if ($this->failDdl !== null && str_contains($sql, $this->failDdl)) { ++$this->ddlFailures; return false; }
        return parent::query($sql, $bind);
    }
    public function update($table, $values) {
        if ($table === db_prefix().'migrations' && $this->failMarker) { ++$this->markerFailures; return false; }
        return parent::update($table, $values);
    }
}
$GLOBALS['resume440Checks'] = 0; $GLOBALS['resume440Prefix'] = $argv[1]; $p = db_prefix();
$db = new Resume440Database(new PDO(getenv('ISSUERS_TEST_DSN'), getenv('ISSUERS_TEST_USER') ?: null, getenv('ISSUERS_TEST_PASSWORD') ?: null, [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]));
$config = []; require APPPATH.'config/migration.php';
resume440Check(($config['migration_version'] ?? null) === 440, 'shipped configuration targets440');
$db->query('CREATE TABLE `'.$p.'migrations` (version bigint NOT NULL) ENGINE=InnoDB');
$db->insert($p.'migrations', ['version'=>439]);
$GLOBALS['resume440Hooks'] = new class { public array $trace = []; public function do_action($name, ...$args) { $this->trace[] = $name; } };
$ci = (object)['db'=>$db]; $GLOBALS['resume440CI'] = $ci;
$ci->config = new class($config) { public function __construct(private array $values) {} public function item($name) { return $this->values[$name] ?? null; } };
$ci->load = new class { public function config($name) {} public function library($name, $options) {} };
$reflection = new ReflectionClass(App_Migration::class); $migration = $reflection->newInstanceWithoutConstructor();
foreach (['_migration_type'=>'sequential', '_migration_path'=>APPPATH.'migrations/', '_migration_version'=>440, '_migration_table'=>$p.'migrations', '_migration_regex'=>'/^\d{3}_(\w+)$/'] as $key=>$value) $reflection->getProperty($key)->setValue($migration, $value);
$ci->migration = $migration;
$reflection = new ReflectionClass(App::class); $app = $reflection->newInstanceWithoutConstructor(); $reflection->getProperty('ci')->setValue($app, $ci); $run = $reflection->getMethod('upgrade_database_silent');
$marker = static fn() => (int)$app->get_current_db_version();
$completed = static fn() => count(array_filter(hooks()->trace, static fn($name) => $name === 'database_updated'));
$db->failDdl = 'CREATE TABLE IF NOT EXISTS `'.$p.'company_invoice_draft_events`';
$result = $run->invoke($app);
resume440Check(!$result['success'] && $marker() === 439 && $db->ddlFailures === 1 && $completed() === 0, 'partial440 DDL cannot confirm439→440 or emit completion');
resume440Check($db->table_exists($p.'company_invoice_drafts') && !$db->table_exists($p.'company_invoice_draft_events'), 'partial DDL really created parent before failing child');
$db->failDdl = null; $db->failMarker = true;
$result = $run->invoke($app);
resume440Check(!$result['success'] && $marker() === 439 && $db->markerFailures > 0 && $completed() === 0, 'failed native marker is not claimed as upgrade success');
$db->failMarker = false;
resume440Check($db->query('ALTER TABLE `'.$p.'company_invoice_draft_events` MODIFY data_json mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL') !== false, 'metadata fault injection succeeded on actual data_json column');
$result = $run->invoke($app);
resume440Check(!$result['success'] && $marker() === 439 && $completed() === 0, 'invalid metadata does not advance marker');
resume440Check($db->query('ALTER TABLE `'.$p.'company_invoice_draft_events` MODIFY data_json mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL') !== false, 'metadata restored before retry');
$result = $run->invoke($app);
resume440Check($result['success'] && $marker() === 440 && $completed() === 1, 'actual executor confirms all contiguous440 only after persisted marker');
$ddl = $db->ddlQueries; $result = $run->invoke($app);
resume440Check($result['success'] && $marker() === 440 && $db->ddlQueries === $ddl, 'current native executor does not reexecute schema bodies');
echo 'Native440 actual executor confirmed: '.$GLOBALS['resume440Checks'].' checks; DDL/marker/metadata failures, retry, tracking and replay.'.PHP_EOL;
