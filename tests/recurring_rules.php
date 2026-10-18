<?php
declare(strict_types=1);
date_default_timezone_set('UTC');
error_reporting(E_ALL);
set_error_handler(function ($severity, $message, $file, $line) { if (error_reporting() & $severity) { throw new ErrorException($message, 0, $severity, $file, $line); } return false; });
define('BASEPATH', dirname(__DIR__) . '/system/');
define('APPPATH', dirname(__DIR__) . '/application/');
define('APP_MODULES_PATH', dirname(__DIR__) . '/modules/');
require APPPATH . 'hooks/App_Autoloader.php';
App_Autoloader::register();
use app\services\RecurringRulePolicy as Policy;
use app\services\RecurringInvoiceLock;

$checks = 0;
function check($condition, string $message): void { global $checks; $checks++; if (!$condition) { throw new RuntimeException($message); } }
function fails(callable $callback, string $message): void { try { $callback(); } catch (InvalidArgumentException | RuntimeException $e) { check(true, $message); return; } throw new Exception('Expected rejection: ' . $message); }
$permissions = ['view' => true, 'view_own' => true, 'edit' => true, 'create' => true];
$staff = 7;
$options = ['allow_staff_view_invoices_assigned' => '1', 'invoices_create_invoice_from_recurring_only_on_paid_invoices' => '0'];
$taxes = [];
$logs = [];
function db_prefix(): string { return 'test_'; }
function get_staff_user_id(): int { global $staff; return $staff; }
function staff_can($permission, $feature): bool { global $permissions; return $permissions[$permission] ?? false; }
function get_option($name) { global $options; return $options[$name] ?? ''; }
function clear_textarea_breaks($value): string { return str_replace('<br />', '', (string) $value); }
function get_invoice_item_taxes($id): array { global $taxes; return $taxes[$id] ?? []; }
function log_message($level, $message): void { global $logs; $logs[] = $message; }
function hooks() { throw new RuntimeException('Forbidden: financial hook invoked'); }
function is_customer_admin($id): bool { return false; }

