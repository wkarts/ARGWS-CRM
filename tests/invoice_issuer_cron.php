<?php
/**
 * Real Cron_model queries and CI3 PDO SQLite driver, with synthetic X/Y/NULL rows.
 * No app bootstrap, transports, credentials, production records or provider calls.
 * Invoice send/status collaborators and MySQL named-lock functions are doubles.
 */
declare(strict_types=1);
date_default_timezone_set('UTC');
error_reporting(E_ALL & ~E_DEPRECATED); // Vendored CI3 compatibility on PHP >= 8.2.
set_error_handler(function ($severity, $message, $file, $line) {
    if (error_reporting() & $severity) { throw new ErrorException($message, 0, $severity, $file, $line); }
    return false;
});
define('BASEPATH', dirname(__DIR__) . '/system/');
define('APPPATH', dirname(__DIR__) . '/application/');
require APPPATH . 'services/InvoiceIssuerScope.php';
require APPPATH . 'services/RecurringInvoiceDates.php';
require APPPATH . 'services/RecurringInvoiceLock.php';
require BASEPATH . 'database/DB.php';

class App_Model { public $db; public $load; public $invoices_model; public $recurring_rules_model; }
require APPPATH . 'models/Invoices_model.php';
require APPPATH . 'models/Cron_model.php';

$checks = 0;
$statusReads = [];
function check($condition, string $message): void { global $checks; ++$checks; if (!$condition) { throw new RuntimeException($message); } }
function db_prefix(): string { return 'test_'; }
function is_php($version): bool { return version_compare(PHP_VERSION, $version, '>='); }
function log_message($level, $message): void {}
function show_error($message): void { throw new RuntimeException($message); }
function get_option($name) {
    $options = ['invoice_auto_operations_hour' => '0', 'new_recurring_invoice_action' => '0',
        'invoices_create_invoice_from_recurring_only_on_paid_invoices' => '0',
        'automatically_send_invoice_overdue_reminder_after' => '0',
        'invoice_due_notice_before' => '3', 'invoice_due_notice_resend_after' => '0'];
    if (!array_key_exists($name, $options)) { throw new RuntimeException('Unexpected option: ' . $name); }
    return $options[$name];
}
function hooks() {
    return new class {
        public function apply_filters($name, $value) {
            if ($name !== 'send_recurring_invoices_system_email') { throw new RuntimeException('Unexpected hook'); }
            return 'false';
        }
    };
}
function update_invoice_status($id): int { global $statusReads; $statusReads[] = (int) $id; return Invoices_model::STATUS_OVERDUE; }
function is_invoices_overdue_reminders_enabled(): bool { return true; }

final class IssuerCronNotices
{
    public array $overdue = [];
    public array $due = [];
    public function send_invoice_overdue_notice($id): void { $this->overdue[] = (int) $id; }
    public function send_invoice_due_notice($id): void { $this->due[] = (int) $id; }
    public function get($id) { throw new RuntimeException('Future recurrence must not hydrate any invoice'); }
    public function add($data) { throw new RuntimeException('Future recurrence must not create any invoice'); }
}
final class IssuerCronHarness extends Cron_model
{
    public function __construct($db)
    {
        $this->db = $db;
        $this->manually = true;
        $this->invoices_model = new IssuerCronNotices();
        $this->recurring_rules_model = new class { public function process_due_locked($today): int { return 0; } };
        $this->load = new class {
            public function model($name): void {
                if (!in_array($name, ['invoices_model', 'recurring_rules_model'], true)) { throw new RuntimeException('Unexpected model: ' . $name); }
            }
        };
    }
}

