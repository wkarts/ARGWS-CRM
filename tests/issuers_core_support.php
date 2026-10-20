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
    public function trans_begin(): bool { return $this->pdo->beginTransaction(); }
    public function trans_commit(): bool { return $this->pdo->commit(); }
    public function trans_rollback(): bool { return $this->pdo->inTransaction()?$this->pdo->rollBack():true; }
    public function trans_status(): bool { return true; }
    public function insert_id(): int { return (int)$this->pdo->lastInsertId(); }
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
    try { $operation(); } catch (RuntimeException | DomainException $exception) { $caught = $exception->getMessage(); }
    essentialCheck($caught === $message, $label . ' (actual: ' . var_export($caught, true) . ')');
}
function essentialFixture(?string $version = null, int $active = 0, bool $available = true): object
{
    $prefix = 'essential_' . bin2hex(random_bytes(5)) . '_';
    $pdo = new PDO(getenv('ISSUERS_TEST_DSN') ?: 'sqlite::memory:', getenv('ISSUERS_TEST_USER') ?: null, getenv('ISSUERS_TEST_PASSWORD') ?: null);
    if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite') { $pdo->exec('PRAGMA foreign_keys=ON'); }
    $db = new EssentialTestDb($pdo, $prefix);
    $db->query("CREATE TABLE {$prefix}modules(module_name VARCHAR(50) PRIMARY KEY, installed_version VARCHAR(20), active INT NOT NULL)");
    $db->query("CREATE TABLE {$prefix}options(name VARCHAR(80) PRIMARY KEY, value TEXT)");
    $db->query("INSERT INTO {$prefix}options VALUES('companyname','Synthetic global company'),('smtp_password','synthetic-test-only'),('payment_account','legacy-test-only')");
    $db->query("CREATE TABLE {$prefix}invoices(id INT PRIMARY KEY, clientid INT, total INT, paymentmode VARCHAR(80))");
    $db->query("INSERT INTO {$prefix}invoices VALUES(42,9,125000,'legacy-gateway')");
    $db->query("CREATE TABLE {$prefix}clients(userid INT PRIMARY KEY, company VARCHAR(80))");
    $db->query("INSERT INTO {$prefix}clients VALUES(9,'Legacy client')");
    $db->query("CREATE TABLE {$prefix}staff(staffid INT PRIMARY KEY, active INT, admin INT, last_activity VARCHAR(30))");
    $db->query("INSERT INTO {$prefix}staff VALUES(7,1,1,NULL)");
    $db->query("CREATE TABLE {$prefix}staff_permissions(staff_id INT NOT NULL,feature VARCHAR(100) NOT NULL,capability VARCHAR(100) NOT NULL)");
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
        $snapshot[$table] = $fixture->db->query('SELECT ' . ($table === 'invoices' ? 'id,clientid,total,paymentmode' : '*') . ' FROM ' . $fixture->prefix . $table . ' ORDER BY ' . $key)->result_array();
    }
    return $snapshot;
}
function essentialSeedSchema(object $fixture, ?callable $transform = null): void
{
    require_once APPPATH . 'services/issuers/IssuerSchema.php';
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
        // Compare the preserved 101 fields; migration103 adds a separately tested default-denied capability.
        $columns = $table === 'staff' ? 'issuer_id,staff_id,can_view,can_manage_clients,can_share_clients' : ($table === 'audit' ? 'id,actor_id,event,issuer_id,subject_id,created_at' : '*');
        $snapshot[$table] = $fixture->db->query('SELECT ' . $columns . ' FROM ' . $fixture->prefix . 'issuers_' . $table)->result_array();
    }
    return $snapshot;
}
function essentialCleanup(): void
{
    foreach ($GLOBALS['essentialFixtures'] as $fixture) {
        if ($fixture->db->driver() !== 'mysql') { continue; }
        // Only random-prefix synthetic test tables, children before parents.
        foreach (['company_task_priorities','company_standalone_priorities','company_invoice_schedule_discovery','company_invoice_schedule_drafts','company_invoice_schedule_modes','company_invoice_schedule_requests','company_invoice_schedule_runs','company_invoice_schedules','company_invoice_draft_events','company_invoice_drafts','company_invoice_copy_requests','company_local_noncash_settlements','company_local_noncash_restorations','company_local_noncash_declarations','company_project_file_requests','company_project_file_rule_requests','company_project_contact_files','company_project_file_states','company_project_file_upload_rules','company_project_fixed_billing_links','company_project_fixed_billing_origins','bb_api_cnab_contracts','company_expense_recurring_requests','company_expense_occurrences','company_expense_recurring','company_portal_mail_deliveries','company_portal_mail_preferences','company_google_mail_queue_bindings','company_google_mail_states','company_google_mail_accounts','company_task_billing_links','company_task_billing_origins','company_project_billing_rates','company_project_gantt_requests','company_task_gantt_dates','company_project_gantt_rules','company_project_timesheet_requests','company_project_timesheet_rules','company_milestone_requests','company_portal_milestones','company_task_milestones','company_milestone_details','company_milestone_portal_rules','company_client_import_batches','company_expense_billing_links','company_expense_billing_origins','company_portal_task_requests','company_portal_task_files','company_portal_task_comments','company_portal_task_origins','company_project_write_rules','company_project_portal_requests','company_portal_task_children','company_portal_project_tasks','company_project_portal_rules','company_contract_comment_requests','company_contract_discussions','company_portal_support_files','company_portal_support_requests','company_portal_support_replies','company_portal_support_origins','company_portal_contracts_files','company_portal_tickets_files','company_portal_projects_files','company_portal_ticket_replies','company_portal_contracts','company_portal_tickets','company_portal_projects','company_portal_operation_requests','company_contract_files','company_ticket_files','company_bank_receipt_reversals','company_bank_receipt_components','asaas_company_hosted_links','asaas_company_payers','company_sales_files','company_sales_deliveries','company_import_batches','company_financial_events','company_currency_units','asaas_company_remote_operations','asaas_company_fee_expenses','asaas_company_jobs','company_standalone_comments','company_standalone_timers','company_standalone_tasks','company_financial_api_requests','company_api_token_requests','company_api_tokens','company_recurring_runs','company_recurring','company_sales_documents','company_project_files','company_task_dependencies','company_project_followers','company_task_followers','company_project_milestones','asaas_company_receipts','asaas_company_proofs','asaas_company_events','asaas_company_origin_meta','asaas_company_accounts','asaas_payment_origins','company_ticket_replies','company_ticket_requests','company_contract_requests','company_contract_counters','company_tickets','company_contracts','company_task_timers','company_task_comments','company_task_checklists','company_tasks','company_project_requests','company_projects','company_expense_files','company_expense_requests','company_expenses','company_expense_categories','company_credit_entries','company_credit_documents','creditnote_refunds','creditnotes','company_invoice_attachments','company_invoice_deliveries','company_payment_receipts','company_invoice_requests','company_portal_tokens','company_portal_limits','user_auto_login','contact_permissions','contacts','company_client_requests','issuers_master_authorizations','company_wizard_drafts','recurring_rule_runs','recurring_rule_audit','recurring_rule_batches','recurring_rules','mail_queue','issuers_invoice_documents', 'issuers_sequences', 'issuers_resource_permissions', 'issuers_installation_identity', 'issuers_client_links', 'issuers_staff_defaults', 'issuers_clients', 'issuers_staff', 'issuers_audit', 'issuers_registry', 'issuers_lock', 'modules', 'options','item_tax','itemable','invoicepaymentrecords','credits','currencies','countries', 'invoices', 'clients', 'staff_permissions', 'staff'] as $table) {
            $fixture->db->pdo->exec('DROP TABLE IF EXISTS ' . $fixture->prefix . $table);
        }
    }
}


/** Real waiter counts; each engine's native lock inventory remains a required test prerequisite. */
function essentialLockWaiters(EssentialTestDb $observer,int $blocker):int
{
    if(str_contains($observer->pdo->getAttribute(PDO::ATTR_SERVER_VERSION),'MariaDB')){
        // MariaDB trx0i_s.cc CACHE_MIN_IDLE_TIME_NS=100ms: every read renews last_read.
        // Polling every20ms would keep the initial empty lock snapshot alive indefinitely.
        usleep(150000);
        return (int)$observer->query('SELECT COUNT(DISTINCT w.requesting_trx_id) n FROM information_schema.INNODB_LOCK_WAITS w JOIN information_schema.INNODB_TRX b ON b.trx_id=w.blocking_trx_id WHERE b.trx_mysql_thread_id=?',[$blocker])->row()->n;
    }
    return (int)$observer->query('SELECT COUNT(DISTINCT w.REQUESTING_THREAD_ID) n FROM performance_schema.data_lock_waits w JOIN performance_schema.threads b ON b.THREAD_ID=w.BLOCKING_THREAD_ID WHERE b.PROCESSLIST_ID=?',[$blocker])->row()->n;
}