class App_Model { public $db; public $load; public $invoices_model; public $clients_model; public $invoice_items_model; public $currencies_model; }
require APPPATH . 'models/Recurring_rules_model.php';
final class Rows
{
    public function __construct(private array $rows) {}
    public function row_array(): array { return $this->rows[0] ?? []; }
    public function row(): object { return (object) ($this->rows[0] ?? []); }
    public function result_array(): array { return $this->rows; }
}
final class MemoryDb
{
    public array $tables = [];
    public array $writes = [];
    public bool $lockAvailable = true;
    public bool $released = false;
    public string $storageEngine = 'InnoDB';
    public ?string $failTable = null;
    private array $filters = [];
    private ?string $fromTable = null;
    private ?array $backup = null;
    private int $lastId = 0;
    private int $affected = 0;
    public function where($field, $value = null): self { $this->filters[] = [$field, $value]; return $this; }
    public function field_exists($field, $table): bool { foreach ($this->tables[$table] ?? [] as $row) { if (array_key_exists($field, $row)) return true; } return false; }
    public function select($fields): self { return $this; }
    public function order_by($field, $direction): self { return $this; }
    public function limit($limit, $offset = 0): self { return $this; }
    public function from($table): self { $this->fromTable = $table; return $this; }
    public function get($table = null): Rows
    {
        $table ??= $this->fromTable;
        $out = array_values(array_filter($this->tables[$table] ?? [], fn($row) => $this->matches($row)));
        $this->filters = []; $this->fromTable = null;
        return new Rows($out);
    }
    private function matches(array $row): bool
    {
        foreach ($this->filters as [$field, $value]) {
            if (str_ends_with($field, ' <=')) { if ($row[substr($field, 0, -3)] > $value) return false; }
            elseif (str_ends_with($field, ' >')) { if ($row[substr($field, 0, -2)] <= $value) return false; }
            elseif (($row[$field] ?? null) != $value) return false;
        }
        return true;
    }
    public function insert($table, array $data): bool
    {
        if ($this->failTable === $table) { throw new RuntimeException('Injected write failure'); }
        if ($table === 'test_recurring_rule_batches') {
            $data += ['result_json' => null, 'applied_at' => null];
            $key = $data['token_hash'];
        } else {
            $data['id'] = max(array_merge([0], array_column($this->tables[$table] ?? [], 'id'))) + 1;
            $this->lastId = $data['id']; $key = $data['id'];
        }
        if ($table === 'test_recurring_rule_runs') {
            foreach ($this->tables[$table] ?? [] as $row) {
                if (($row['rule_id'] === $data['rule_id'] && $row['cycle_date'] === $data['cycle_date']) || $row['invoice_id'] === $data['invoice_id']) {
                    throw new RuntimeException('Unique constraint');
                }
            }
        }
        $this->tables[$table][$key] = $data; $this->writes[] = [$table, $data]; return true;
    }
    public function update($table, array $data): bool
    {
        if ($this->failTable === $table) throw new RuntimeException('Injected write failure');
        $this->affected = 0;
        foreach ($this->tables[$table] ?? [] as $key => $row) {
            if ($this->matches($row)) { $this->tables[$table][$key] = array_replace($row, $data); $this->affected++; }
        }
        $this->filters = []; $this->writes[] = [$table, $data]; return true;
    }
    public function insert_id(): int { return $this->lastId; }
    public function affected_rows(): int { return $this->affected; }
    public function query($sql, array $args): Rows
    {
        if (str_contains($sql, 'information_schema.TABLES')) return new Rows(array_map(fn($table) => ['TABLE_NAME' => $table, 'ENGINE' => $this->storageEngine], $args));
        if (str_contains($sql, 'GET_LOCK')) return new Rows([['acquired' => $this->lockAvailable ? 1 : 0]]);
        if (str_contains($sql, 'RELEASE_LOCK')) { $this->released = true; return new Rows([['released' => 1]]); }
        if (preg_match('/SELECT (?:\*|id) FROM `([^`]+)` WHERE (id|token_hash) = \? FOR UPDATE/', $sql, $matches)) {
            return $this->where($matches[2], $args[0])->get($matches[1]);
        }
        throw new RuntimeException('Unexpected SQL: ' . $sql);
    }
    public function trans_begin(): bool { $this->backup = [$this->tables, $this->writes]; return true; }
    public function trans_status(): bool { return true; }
    public function trans_commit(): bool { $this->backup = null; return true; }
    public function trans_rollback(): bool { if ($this->backup) [$this->tables, $this->writes] = $this->backup; $this->backup = null; return true; }
}
final class RulesHarness extends Recurring_rules_model
{
    public function __construct(MemoryDb $db)
    {
        $this->db = $db;
        $this->load = new class { public function model($name): void {} };
        $this->invoices_model = new class($db) {
            public int $draftCalls = 0;
            public function __construct(private MemoryDb $db) {}
            public function get($id) { return isset($this->db->tables['test_invoices'][$id]) ? (object) $this->db->tables['test_invoices'][$id] : false; }
            public function add_recurring_rule_draft(array $data): int { $this->draftCalls++; $this->db->insert('test_invoices', $data + ['status' => 6]); return $this->db->insert_id(); }
            public function update(...$args) { throw new RuntimeException('Forbidden: invoice update'); }
        };
    }
}
function fixtureRule(int $id = 1): array
{
    return ['id' => $id, 'emitter_key' => 'legacy', 'source_invoice_id' => null, 'clientid' => 10, 'addedfrom' => 7, 'sale_agent' => 7,
        'title' => 'Teste sintético', 'state' => 'active', 'calendar_group' => 'dia 31', 'integration_group' => 'manual',
        'anchor_date' => '2024-01-31', 'anchor_due' => '2024-02-05', 'interval_count' => 1, 'interval_unit' => 'MONTH', 'due_mode' => 'days',
        'next_issue_date' => '2024-02-29', 'cycles_limit' => 0, 'cycles_emitted' => 0, 'version' => 1,
        'template_json' => json_encode(['clientid' => 10, 'subtotal' => '25.00', 'total' => '25.00', 'newitems' => [['description' => 'Serviço', 'qty' => '1.00', 'rate' => '25.00']]]),
        'created_at' => '2024-01-01 00:00:00', 'updated_at' => '2024-01-01 00:00:00'];
}
function fixtureInvoice(int $id = 8): array
{
    return ['id' => $id, 'recurring' => 1, 'is_recurring_from' => null, 'status' => 2, 'cycles' => 12, 'total_cycles' => 1,
        'date' => '2024-01-31', 'duedate' => '2024-02-05', 'last_recurring_date' => '2024-02-29', 'custom_recurring' => 0,
        'recurring_type' => null, 'recurring_due_mode' => 'days', 'addedfrom' => 7, 'sale_agent' => 7, 'clientid' => 10,
        'total' => '25.00', 'subtotal' => '25.00', 'currency' => 1,
        'items' => [['id' => 91, 'description' => 'Serviço', 'long_description' => '', 'qty' => '1.00', 'unit' => '', 'rate' => '25.00', 'item_order' => 1]]];
}
function fixture(): array
{
    $db = new MemoryDb(); $db->tables = ['test_recurring_rules' => [1 => fixtureRule(), 2 => fixtureRule(2)],
        'test_invoices' => [8 => fixtureInvoice()], 'test_clients' => [10 => ['userid' => 10, 'active' => 1]],
        'test_itemable' => [91 => fixtureInvoice()['items'][0] + ['rel_id' => 8, 'rel_type' => 'invoice']], 'test_recurring_rule_batches' => [], 'test_recurring_rule_audit' => [], 'test_recurring_rule_runs' => []];
    return [$db, new RulesHarness($db)];
}

