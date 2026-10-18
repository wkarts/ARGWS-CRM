<?php

declare(strict_types=1);

// Execute the real CRON method with in-memory collaborators only. No DB, network,
// mail transport, app bootstrap, scheduler locks or production records are used.
date_default_timezone_set('UTC');
error_reporting(E_ALL);
set_error_handler(function (int $severity, string $message, string $file, int $line): bool {
    if (!(error_reporting() & $severity)) {
        return false;
    }
    throw new ErrorException($message, 0, $severity, $file, $line);
});

define('BASEPATH', dirname(__DIR__) . '/system/');
define('APPPATH', dirname(__DIR__) . '/application/');
define('APP_MODULES_PATH', dirname(__DIR__) . '/modules/');
require APPPATH . 'hooks/App_Autoloader.php';
App_Autoloader::register();

class App_Model
{
    public $db;
    public $load;
    public $invoices_model;
}

require APPPATH . 'models/Cron_model.php';

final class RecurrenceTestHooks
{
    public array $actions = [];

    public function apply_filters($name, $value)
    {
        if ($name !== 'send_recurring_invoices_system_email') {
            throw new RuntimeException('Unexpected filter: ' . $name);
        }
        return 'false';
    }

    public function do_action($name, $payload): void
    {
        $this->actions[] = [$name, $payload];
    }
}

$testOptions = [];
$testLogs = [];
$testHooks = new RecurrenceTestHooks();

function get_option($name)
{
    global $testOptions;
    if (!array_key_exists($name, $testOptions)) {
        throw new RuntimeException('Unexpected option: ' . $name);
    }
    return $testOptions[$name];
}
function db_prefix(): string { return 'tbl'; }
function _d($date) { return $date; }
function clear_textarea_breaks($value) { return $value; }
function get_custom_fields($entity): array { return []; }
function get_tags_in($id, $entity): array { return []; }
function handle_tags_save($tags, $id, $entity): void {}
function log_message($level, $message): void
{
    global $testLogs;
    $testLogs[] = [$level, $message];
}
function hooks(): RecurrenceTestHooks
{
    global $testHooks;
    return $testHooks;
}

final class RecurrenceTestDatabase
{
    public array $rows = [];
    public array $writes = [];
    private array $conditions = [];
    private array $increments = [];
    private string $selection = '*';

    public function __construct(array $rows)
    {
        foreach ($rows as $row) {
            $this->rows[$row['id']] = $row;
        }
    }

    public function select($columns): self { $this->selection = $columns; return $this; }
    public function from($table): self { return $this; }
    public function where($key, $value = null): self
    {
        $this->conditions[$key] = $value;
        return $this;
    }
    public function set($key, $value, $escape): self
    {
        if ($key !== 'total_cycles' || $value !== 'total_cycles+1' || $escape !== false) {
            throw new RuntimeException('Unexpected SQL expression');
        }
        $this->increments[$key] = 1;
        return $this;
    }
    public function get(): object
    {
        $rows = [];
        foreach ($this->rows as $row) {
            if ($row['recurring'] == 0 || ($row['cycles'] != 0 && $row['cycles'] == $row['total_cycles']) || $row['status'] == 6) {
                continue;
            }
            if (isset($this->conditions['status']) && $row['status'] != $this->conditions['status']) {
                continue;
            }
            $rows[] = $this->selection === '*' ? $row : array_intersect_key($row, array_flip(explode(',', $this->selection)));
        }
        $this->conditions = [];
        $this->selection = '*';
        return new class($rows) {
            public function __construct(private array $rows) {}
            public function result_array(): array { return $this->rows; }
        };
    }
    public function update($table, array $values = []): bool
    {
        if ($table !== 'tblinvoices' || !isset($this->conditions['id'])) {
            throw new RuntimeException('Unexpected update target');
        }
        $id = $this->conditions['id'];
        foreach ($this->increments as $key => $increment) {
            $values[$key] = $this->rows[$id][$key] + $increment;
        }
        $this->writes[] = [$id, $values];
        if (isset($this->rows[$id])) {
            $this->rows[$id] = array_replace($this->rows[$id], $values);
        }
        $this->conditions = [];
        $this->increments = [];
        return true;
    }
}

