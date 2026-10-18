<?php
declare(strict_types=1);
error_reporting(E_ALL);
set_error_handler(function ($severity, $message, $file, $line) { if (error_reporting() & $severity) throw new ErrorException($message, 0, $severity, $file, $line); return false; });
define('BASEPATH', dirname(__DIR__) . '/system/'); define('APPPATH', dirname(__DIR__) . '/application/'); define('APP_MODULES_PATH', dirname(__DIR__) . '/modules/'); define('CRON', true);
require APPPATH . 'hooks/App_Autoloader.php'; App_Autoloader::register();
class App_Model { public $db; }
require APPPATH . 'models/Invoices_model.php';
$checks = 0; $items = []; $taxCalls = []; $throwItem = false;
function check($v, $message): void { global $checks; $checks++; if (!$v) throw new RuntimeException($message); }
function db_prefix(): string { return 'test_'; }
function get_option($name) { return ['invoice_prefix' => 'INV-', 'invoice_number_format' => '1'][$name] ?? ''; }
function app_generate_hash(): string { return str_repeat('a', 32); }
function hooks() { throw new RuntimeException('Unexpected external hook'); }
function update_invoice_status($id): void { throw new RuntimeException('Unexpected status side-effect'); }
function handle_tags_save(...$args): void { throw new RuntimeException('Unexpected tag side-effect'); }
function add_new_sales_item_post($item, $id, $type): int { throw new RuntimeException('Unexpected item formatting hook path'); }
function _maybe_insert_post_item_tax($id, $item, $invoice, $type): void { global $taxCalls; $taxCalls[] = $item['taxname']; }
function update_sales_total_tax_column($id, $type, $table): void {}
final class DraftDb
{
    public array $rows = []; private int $id = 0;
    public function table_exists($table): bool { return false; }
    public function insert($table, $data): bool {
        global $items, $throwItem;
        if ($table === 'test_itemable') { if ($throwItem) throw new RuntimeException('Injected item failure'); $this->id++; $items[] = $data; return true; }
        if ($table !== 'test_invoices') throw new RuntimeException('Unexpected table'); $this->id++; $this->rows[$this->id] = $data; return true;
    }
    public function insert_id(): int { return $this->id; }
    public function where($field, $value): self { return $this; }
    public function update($table, $data): bool { if ($table !== 'test_invoices') throw new RuntimeException('Unexpected update'); $this->rows[$this->id] = array_replace($this->rows[$this->id], $data); return true; }
}
final class InvoiceWriterHarness extends Invoices_model
{
    public array $activities = [];
    public function __construct() { $this->db = new DraftDb(); }
    public function log_invoice_activity($id, $description = '', $client = false, $additional_data = '') { $this->activities[] = [$id, $description]; }
    public function save_formatted_number($id) { throw new RuntimeException('Unexpected numbering hook'); }
    public function increment_next_number() { throw new RuntimeException('Unexpected invoice numbering increment'); }
}
$writer = new InvoiceWriterHarness();
$template = ['clientid' => 1, 'currency' => 1, 'subtotal' => '20.00', 'total' => '20.00', 'billing_street' => '', 'date' => '2024-02-29', 'duedate' => '2024-03-05',
    'newitems' => [['description' => 'Item', 'long_description' => '', 'qty' => '2.00', 'rate' => '10.00', 'unit' => '', 'order' => 1, 'taxname' => []]],
    'save_and_send' => true, 'status' => 1, 'allowed_payment_modes' => ['asaas'], 'recurring' => 1, 'is_recurring_from' => 88,
    'billed_tasks' => [1], 'billed_expenses' => [2], 'invoices_to_merge' => [3], 'custom_fields' => ['secret' => 'ignored']];
foreach ([42, 0, false, ''] as $issuer) {
    try { $writer->add_recurring_rule_draft($template + ['issuer_id' => $issuer]); throw new LogicException('issuer rejection expected'); }
    catch (DomainException $e) { check(!$writer->db->rows, 'explicit/malformed issuer never silently becomes legacy'); }
}
$id = $writer->add_recurring_rule_draft($template); $saved = $writer->db->rows[$id];
check($saved['status'] === Invoices_model::STATUS_DRAFT, 'draft forced');
check($saved['number'] === Invoices_model::STATUS_DRAFT_NUMBER && $saved['formatted_number'] === 'INV-DRAFT', 'draft number only');
check($saved['allowed_payment_modes'] === serialize([]), 'no external payment modes');
check($saved['recurring'] === 0 && $saved['custom_recurring'] === 0, 'child cannot recur');
check($saved['cancel_overdue_reminders'] === 1, 'reminders blocked');
check(!isset($saved['save_and_send'], $saved['billed_tasks'], $saved['invoices_to_merge'], $saved['is_recurring_from']), 'whitelist drops unsafe side effects');
check($saved['date'] === '2024-02-29' && $saved['duedate'] === '2024-03-05', 'ISO dates preserved without formatter hook');
check(count($items) === 1 && count($taxCalls) === 1 && count($writer->activities) === 1, 'local canonical item/tax/activity paths retained');
$throwItem = true;
try { $writer->add_recurring_rule_draft($template); throw new LogicException('failure expected'); } catch (RuntimeException $e) { check($e->getMessage() === 'Injected item failure', 'failure propagated for rollback'); }
$throwItem = false;
$normalTemplate = $template; unset($normalTemplate['invoices_to_merge'], $normalTemplate['is_recurring_from']);
try { $writer->add($normalTemplate); throw new LogicException('normal hook expected'); } catch (RuntimeException $e) { check($e->getMessage() === 'Unexpected external hook', 'quiet flag restored; standard add still uses hooks'); }
echo "Independent recurring draft writer: $checks checks passed (real Invoices_model::add with local collaborators; no hooks/network/mail).\n";