// Selection, civil dates, legacy policy, boundaries and explicit fixed-day opt-in.
foreach ([[], [0], ['1 OR 1=1'], [1, 1], array_fill(0, 101, 1), [2147483648], [[]]] as $ids) fails(fn() => Policy::ids($ids), 'invalid selection');
check(Policy::ids(['2', 1]) === [1, 2], 'canonical selection');
foreach (['2023-02-29', '2024-13-01', '2024-01-01 garbage', '1899-12-31', '2200-01-01'] as $date) fails(fn() => Policy::date($date), 'invalid date');
foreach ([28, 29, 30, 31] as $day) {
    $rule = fixtureRule(); $rule['anchor_due'] = '2024-01-' . $day; $rule['anchor_date'] = '2024-01-01'; $rule['due_mode'] = 'fixed_day'; $rule['next_issue_date'] = '2024-02-01';
    $dates = Policy::upcoming($rule);
    check($dates[0]['due'] === '2024-02-' . min(29, $day), 'leap clamp');
    check($dates[1]['due'] === '2024-03-' . $day, 'restore due anchor');
}
$r = fixtureRule(); $r['due_mode'] = 'fixed_day';
check(Policy::upcoming($r)[0] === ['issue' => '2024-02-29', 'due' => '2024-03-05'], 'independent due anchor');
check(Policy::upcoming(fixtureRule())[0]['due'] === '2024-03-05', 'legacy day terms');
$r['state'] = 'paused'; $resumed = Policy::change($r, 'resume', null, '2024-04-10');
check($resumed['next_issue_date'] === '2024-04-30', 'resume skips missed periods');
foreach ([['is_recurring_from' => 4], ['status' => 5], ['status' => 6], ['recurring' => 0], ['total_cycles' => 12]] as $change) fails(fn() => Policy::legacy(array_replace(fixtureInvoice(), $change)), 'legacy eligibility');
fails(fn() => Policy::change(array_replace(fixtureRule(), ['state' => 'ended']), 'resume', null, '2024-02-01'), 'ended terminal');
fails(fn() => Policy::schedule(array_replace(fixtureRule(), ['interval_unit' => 'DAY', 'due_mode' => 'fixed_day'])), 'fixed mode monthly/yearly only');

