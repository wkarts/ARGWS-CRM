<?php
/** Dedicated ephemeral database ONLY. Never point this test at a CRM installation. */
declare(strict_types=1);
if (getenv('RECURRING_TEST_DB') !== 'crm_recurrence_test') {
    fwrite(STDERR, "Not executed: requires an empty dedicated RECURRING_TEST_DB=crm_recurrence_test.\n");
    exit(2);
}
if (!extension_loaded('mysqli')) { throw new RuntimeException('mysqli is required for the real database suite.'); }
mysqli_report(MYSQLI_REPORT_OFF);
date_default_timezone_set('UTC');
error_reporting(E_ALL & ~E_DEPRECATED); // Vendored CodeIgniter 3 creates dynamic driver properties on PHP >= 8.2.
set_error_handler(function ($severity, $message, $file, $line) { if (error_reporting() & $severity) throw new ErrorException($message, 0, $severity, $file, $line); return false; });
define('BASEPATH', dirname(__DIR__) . '/system/'); define('APPPATH', dirname(__DIR__) . '/application/'); define('APP_MODULES_PATH', dirname(__DIR__) . '/modules/'); define('CRON', true);
$checks = 0; $logs = []; $context = null;
function check($condition, $message): void { global $checks; $checks++; if (!$condition) throw new RuntimeException($message); }
function db_prefix(): string { return 'test_'; }
function get_staff_user_id(): int { return 7; }
function staff_can($permission, $feature): bool { return true; }
function get_option($name) { return ['invoice_prefix' => 'TEST-', 'invoice_number_format' => '1', 'allow_staff_view_invoices_assigned' => '1', 'invoices_create_invoice_from_recurring_only_on_paid_invoices' => '0'][$name] ?? ''; }
function log_message($level, $message): void { global $logs; $logs[] = $message; }
function show_error($message): void { throw new RuntimeException($message); }
function is_php($version): bool { return version_compare(PHP_VERSION, $version, '>='); }
function &get_instance() { global $context; return $context; }
function app_generate_hash(): string { return bin2hex(random_bytes(16)); }
function clear_textarea_breaks($text): string { return str_replace('<br />', '', (string) $text); }
function hooks() { throw new RuntimeException('No invoice hook is allowed during these tests.'); }
function total_rows($table, $where): int { return get_instance()->db->where($where)->count_all_results($table); }
require APPPATH . 'hooks/App_Autoloader.php'; App_Autoloader::register();
require BASEPATH . 'database/DB.php';
$connection = ['dbdriver' => 'mysqli', 'hostname' => getenv('RECURRING_TEST_HOST') ?: '127.0.0.1', 'port' => (int) (getenv('RECURRING_TEST_PORT') ?: 3306),
    'username' => 'recurrence_test', 'password' => getenv('RECURRING_TEST_PASSWORD'), 'database' => 'crm_recurrence_test', 'dbprefix' => '',
    'db_debug' => false, 'pconnect' => false, 'char_set' => 'utf8mb4', 'dbcollat' => 'utf8mb4_unicode_ci', 'stricton' => true];
$db = DB($connection, true);
check((int) $db->query('SELECT COUNT(*) AS n FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE()')->row()->n === 0, 'Database must be empty; refusing to change a populated database.');
$schema = str_replace('`tbl', '`test_', file_get_contents(dirname(__DIR__) . '/install/database.sql'));
check($db->conn_id->multi_query($schema), 'Install schema accepted');
do { if ($result = $db->conn_id->store_result()) $result->free(); } while ($db->conn_id->more_results() && $db->conn_id->next_result());
check($db->conn_id->errno === 0, 'Fresh installer schema imported');
class CI_Migration { public $db; }
require APPPATH . 'migrations/395_version_395.php';
$migration = new Migration_Version_395(); $migration->db = $db; $migration->up(); $migration->up(); $migration->down();
check($db->table_exists('test_recurring_rules'), 'Migration repeat and rollback preserve tables');
class App_Model { public $db; public $load; public $invoices_model; }
require APPPATH . 'models/Invoices_model.php';
require APPPATH . 'models/Recurring_rules_model.php';
require APPPATH . 'helpers/invoices_helper.php';
require APPPATH . 'helpers/sales_helper.php';
final class DatabaseInvoiceWriter extends Invoices_model { public function __construct($db) { $this->db = $db; } }
final class DatabaseRules extends Recurring_rules_model
{
    public function __construct($db) { $this->db = $db; $this->invoices_model = new DatabaseInvoiceWriter($db); $this->load = new class { public function model($name): void {} }; }
}
$context = new DatabaseRules($db); $model = $context;
$db->insert('test_clients', ['userid' => 10, 'company' => 'Cliente sintético', 'datecreated' => '2024-01-01 00:00:00', 'active' => 1]);
$invoice = ['id' => 8, 'clientid' => 10, 'number' => 1, 'datecreated' => '2024-01-01 00:00:00', 'date' => '2024-01-31', 'duedate' => '2024-02-05',
    'currency' => 1, 'subtotal' => '25.00', 'total' => '25.00', 'total_tax' => '0.00', 'hash' => str_repeat('b', 32), 'status' => 2, 'discount_type' => '',
    'include_shipping' => 0, 'billing_street' => '', 'addedfrom' => 7, 'sale_agent' => 7, 'recurring' => 1, 'cycles' => 12, 'total_cycles' => 1,
    'last_recurring_date' => '2024-02-29', 'custom_recurring' => 0, 'recurring_due_mode' => 'days'];
