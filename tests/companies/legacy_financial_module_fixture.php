<?php
/** Real CI3 query builder + MySQL/MariaDB. Only the surrounding CI loader is a double. */
declare(strict_types=1);
date_default_timezone_set('UTC');
error_reporting(E_ALL & ~E_DEPRECATED);
set_error_handler(static function ($level, $message, $file, $line) {
    if (error_reporting() & $level) throw new ErrorException($message, 0, $level, $file, $line);
    return false;
});
define('BASEPATH', dirname(__DIR__, 2) . '/system/');
define('APPPATH', dirname(__DIR__, 2) . '/application/');
define('APP_MODULES_PATH', dirname(__DIR__, 2) . '/modules/');
require BASEPATH . 'database/DB.php';
require APPPATH . 'services/InvoiceIssuerScope.php';
require APPPATH . 'services/ClientIssuerScope.php';
require APPPATH . 'services/CustomerRelationBoundary.php';
function is_php($version): bool { return version_compare(PHP_VERSION, $version, '>='); }
function log_message($level, $message): void {}
function show_error($message): void { throw new RuntimeException('native_query_builder_failed'); }
function db_prefix(): string { return $GLOBALS['moduleReaderPrefix']; }
function &get_instance() { return $GLOBALS['moduleReaderCi']; }
function format_invoice_number($id): string { return 'SYNTHETIC-' . $id; }
function get_option($name) { return $name === 'acc_close_the_books' ? 0 : 1; }
require APPPATH . 'helpers/database_helper.php';
class App_Model {
    public $db;
    public $load;
    public function __construct() {
        $this->db = get_instance()->db;
        $this->load = new class { public function model($name): void { throw new RuntimeException('unexpected_legacy_hydration'); } };
    }
}
require APP_MODULES_PATH . 'finance/models/Finance_model.php';
require APP_MODULES_PATH . 'finance/libraries/Finance_module.php';
require APP_MODULES_PATH . 'accounting/models/Accounting_model.php';

$dsn = (string) getenv('ISSUERS_TEST_DSN');
if (!preg_match('/^mysql:.*(?:^|;)dbname=(issuer_[a-zA-Z0-9_]*module_audit)(?:;|$)/D', $dsn, $dsnMatch)) {
    throw new RuntimeException('Dedicated issuer_*module_audit MySQL database required; fixture uses native tbl prefix.');
}
$GLOBALS['moduleReaderPrefix'] = 'tbl';
$db = DB(['dbdriver' => 'pdo', 'subdriver' => 'mysql', 'dsn' => $dsn,
    'database' => $dsnMatch[1],
    'username' => getenv('ISSUERS_TEST_USER'), 'password' => getenv('ISSUERS_TEST_PASSWORD'),
    'dbprefix' => db_prefix(), 'db_debug' => false, 'pconnect' => false,
    'char_set' => 'utf8mb4', 'dbcollat' => 'utf8mb4_bin', 'save_queries' => true], true);
$db->conn_id->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$GLOBALS['moduleReaderCi'] = (object) ['db' => $db, 'load' => new class { public function database(): void {} }];