function fixture(bool $withScope, string $method): array
{
    $db = DB(['dbdriver' => 'pdo', 'subdriver' => 'sqlite', 'dsn' => 'sqlite::memory:',
        'db_debug' => false, 'pconnect' => false, 'save_queries' => true], true);
    $db->conn_id->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $locks = (object) ['acquired' => 0, 'released' => 0];
    $db->conn_id->sqliteCreateFunction('GET_LOCK', function ($name, $timeout) use ($locks) { ++$locks->acquired; return 1; }, 2);
    $db->conn_id->sqliteCreateFunction('RELEASE_LOCK', function ($name) use ($locks) { ++$locks->released; return 1; }, 1);
    $db->query('CREATE TABLE test_invoices (id INTEGER PRIMARY KEY' . ($withScope ? ', issuer_id INTEGER NULL' : '') . ',
        recurring INTEGER, date TEXT, last_recurring_date TEXT NULL, number INTEGER, duedate TEXT NULL,
        recurring_type TEXT NULL, custom_recurring INTEGER, addedfrom INTEGER, sale_agent INTEGER, clientid INTEGER,
        cycles INTEGER, total_cycles INTEGER, status INTEGER, last_overdue_reminder TEXT NULL,
        last_due_reminder TEXT NULL, cancel_overdue_reminders INTEGER)');
    $db->query('CREATE TABLE test_recurring_rules (source_invoice_id INTEGER)');
    $base = ['recurring' => 1, 'date' => date('Y-m-d', strtotime('+1 year')), 'last_recurring_date' => null,
        'number' => 1, 'duedate' => date('Y-m-d', strtotime('+1 day')), 'recurring_type' => 'MONTH', 'custom_recurring' => 0,
        'addedfrom' => 1, 'sale_agent' => 1, 'clientid' => 1, 'cycles' => 0, 'total_cycles' => 0, 'status' => 1,
        'last_overdue_reminder' => null, 'last_due_reminder' => null, 'cancel_overdue_reminders' => 0];
    if ($method !== 'recurring_invoices') { $base['date'] = date('Y-m-d', strtotime('-10 days')); }
    if ($method === 'invoice_overdue') { $base['duedate'] = date('Y-m-d', strtotime('-1 day')); $base['status'] = 4; }
    // id 1 is legacy; 2 and 3 belong to distinct named issuers; 4 is malformed ownership.
    foreach ([1 => null, 2 => 101, 3 => 202, 4 => 0] as $id => $issuer) {
        $row = ['id' => $id] + $base;
        if ($withScope) { $row['issuer_id'] = $issuer; }
        check($db->insert('test_invoices', $row), 'Synthetic candidate inserted');
    }
    $excluded = $method === 'recurring_invoices'
        ? [['recurring' => 0], ['cycles' => 1, 'total_cycles' => 1], ['status' => 6], []]
        : [['status' => 2], ['status' => 5], ['status' => 6], ['duedate' => null]];
    if ($method === 'invoice_due') { $excluded[] = ['duedate' => date('Y-m-d', strtotime('-1 day'))]; $excluded[] = ['cancel_overdue_reminders' => 1]; }
    foreach ($excluded as $index => $changes) {
        $row = array_replace(['id' => $index + 5] + $base, $changes);
        if ($withScope) { $row['issuer_id'] = null; }
        check($db->insert('test_invoices', $row), 'Synthetic excluded row inserted');
    }
    if ($method === 'recurring_invoices') { $db->insert('test_recurring_rules', ['source_invoice_id' => 8]); }
    $db->queries = [];
    return [$db, $locks];
}

foreach ([true, false] as $withScope) {
    foreach (['recurring_invoices', 'invoice_overdue', 'invoice_due'] as $method) {
        $statusReads = [];
        [$db, $locks] = fixture($withScope, $method);
        $cron = new IssuerCronHarness($db);
        (new ReflectionMethod(Cron_model::class, $method))->invoke($cron);
        $queries = array_values(array_filter($db->queries, fn ($sql) => preg_match('/^SELECT\b/i', $sql) && str_contains($sql, 'test_invoices')));
        check(count($queries) === 1, $method . ': exactly one invoice selection executed');
        $query = $queries[0];
        // Execute exactly the SQL compiled and used by the real CI3 builder and real cron method.
        $selected = array_map('intval', array_column($db->conn_id->query($query)->fetchAll(PDO::FETCH_ASSOC), 'id'));
        sort($selected);
        $expected = $withScope ? [1] : [1, 2, 3, 4];
        check($selected === $expected, $method . ': real WHERE must retain only the authorized legacy rows');
        check(str_contains($query, 'issuer_id IS NULL') === $withScope, $method . ': schema-aware NULL ownership predicate');
        if ($method === 'invoice_overdue') {
            check($statusReads === $expected, 'Overdue status calculation receives legacy IDs only');
            check($cron->invoices_model->overdue === $expected, 'Overdue notice collaborator receives legacy IDs only');
        } elseif ($method === 'invoice_due') {
            check($cron->invoices_model->due === $expected, 'Due notice collaborator receives legacy IDs only');
        } else {
            check($locks->acquired === 1 && $locks->released === 1, 'Recurring scheduler still acquires/releases the shared lock');
        }
        check((int) $db->count_all('test_invoices') === ($method === 'invoice_due' ? 10 : 8), 'Cron does not create invoices in future-dated fixtures');
    }
}
echo "Invoice issuer cron: {$checks} checks passed (real CI3 SQL/SQLite; notices and named locks doubled).\n";