// No writes during pure preview except the durable approval token.
[$db, $model] = fixture(); $preview = $model->preview('rules', [1, 2], 'due_mode', 'fixed_day');
check(strlen($preview['token']) === 64, 'durable preview'); check(count($db->writes) === 1 && $db->writes[0][0] === 'test_recurring_rule_batches', 'preview does not mutate rules');
check($model->invoices_model->draftCalls === 0, 'preview does not create invoice');
$beforeInvoices = $db->tables['test_invoices'];
$result = $model->apply($preview['token'], true);
check($db->tables['test_recurring_rules'][1]['due_mode'] === 'fixed_day', 'bulk applies future policy');
check(count($db->tables['test_recurring_rule_audit']) === 2, 'one audit per rule');
check(json_decode($db->tables['test_recurring_rule_audit'][1]['after_json'], true)['version'] === 2, 'audit reflects persisted version after mutation');
check($model->apply($preview['token'], true) === $result && count($db->tables['test_recurring_rule_audit']) === 2, 'idempotent result and audit');
check($db->tables['test_invoices'] === $beforeInvoices && $model->invoices_model->draftCalls === 0, 'bulk preserves all invoices, no generation');
check($db->released, 'lock released');

// Revalidate actor, permission, visibility, state, expiration and snapshot at application time.
[$db, $model] = fixture(); $p = $model->preview('rules', [1], 'pause', null); $staff = 99; fails(fn() => $model->apply($p['token'], true), 'actor binding'); $staff = 7;
$permissions['edit'] = false; fails(fn() => $model->apply($p['token'], true), 'edit denied'); $permissions['edit'] = true;
$permissions['view'] = false; $permissions['view_own'] = false; $options['allow_staff_view_invoices_assigned'] = '0'; fails(fn() => $model->apply($p['token'], true), 'visibility rechecked');
$permissions['view'] = true; $permissions['view_own'] = true; $options['allow_staff_view_invoices_assigned'] = '1';
fails(fn() => $model->apply($p['token'], false), 'explicit confirmation');
$db->tables['test_recurring_rules'][1]['version']++; fails(fn() => $model->apply($p['token'], true), 'concurrent version');
check($db->tables['test_recurring_rules'][1]['state'] === 'active' && !$db->tables['test_recurring_rule_audit'], 'conflict has no writes');
[$db, $model] = fixture(); $p = $model->preview('rules', [1], 'pause', null); $db->tables['test_recurring_rule_batches'][hash('sha256', $p['token'])]['expires_at'] = '2000-01-01 00:00:00'; fails(fn() => $model->apply($p['token'], true), 'expired preview');
[$db, $model] = fixture(); $p = $model->preview('rules', [1, 999], 'pause', null); check($p['token'] === null && !$db->writes, 'ineligible selection never produces applicable partial batch');
[$db, $model] = fixture(); $db->tables['test_recurring_rules'][1]['emitter_key'] = 'another-company'; $p = $model->preview('rules', [1], 'pause', null); check($p['token'] === null, 'nonlegacy emitter fail-closed');

// Atomic rollback, lock contention and migration preserving emitted documents.
[$db, $model] = fixture(); $p = $model->preview('rules', [1, 2], 'pause', null); $snapshot = $db->tables; $db->failTable = 'test_recurring_rule_audit'; fails(fn() => $model->apply($p['token'], true), 'injected audit failure'); check($db->tables === $snapshot, 'atomic full rollback');
$db->failTable = null; $db->lockAvailable = false; fails(fn() => $model->apply($p['token'], true), 'shared lock busy'); check($db->tables === $snapshot, 'busy lock has no writes');
[$db, $model] = fixture(); $p = $model->preview('legacy', [8], 'due_mode', 'fixed_day'); $model->apply($p['token'], true); check($db->tables['test_invoices'][8] === array_replace(fixtureInvoice(), ['recurring_due_mode' => 'fixed_day']), 'legacy bulk changes metadata only');
[$db, $model] = fixture(); $p = $model->preview('legacy', [8], 'migrate', null); $r = $model->apply($p['token'], true); $rule = $db->tables['test_recurring_rules'][$r['rule_ids'][0]];
check($rule['state'] === 'paused' && $rule['integration_group'] === 'manual', 'migration is paused and manual');
check($db->tables['test_invoices'][8] === array_replace(fixtureInvoice(), ['recurring' => 0]), 'migration stops only legacy schedule');
check($rule['cycles_emitted'] === 1 && $rule['cycles_limit'] === 12 && $rule['next_issue_date'] === '2024-03-31', 'migration retains progress/anchor');
check($model->apply($p['token'], true) === $r, 'migration retry does not duplicate rule');
[$db, $model] = fixture(); $p = $model->preview('legacy', [8], 'migrate', null); $taxes[91] = [['taxname' => 'IVA|5.00']]; fails(fn() => $model->apply($p['token'], true), 'tax snapshot changes invalidate migration'); $taxes = [];