function moduleReaderFixture($db, string $prefix): void {
    if (!in_array($prefix, ['tbl', 'module454_'], true)) throw new LogicException('fixture_prefix');
    $definitions = [
        'clients' => 'userid int PRIMARY KEY, company varchar(100) NOT NULL',
        'issuers_registry' => 'id int PRIMARY KEY',
        'issuers_clients' => 'client_id int PRIMARY KEY, owner_issuer_id int NOT NULL',
        'issuers_client_links' => 'client_id int NOT NULL, issuer_id int NOT NULL',
        'invoices' => 'id int PRIMARY KEY, issuer_id int NULL, clientid int NOT NULL, date date NOT NULL, duedate date NOT NULL, total decimal(15,2) NOT NULL, subtotal decimal(15,2) NOT NULL, status int NOT NULL, currency int NOT NULL',
        'expenses' => 'id int PRIMARY KEY, clientid int NOT NULL DEFAULT 0, invoiceid int NOT NULL DEFAULT 0, date date NOT NULL, amount decimal(15,2) NOT NULL, expense_name varchar(100) NOT NULL, note varchar(100) NOT NULL, currency int NOT NULL DEFAULT 1, category int NOT NULL DEFAULT 1, is_bill int NOT NULL DEFAULT 0',
        'expenses_categories' => 'id int PRIMARY KEY, name varchar(100) NOT NULL',
        'invoicepaymentrecords' => 'id int PRIMARY KEY, invoiceid int NOT NULL, amount decimal(15,2) NOT NULL, date date NOT NULL',
        'acc_account_history' => 'id int PRIMARY KEY, rel_id int NOT NULL, rel_type varchar(40) NOT NULL, paid int NOT NULL DEFAULT 0, debit decimal(15,2) NOT NULL DEFAULT 0, credit decimal(15,2) NOT NULL DEFAULT 0',
        'acc_bill_mappings' => 'id int PRIMARY KEY, bill_id int NOT NULL, type varchar(30) NOT NULL, account int NOT NULL, amount decimal(15,2) NOT NULL',
        'files' => 'id int PRIMARY KEY, rel_id int NOT NULL, rel_type varchar(30) NOT NULL, file_name varchar(100) NOT NULL, filetype varchar(100) NOT NULL, staffid int NOT NULL',
    ];
    foreach ($definitions as $table => $columns) {
        $db->query('DROP TABLE IF EXISTS ' . $prefix . $table);
        $db->query('CREATE TABLE ' . $prefix . $table . ' (' . $columns . ') ENGINE=InnoDB');
    }
    $db->data_cache = [];
    foreach ([1, 2, 3] as $issuer) $db->insert($prefix . 'issuers_registry', ['id' => $issuer]);
    foreach ([101 => 'LEGACY PRINCIPAL', 102 => 'PRIVATE PRINCIPAL', 103 => 'PRIVATE A', 104 => 'PRIVATE B'] as $id => $name) {
        $db->insert($prefix . 'clients', ['userid' => $id, 'company' => $name]);
        if ($id !== 101) $db->insert($prefix . 'issuers_clients', ['client_id' => $id, 'owner_issuer_id' => $id - 101]);
    }
    $today = date('Y-m-d');
    foreach ([101 => [null,101,100,2], 102 => [1,102,200,2], 103 => [2,103,300,2], 104 => [3,104,400,2],
        105 => [0,101,500,2], 106 => [null,101,60,1], 107 => [2,103,700,1], 108 => [3,104,800,1], 109 => [1,102,900,1]] as $id => [$issuer,$client,$total,$status]) {
        $db->insert($prefix . 'invoices', ['id'=>$id,'issuer_id'=>$issuer,'clientid'=>$client,'date'=>$today,
            'duedate'=>$today,'total'=>$total,'subtotal'=>$total,'status'=>$status,'currency'=>1]);
        $db->insert($prefix . 'invoicepaymentrecords', ['id'=>$id+200,'invoiceid'=>$id,'amount'=>10,'date'=>$today]);
    }
    $db->insert($prefix . 'invoicepaymentrecords', ['id'=>999,'invoiceid'=>999,'amount'=>999,'date'=>$today]);
    $db->insert($prefix . 'expenses_categories', ['id'=>1,'name'=>'Synthetic category']);
    foreach ([201 => [101,0,10], 202 => [102,0,20], 203 => [103,0,30], 204 => [104,0,40], 205 => [0,103,50],
        206 => [0,999,60], 207 => [0,0,70], 208 => [101,101,80], 209 => [0,105,90]] as $id => [$client,$invoice,$amount]) {
        $db->insert($prefix . 'expenses', ['id'=>$id,'clientid'=>$client,'invoiceid'=>$invoice,'date'=>$today,
            'amount'=>$amount,'expense_name'=>'SYNTHETIC-' . $id,'note'=>'fixture','currency'=>1,'category'=>1,'is_bill'=>0]);
        $db->insert($prefix . 'acc_bill_mappings', ['id'=>$id,'bill_id'=>$id,'type'=>'debit','account'=>1,'amount'=>$amount]);
    }
    // Existing accounting history must remain byte-for-byte unchanged, including foreign native identities.
    $db->insert($prefix . 'acc_account_history', ['id'=>1,'rel_id'=>103,'rel_type'=>'history_preservation','debit'=>73]);
    $db->data_cache = [];
}
function moduleReaderIds(array $rows, string $key = 'id'): array { $ids = array_map('intval', array_column($rows, $key)); sort($ids); return $ids; }
function moduleReaderCapture(callable $f) {
    try { return $f(); }
    catch (Throwable $e) {
        get_instance()->db->reset_query();
        return ['error'=>get_class($e),'message'=>$e->getMessage()];
    }
}
