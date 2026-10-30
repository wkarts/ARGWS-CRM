<?php
/**
 * Executes the six native controller/view selections and the real data_tables_init SQL.
 * Capture stops at the native post-query hook; HTML formatting, HTTP/session ACL and counts
 * are not represented as tested by this suite. No query-builder/SQL predicate is mocked.
 */
declare(strict_types=1);
require __DIR__ . '/legacy_financial_module_fixture.php';
require APPPATH . 'services/utilities/StrClickable.php';
require APPPATH . 'services/utilities/Str.php';
require APPPATH . 'helpers/func_helper.php';
require APPPATH . 'helpers/datatables_helper.php';
class AdminController { public $db, $load, $input, $currencies_model, $accounting_model; }
require APP_MODULES_PATH . 'accounting/controllers/Accounting.php';
final class ModuleTableRows extends RuntimeException { public function __construct(public array $rows) { parent::__construct('native_sql_rows_captured'); } }
function _l($value) { return $value; }
function acc_get_status_modules($name): bool { return false; }
function has_permission($feature, $unused = '', $capability = ''): bool { return $capability === 'view'; }
function get_staff_user_id(): int { return 17; }
function hooks() {
    static $hooks;
    return $hooks ??= new class {
        public function apply_filters($name, $value, ...$args) {
            if ($name === 'datatables_sql_query_results') throw new ModuleTableRows($value);
            return $value;
        }
    };
}
final class ModuleTableInput {
    public array $values = ['start'=>0,'length'=>100,'draw'=>1];
    public function post($name = null) { return $name === null ? $this->values : ($this->values[$name] ?? null); }
    public function get($name = null) { return null; }
    public function is_ajax_request(): bool { return true; }
}
function moduleTableFixture($db): void {
    moduleReaderFixture($db, db_prefix());
    $p = db_prefix();
    $db->query('ALTER TABLE ' . $p . "invoicepaymentrecords ADD paymentmode int NOT NULL DEFAULT 1");
    $db->query('ALTER TABLE ' . $p . "expenses ADD paymentmode int NOT NULL DEFAULT 1, ADD approved int NOT NULL DEFAULT 0, ADD voided int NOT NULL DEFAULT 0, ADD status int NOT NULL DEFAULT 1, ADD addedfrom int NOT NULL DEFAULT 17, ADD vendor int NOT NULL DEFAULT 0, ADD due_date date NULL, ADD date_paid date NULL, ADD reference_no varchar(100) NOT NULL DEFAULT ''");
    $tables = [
        'currencies'=>'id int PRIMARY KEY,name varchar(20) NOT NULL',
        'payment_modes'=>'id int PRIMARY KEY,name varchar(50) NOT NULL',
        'pur_vendor'=>'userid int PRIMARY KEY,company varchar(100) NOT NULL',
        'acc_checks'=>'id int PRIMARY KEY,bank_account int NOT NULL,issue int NOT NULL',
        'acc_check_details'=>'id int PRIMARY KEY,bill int NOT NULL,check_id int NOT NULL',
        'acc_accounts'=>'id int PRIMARY KEY,active int NOT NULL,account_type_id int NOT NULL,account_detail_type_id int NOT NULL',
        'acc_account_type_details'=>'id int PRIMARY KEY,name varchar(100) NOT NULL,account_type_id int NOT NULL',
    ];
    foreach ($tables as $table=>$columns) {
        $db->query('DROP TABLE IF EXISTS ' . $p . $table);
        $db->query('CREATE TABLE ' . $p . $table . ' (' . $columns . ') ENGINE=InnoDB');
    }
    $db->query('INSERT INTO ' . $p . "currencies VALUES (1,'BRL')");
    $db->query('INSERT INTO ' . $p . "payment_modes VALUES (1,'Synthetic cash')");
    $db->data_cache=[];
}
function moduleTableCall($surface, $db): array {
    $ci = &get_instance();
    $ci->input = new ModuleTableInput();
    $ci->load = new class { public function model($name): void {} };
    $ci->currencies_model = new class { public function get_base_currency() { return (object)['id'=>1,'name'=>'BRL']; } };
    $ci->accounting_model = new Accounting_model();
    $controller = (new ReflectionClass(Accounting::class))->newInstanceWithoutConstructor();
    foreach (['db','input','load','currencies_model','accounting_model'] as $property) $controller->$property = $ci->$property;
    $db->query('UPDATE ' . db_prefix() . 'expenses SET is_bill=0,approved=0');
    try {
        if (in_array($surface,['sales_table','sales_invoice_table','expenses_table','bill_payment_information_table'],true)) {
            if ($surface === 'bill_payment_information_table') $db->query('UPDATE ' . db_prefix() . 'expenses SET is_bill=1,approved=1');
            $controller->$surface();
        } else {
            $db->query('UPDATE ' . db_prefix() . 'expenses SET is_bill=1,approved=' . ($surface === 'checks' ? '1' : '0'));
            $view = new class($ci) {
                public function __construct(public $ci) {}
                public function render($surface): void { require APP_MODULES_PATH . 'accounting/views/' . $surface . '/table_bills.php'; }
            };
            $view->render($surface);
        }
    } catch (ModuleTableRows $rows) {
        return moduleReaderIds($rows->rows);
    }
    throw new RuntimeException('native_datatables_query_not_reached');
}
$observed=[];$failures=[];
foreach (['tbl','module454_'] as $prefix) {
    $GLOBALS['moduleReaderPrefix']=$prefix;$db->set_dbprefix($prefix);$db->reset_query();
    moduleTableFixture($db);
    foreach (['sales_table'=>[301,306],'sales_invoice_table'=>[101,106],'expenses_table'=>[201,207,208],
        'bill_payment_information_table'=>[201,207,208],'bills'=>[201,207,208],'checks'=>[201,207,208]] as $surface=>$expected) {
        $name=$prefix . ':' . $surface;
        $observed[$name]=moduleReaderCapture(fn()=>moduleTableCall($surface,$db));
        if($observed[$name]!==$expected)$failures[$name]=['expected'=>$expected,'actual'=>$observed[$name]];
    }
}
echo json_encode(['runtime'=>PHP_VERSION,'engine'=>$db->query('SELECT VERSION() AS version')->row()->version,
    'checks'=>count($observed),'passed'=>count($observed)-count($failures),'acceptance'=>$failures?'FAIL':'PASS',
    'scope'=>'Real native six DataTables selection queries. Post-query hook stops before rendering/counts. Not an HTTP or ACL proof.',
    'failures'=>$failures,'observed'=>$observed],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES) . "\n";
exit($failures?1:0);