// Exactly one local draft per successful rule/cycle; failure rolls back local state.
[$db, $model] = fixture(); $db->tables['test_recurring_rules'] = [1 => fixtureRule()];
check(RecurringInvoiceLock::run($db, fn() => $model->process_due_locked('2024-02-29')) === 1, 'draft generated');
check(count($db->tables['test_recurring_rule_runs']) === 1 && $db->tables['test_recurring_rules'][1]['cycles_emitted'] === 1, 'cycle recorded');
check(RecurringInvoiceLock::run($db, fn() => $model->process_due_locked('2024-02-29')) === 0, 'same-date cron retry creates no duplicate');
check(count($db->tables['test_invoices']) === 2, 'one new draft only');
[$db, $model] = fixture(); $db->tables['test_recurring_rules'] = [1 => fixtureRule()]; $before = $db->tables; $db->failTable = 'test_recurring_rule_runs';
check(RecurringInvoiceLock::run($db, fn() => $model->process_due_locked('2024-02-29')) === 0, 'failed cycle isolated'); check($db->tables === $before, 'invoice/rule/run rollback');
[$db, $model] = fixture(); $db->tables['test_recurring_rules'][1]['state'] = 'paused'; $db->tables['test_recurring_rules'][2]['emitter_key'] = 'another-company'; check($model->process_due_locked('2024-02-29') === 0, 'paused and unsupported emitters do not generate');
[$db, $model] = fixture(); $db->tables['test_recurring_rules'] = [1 => array_replace(fixtureRule(), ['cycles_limit' => 1])]; $model->process_due_locked('2024-02-29'); check($db->tables['test_recurring_rules'][1]['state'] === 'ended', 'finite cycles end');
[$db, $model] = fixture(); $p = $model->preview('rules', [1], 'pause', null); $db->storageEngine = 'MyISAM'; fails(fn() => $model->apply($p['token'], true), 'nontransactional engine rejected');
[$db, $model] = fixture(); $db->tables['test_invoices'][8]['issuer_id'] = 42; $p = $model->preview('legacy', [8], 'migrate', null); check($p['token'] === null, 'explicit invoice issuer is not silently relabeled legacy');

// Scope restriction also applies to read/list paths, before exposing invoice data.
[$db, $model] = fixture();
check(count($model->listing('legacy')['rows']) === 1, 'schema before issuer column remains legacy');
$db->tables['test_invoices'][8]['issuer_id'] = 42;
check(!$model->listing('legacy')['rows'], 'explicit issuer excluded from legacy listing');
$db->tables['test_invoices'][8]['issuer_id'] = null;
check(count($model->listing('legacy')['rows']) === 1, 'null issuer remains visible legacy');
$db->tables['test_recurring_rules'][1]['emitter_key'] = 'company-42';
check(count($model->listing('rules')['rows']) === 1, 'explicit issuer rule excluded from listing');
fails(fn() => $model->get_visible(1), 'explicit issuer rule is not readable by direct ID');