final class RecurrenceTestInvoices
{
    public array $created = [];
    public array $sent = [];
    public bool $failAdd = false;

    public function __construct(private RecurrenceTestDatabase $db) {}
    public function get($id): object { return (object) $this->db->rows[$id]; }
    public function add($data)
    {
        if ($this->failAdd) {
            return false;
        }
        $id = 10000 + count($this->created);
        $this->created[$id] = $data;
        return $id;
    }
    public function send_invoice_to_client($id, $template, $attach): void
    {
        $this->sent[] = [$id, $template, $attach];
    }
}

final class RecurrenceTestCron extends Cron_model
{
    public function __construct(array $rows)
    {
        $this->db = new RecurrenceTestDatabase($rows);
        $this->invoices_model = new RecurrenceTestInvoices($this->db);
        $this->load = new class {
            public function model($name): void
            {
                if ($name !== 'invoices_model') {
                    throw new RuntimeException('Unexpected model: ' . $name);
                }
            }
        };
        $this->manually = true;
    }
}

function invoiceFixture(int $id, array $changes = []): array
{
    return array_replace([
        'id' => $id, 'recurring' => 1, 'date' => '2025-01-26',
        'last_recurring_date' => null, 'number' => $id, 'duedate' => '2025-02-05',
        'recurring_type' => null, 'custom_recurring' => 0, 'addedfrom' => 1,
        'sale_agent' => 1, 'clientid' => 1, 'cycles' => 0, 'total_cycles' => 0,
        'status' => 2, 'project_id' => 0, 'show_quantity_as' => 1, 'currency' => 1,
        'subtotal' => 100, 'total' => 100, 'adjustment' => 0,
        'discount_percent' => 0, 'discount_total' => 0, 'discount_type' => '',
        'terms' => '', 'billing_street' => '', 'billing_city' => '', 'billing_state' => '',
        'billing_zip' => '', 'billing_country' => 0, 'shipping_street' => '',
        'shipping_city' => '', 'shipping_state' => '', 'shipping_zip' => '',
        'shipping_country' => 0, 'include_shipping' => 0, 'show_shipping_on_invoice' => 0,
        'clientnote' => '', 'allowed_payment_modes' => serialize([]), 'items' => [],
        'cancel_overdue_reminders' => 0,
    ], $changes);
}

function newCron(array $rows): RecurrenceTestCron
{
    global $testOptions, $testLogs, $testHooks;
    $testOptions = [
        'invoice_auto_operations_hour' => 9,
        'new_recurring_invoice_action' => 'generate_and_send',
        'invoices_create_invoice_from_recurring_only_on_paid_invoices' => 0,
        'next_invoice_number' => 100,
        'invoice_due_after' => 7,
    ];
    $testLogs = [];
    $testHooks = new RecurrenceTestHooks();
    return new RecurrenceTestCron($rows);
}

$checks = 0;
function same($expected, $actual, string $context): void
{
    global $checks;
    $checks++;
    if ($expected !== $actual) {
        throw new RuntimeException($context . ': expected ' . var_export($expected, true) . ', got ' . var_export($actual, true));
    }
}