check($db->insert('test_invoices', $invoice), 'Synthetic source invoice');
check($db->insert('test_itemable', ['id' => 91, 'description' => 'Serviço sintético', 'long_description' => '', 'qty' => 1, 'rate' => 25,
    'rel_id' => 8, 'rel_type' => 'invoice', 'unit' => '', 'item_order' => 1]), 'Synthetic source item');
$db->insert('test_customfieldsvalues', ['relid' => 8, 'fieldid' => 42, 'fieldto' => 'invoice', 'value' => 'Valor sintético']);
$db->insert('test_customfieldsvalues', ['relid' => 91, 'fieldid' => 43, 'fieldto' => 'items', 'value' => 'Item sintético']);
$original = $db->where('id', 8)->get('test_invoices')->row_array();
$preview = $model->preview('legacy', [8], 'migrate', null);
check($preview['token'] !== null, 'Migration preview accepted');
$result = $model->apply($preview['token'], true); $ruleId = $result['rule_ids'][0];
check($db->where('id', 8)->get('test_invoices')->row_array() === array_replace($original, ['recurring' => '0']), 'Only recurrence metadata changed in source');
check($model->apply($preview['token'], true) === $result, 'Idempotent replay in MariaDB');
check((int) $db->count_all('test_recurring_rule_audit') === 1, 'Single migration audit');
$rule = $db->where('id', $ruleId)->get('test_recurring_rules')->row_array();
check($rule['state'] === 'paused', 'Imported rule initially paused');
// Simulated clock for the scheduler, no production clock manipulation or external calls.
$db->where('id', $ruleId)->update('test_recurring_rules', ['state' => 'active']);
check(app\services\RecurringInvoiceLock::run($db, fn() => $model->process_due_locked('2024-03-31')) === 1, 'Real local invoice draft created atomically');
$run = $db->where('rule_id', $ruleId)->get('test_recurring_rule_runs')->row_array();
$draft = $db->where('id', $run['invoice_id'])->get('test_invoices')->row_array();
check((int) $draft['status'] === 6 && $draft['allowed_payment_modes'] === serialize([]) && (int) $draft['sent'] === 0, 'Draft has no payment mode or send');
check($draft['date'] === '2024-03-31' && $draft['duedate'] === '2024-04-05' && $draft['total'] === '25.00', 'Calendar and amounts persisted');
check((int) $db->where('relid', $draft['id'])->where('fieldto', 'invoice')->count_all_results('test_customfieldsvalues') === 1, 'Invoice custom fields retained');
$newItem = $db->where('rel_id', $draft['id'])->where('rel_type', 'invoice')->get('test_itemable')->row_array();
check((int) $db->where('relid', $newItem['id'])->where('fieldto', 'items')->count_all_results('test_customfieldsvalues') === 1, 'Item custom fields retained');
check(app\services\RecurringInvoiceLock::run($db, fn() => $model->process_due_locked('2024-03-31')) === 0, 'Cron replay does not duplicate cycle');
// Deliberately violate a unique cycle inside an explicit transaction, then roll it back.
$beforeRuns = (int) $db->count_all('test_recurring_rule_runs');
$db->trans_begin(); $db->where('id', $ruleId)->update('test_recurring_rules', ['state' => 'paused']);
$duplicate = $db->query('INSERT INTO test_recurring_rule_runs (rule_id,cycle_date,invoice_id,created_at) VALUES (?,?,?,?)', [$ruleId, $run['cycle_date'], 999, '2024-03-31 00:00:00']);
check($duplicate === false && !$db->trans_status(), 'Database enforces unique cycle'); $db->trans_rollback();
check($db->where('id', $ruleId)->get('test_recurring_rules')->row()->state === 'active' && (int) $db->count_all('test_recurring_rule_runs') === $beforeRuns, 'Real transaction rollback');
// Independent connection proves named-lock exclusion and release.
$other = DB($connection, true);
app\services\RecurringInvoiceLock::run($db, function () use ($other) {
    try { app\services\RecurringInvoiceLock::run($other, fn() => true); throw new LogicException('Expected contention'); }
    catch (app\services\RecurringInvoiceLockUnavailable $e) { check(true, 'Concurrent connection excluded'); }
});
check(app\services\RecurringInvoiceLock::run($other, fn() => true) === true, 'Named lock released after work');
$other->close(); $db->close();
echo "Independent recurring MariaDB integration: $checks checks passed with real installer schema, CI3 driver, migration, model and invoice writer. No provider/mail hooks.\n";
