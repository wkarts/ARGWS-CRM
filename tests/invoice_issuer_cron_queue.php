<?php
/**
 * Real Cron_model queue selection/deletion and CI3 PDO SQLite query builder.
 * Fixtures are synthetic; model loaders and invoice/estimate transports are doubles.
 * No application bootstrap, external mail transport, credentials or production data.
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
require BASEPATH . 'database/DB.php';

class App_Model { public $db; public $load; public $invoices_model; public $estimates_model; }
require APPPATH . 'models/Cron_model.php';

$checks = 0;
function check($condition, string $message): void { global $checks; ++$checks; if (!$condition) { throw new RuntimeException($message); } }
function db_prefix(): string { return 'test_'; }
function is_php($version): bool { return version_compare(PHP_VERSION, $version, '>='); }
function log_message($level, $message): void {}
function show_error($message): void { throw new RuntimeException($message); }

final class IssuerQueueTransport
{
    public array $sent = [];
    public function send_invoice_to_client($id, $template, $attachPdf, $cc): void
    {
        $this->record($id, $template, $attachPdf, $cc);
    }
    public function send_estimate_to_client($id, $template, $attachPdf, $cc): void
    {
        $this->record($id, $template, $attachPdf, $cc);
    }
    private function record($id, $template, $attachPdf, $cc): void
    {
        $this->sent[] = ['rel_id' => $id, 'template' => $template, 'attach_pdf' => $attachPdf,
            'cc' => $cc, 'contacts' => $GLOBALS['scheduled_email_contacts'] ?? null];
    }
    public function get($id) { throw new RuntimeException('Queue must not hydrate an invoice/estimate'); }
}

final class IssuerQueueHarness extends Cron_model
{
    public function __construct($db)
    {
        $this->db = $db;
        $this->invoices_model = new IssuerQueueTransport();
        $this->estimates_model = new IssuerQueueTransport();
        $this->load = new class($db) {
            private $db;
            public array $loaded = [];
            public function __construct($db) { $this->db = $db; }
            public function model($name): void
            {
                check(in_array($name, ['invoices_model', 'estimates_model'], true), 'Only expected models loaded');
                check((bool) preg_match('/^SELECT .*scheduled_emails/s', $this->db->last_query()),
                    'Queue selection is complete before loading any model');
                $this->loaded[] = $name;
            }
        };
    }
}

function fixture(bool $withScope): array
{
    $db = DB(['dbdriver' => 'pdo', 'subdriver' => 'sqlite', 'dsn' => 'sqlite::memory:',
        'dbprefix' => db_prefix(), 'db_debug' => false, 'pconnect' => false, 'save_queries' => true], true);
    $db->conn_id->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $db->query('CREATE TABLE test_invoices (id INTEGER PRIMARY KEY' . ($withScope ? ', issuer_id INTEGER NULL' : '') . ')');
    $db->query('CREATE TABLE test_scheduled_emails (id INTEGER PRIMARY KEY, rel_type TEXT NULL,
        rel_id INTEGER NULL, scheduled_at TEXT, contacts TEXT, template TEXT, attach_pdf INTEGER, cc TEXT)');
    foreach ([1 => null, 2 => 101, 3 => 202, 4 => 0] as $id => $issuer) {
        $row = ['id' => $id];
        if ($withScope) { $row['issuer_id'] = $issuer; }
        check($db->insert('invoices', $row), 'Synthetic invoice inserted');
    }
    $past = date('Y-m-d H:i:s', strtotime('-1 day'));
    $future = date('Y-m-d H:i:s', strtotime('+1 day'));
    $queue = [
        11 => ['invoice', 1, $past],   // Legacy NULL, eligible.
        12 => ['invoice', 2, $past],   // Issuer X, untouched after migration.
        13 => ['invoice', 3, $past],   // Issuer Y, untouched after migration.
        14 => ['invoice', 4, $past],   // Malformed issuer 0, not legacy.
        15 => ['invoice', 999, $past], // Orphan, not legacy.
        16 => ['invoice', 0, $past],   // Invalid invoice reference.
        17 => ['estimate', 2, $past],  // Collides with issuer X invoice ID, still eligible.
        18 => ['other', 999, $past],   // Preserve existing handling of other types.
        19 => [null, 999, $past],      // Preserve SQL NULL type handling.
        20 => ['invoice', 1, $future], // Never bypass the schedule cutoff.
        21 => ['estimate', 8, $future],
        22 => ['invoice', null, $past],
    ];
    foreach ($queue as $id => [$type, $relId, $scheduledAt]) {
        check($db->insert('scheduled_emails', ['id' => $id, 'rel_type' => $type, 'rel_id' => $relId,
            'scheduled_at' => $scheduledAt, 'contacts' => $id . ',' . ($id + 100),
            'template' => 'template_' . $id, 'attach_pdf' => $id % 2, 'cc' => 'fixture_' . $id . '@example.invalid']),
            'Synthetic queue row inserted');
    }
    $before = $db->get('scheduled_emails')->result_array();
    $db->queries = [];
    return [$db, $before];
}

function queueIds(array $rows): array
{
    $ids = array_map('intval', array_column($rows, 'id'));
    sort($ids);
    return $ids;
}

foreach ([true, false] as $withScope) {
    [$db, $before] = fixture($withScope);
    $cron = new IssuerQueueHarness($db);
    (new ReflectionMethod(Cron_model::class, 'send_scheduled_emails'))->invoke($cron);
    $queries = array_values(array_filter($db->queries, fn ($sql) => preg_match('/^SELECT\b/i', $sql)
        && str_contains($sql, 'test_scheduled_emails')));
    check(count($queries) === 1, 'Exactly one actual queue selection');
    $selection = $queries[0];
    check(str_contains($selection, 'issuer_id IS NULL') === $withScope, 'Schema-aware legacy predicate used only after migration');
    check(str_contains($selection, 'test_invoices') === $withScope, 'Pre-migration selection does not require an invoice reference lookup');
    check($cron->load->loaded === ['invoices_model', 'estimates_model'], 'Existing model load sequence preserved');

    $sentIds = $withScope ? [11, 17] : [11, 12, 13, 14, 15, 16, 17, 22];
    foreach (['invoice' => $cron->invoices_model, 'estimate' => $cron->estimates_model] as $type => $transport) {
        $expected = [];
        foreach ($before as $row) {
            if ($row['rel_type'] === $type && in_array((int) $row['id'], $sentIds, true)) {
                $expected[] = ['rel_id' => $row['rel_id'], 'template' => $row['template'],
                    'attach_pdf' => $row['attach_pdf'], 'cc' => $row['cc'], 'contacts' => explode(',', $row['contacts'])];
            }
        }
        check($transport->sent === $expected, $type . ': only allowed queue items reach the transport, with unchanged arguments and contacts');
    }

    $remaining = $db->get('scheduled_emails')->result_array();
    $expectedRemaining = $withScope ? [12, 13, 14, 15, 16, 20, 21, 22] : [20, 21];
    check(queueIds($remaining) === $expectedRemaining, 'Only eligible scheduled entries deleted; blocked and future entries remain');
    $preserved = array_values(array_filter($before, fn ($row) => in_array((int) $row['id'], $expectedRemaining, true)));
    check($remaining === $preserved, 'Blocked and future queue entries remain byte-for-byte unchanged');
    check(!isset($GLOBALS['scheduled_email_contacts']), 'Contact dispatch globals cleaned after execution');

    $deletions = array_values(array_filter($db->queries, fn ($sql) => preg_match('/^DELETE\b/i', $sql)));
    $expectedDeleted = $withScope ? [11, 17, 18, 19] : [11, 12, 13, 14, 15, 16, 17, 18, 19, 22];
    check(count($deletions) === count($expectedDeleted), 'Exactly the expected number of queue deletions executed');
    foreach ($deletions as $sql) {
        check((bool) preg_match('/WHERE\s+["`]?id["`]?\s*=\s*[\x27"]?(\d+)/i', $sql, $matches), 'Every deletion targets a queue ID');
        check(in_array((int) $matches[1], $expectedDeleted, true), 'No forbidden queue deletion attempted');
    }

    // Rerunning the job cannot dispatch or remove the protected/future remainder.
    $invoiceCalls = $cron->invoices_model->sent;
    $estimateCalls = $cron->estimates_model->sent;
    (new ReflectionMethod(Cron_model::class, 'send_scheduled_emails'))->invoke($cron);
    check($cron->invoices_model->sent === $invoiceCalls && $cron->estimates_model->sent === $estimateCalls,
        'A second run never dispatches protected/future queue entries');
    check($db->get('scheduled_emails')->result_array() === $remaining, 'A second run preserves every protected/future queue entry');
}
echo "Invoice issuer cron queue: {$checks} checks passed (real CI3 SQL/SQLite; loader and transports doubled).\n";