// Reuse the real customer/catalogue records; submitted prices/totals are ignored.
[$db, $model] = fixture();
$model->clients_model = new class { public function get($id) { return (object) ['userid' => $id, 'active' => 1, 'default_currency' => 1]; } };
$model->currencies_model = new class { public function get_base_currency() { return (object) ['id' => 1]; } };
$model->invoice_items_model = new class { public function get($id) { return (object) ['description' => 'Catálogo', 'rate' => '12.50', 'taxname' => 'IVA', 'taxrate' => '10.00', 'taxname_2' => null]; } };
$input = ['request_key' => str_repeat('c', 64), 'title' => 'Nova sem fatura', 'clientid' => 10, 'emitter_key' => 'legacy', 'anchor_date' => date('Y-m-d'), 'anchor_due' => date('Y-m-d'),
    'interval_count' => 1, 'interval_unit' => 'MONTH', 'due_mode' => 'days', 'items' => [['id' => 4, 'qty' => '2.00', 'rate' => '0.01']], 'total' => '0.01'];
$id = $model->create_from_catalogue($input); $created = $db->tables['test_recurring_rules'][$id]; $template = json_decode($created['template_json'], true);
check($created['source_invoice_id'] === null && $created['state'] === 'paused', 'independent creation needs no invoice and starts paused');
check($template['subtotal'] === '25.00' && $template['total_tax'] === '2.50' && $template['total'] === '27.50', 'authoritative catalogue price, fixed-point totals/taxes');
check(count($db->tables['test_invoices']) === 1 && $model->invoices_model->draftCalls === 0, 'creation does not issue invoice');
check($model->create_from_catalogue($input) === $id && count($db->tables['test_recurring_rule_audit']) === 1, 'repeated create submits the same rule without duplicate audit');
fails(fn() => $model->create_from_catalogue(array_replace($input, ['title' => 'Changed request'])), 'create key payload is immutable');
fails(fn() => $model->create_from_catalogue(array_replace($input, ['emitter_key' => 'company-42'])), 'unsupported standalone emitter');
fails(fn() => $model->create_from_catalogue(array_replace($input, ['items' => [['id' => 4, 'qty' => '-1']]])), 'negative quantity');
fails(fn() => $model->create_from_catalogue(array_replace($input, ['items' => [['id' => 4, 'qty' => []]]])), 'nested quantity');
fails(fn() => Policy::schedule(array_replace(fixtureRule(), ['anchor_date' => []])), 'nested date');
// Two separate half-cent taxes follow core's aggregate rounding, not per-group rounding.
$model->invoice_items_model = new class { public function get($id) { return (object) ['description' => 'Frações', 'rate' => '0.05', 'taxname' => 'A', 'taxrate' => '10.00', 'taxname_2' => 'B', 'taxrate_2' => '10.00']; } };
$fractionInput = array_replace($input, ['request_key' => str_repeat('d', 64), 'items' => [['id' => 4, 'qty' => '1.00']]]);
$id = $model->create_from_catalogue($fractionInput); $template = json_decode($db->tables['test_recurring_rules'][$id]['template_json'], true);
check($template['subtotal'] === '0.05' && $template['total_tax'] === '0.01' && $template['total'] === '0.06', 'two half-cent tax groups sum before final rounding');
check(count($template['newitems'][0]['taxname']) === 2, 'both catalogue taxes preserved');
$model->invoice_items_model = new class { public function get($id) { return (object) ['description' => 'Quantidade fracionada', 'rate' => '0.33', 'taxname' => 'A', 'taxrate' => '10.00', 'taxname_2' => null]; } };
$id = $model->create_from_catalogue(array_replace($fractionInput, ['request_key' => str_repeat('e', 64), 'items' => [['id' => 4, 'qty' => '0.30']]]));
$template = json_decode($db->tables['test_recurring_rules'][$id]['template_json'], true);
check($template['subtotal'] === '0.10' && $template['total_tax'] === '0.01' && $template['total'] === '0.11', 'fractional quantities round item before tax as core UI');
echo "Independent recurring rules: $checks checks passed (real policy/model, in-memory database and invoice writer; no network).\n";