// A currently due series must persist its calendar date and not repeat the same
// period on a second run. A backlog can legitimately generate another period.
$currentMonth = new DateTimeImmutable('first day of this month');
$previousMonth = $currentMonth->modify('-1 month')->format('Y-m-d');
$currentIssue = $currentMonth->format('Y-m-d');
$cron = newCron([
    invoiceFixture(1, ['date' => '2025-02-30']),
    invoiceFixture(2, ['last_recurring_date' => '2025-02-30']),
    invoiceFixture(4, ['custom_recurring' => 1, 'recurring_type' => null]),
    invoiceFixture(3, ['date' => $previousMonth, 'duedate' => null]),
]);
$cron->recurring_invoices();
same(3, count($testLogs), 'invalid original date, last date and null custom unit logged independently');
same(1, count($cron->invoices_model->created), 'invalid records do not block the valid invoice');
same($currentIssue, array_values($cron->invoices_model->created)[0]['date'], 'currently due issue date');
same($currentMonth->modify('+7 days')->format('Y-m-d'), array_values($cron->invoices_model->created)[0]['duedate'], 'configured due-after fallback');
same(null, $cron->db->rows[1]['last_recurring_date'], 'invalid original does not advance');
same('2025-02-30', $cron->db->rows[2]['last_recurring_date'], 'invalid last date does not advance');
same(null, $cron->db->rows[4]['last_recurring_date'], 'null custom unit does not advance');
same($currentIssue, $cron->db->rows[3]['last_recurring_date'], 'calendar issue date persisted');
same(1, $cron->db->rows[3]['total_cycles'], 'successful generation advances one cycle');
same(1, count($cron->invoices_model->sent), 'only the generated invoice is sent');
same(1, count($testHooks->actions), 'only successful generation emits its hook');
$cron->recurring_invoices();
same(1, count($cron->invoices_model->created), 'same-day rerun does not duplicate a current period');
same(1, $cron->db->rows[3]['total_cycles'], 'same-day rerun does not increment cycles');
same(1, count($cron->invoices_model->sent), 'same-day rerun does not resend');

// An overdue historical fixture makes the two contractual modes deterministic,
// independently of the actual day this test runs. Each gets one catch-up cycle.
$cron = newCron([
    invoiceFixture(10, ['date' => '2001-01-26', 'duedate' => '2001-02-05']),
    invoiceFixture(11, ['date' => '2001-01-26', 'duedate' => '2001-02-05', 'recurring_due_mode' => 'days']),
    invoiceFixture(12, ['date' => '2001-01-26', 'duedate' => '2001-02-05', 'recurring_due_mode' => 'fixed_day']),
    invoiceFixture(13, ['date' => '2001-01-31', 'duedate' => '2001-01-31', 'recurring_due_mode' => 'fixed_day']),
    invoiceFixture(14, ['date' => '2001-01-26', 'duedate' => '2001-02-30']),
    invoiceFixture(15, ['date' => '2001-01-26', 'duedate' => '2001-02-05', 'recurring_due_mode' => 'fixed_day', 'custom_recurring' => 1, 'recurring_type' => 'WEEK']),
]);
$cron->recurring_invoices();
$created = array_values($cron->invoices_model->created);
same(5, count($created), 'invalid due date skips only that invoice');
same('2001-03-08', $created[0]['duedate'], 'missing mode preserves ten-day original terms');
same('2001-03-08', $created[1]['duedate'], 'explicit days mode preserves original terms');
same('2001-03-05', $created[2]['duedate'], 'fixed mode retains next-month fifth');
same('2001-02-28', $created[3]['date'], 'monthly issue clamps January 31');
same('2001-02-28', $created[3]['duedate'], 'fixed due day 31 clamps independently');
same('2001-02-02', $created[4]['date'], 'custom weekly issue unchanged');
same('2001-02-12', $created[4]['duedate'], 'weekly fixed setting falls back to day interval');
same(null, $cron->db->rows[14]['last_recurring_date'], 'invalid due date never advances source');
same(0, $cron->db->rows[14]['total_cycles'], 'invalid due date never increments cycles');
same(1, count($testLogs), 'invalid due date produces an error log');

// Persistence and external effects must only follow successful invoice creation.
$cron = newCron([invoiceFixture(20, ['date' => $previousMonth, 'duedate' => null])]);
$cron->invoices_model->failAdd = true;
$cron->recurring_invoices();
same(null, $cron->db->rows[20]['last_recurring_date'], 'failed add does not advance recurrence');
same(0, $cron->db->rows[20]['total_cycles'], 'failed add does not consume a cycle');
same([], $cron->db->writes, 'failed add causes no database writes');
same([], $cron->invoices_model->sent, 'failed add sends no invoice');
same([], $testHooks->actions, 'failed add emits no creation hook');

restore_error_handler();
echo 'Recurring invoice CRON: ' . $checks . ' checks passed.' . PHP_EOL;
