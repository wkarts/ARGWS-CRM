<?php
/** Actual Payments handler + SQL listing. A storage proxy below controls one concurrent page boundary. */
require __DIR__.'/native_payment_records.php';
require_once APPPATH.'services/NativeCompanyPaymentsHttp.php';

class CompanyRequestScope
{
    public static int $issuer;
    public static int $actor = 7;
    public static bool $csrf = true;
    public static function nativeFor($ci) { return $ci === $GLOBALS['CI']; }
    public static function sealedFor($ci) { return self::nativeFor($ci); }
    public static function context() { return new class {
        public function issuerId() { return CompanyRequestScope::$issuer; }
        public function staffId() { return CompanyRequestScope::$actor; }
    }; }
    public static function assertNativeMutation($ci) {
        if ($ci->input->method(true) !== 'POST' || !self::$csrf) throw new DomainException('native_payment_csrf_denied');
    }
    public static function adminUrl($ci, $path = '') { return '/admin/'.$path; }
}
function _l($value) { return $value; }
function app_format_money($value, $currency) { return $currency.' '.$value; }
function _d($value) { return $value; }
function get_option($key) { return $GLOBALS['paymentTableOptions'][$key] ?? '0'; }
function show_error($message, $status = 500) { $GLOBALS['CI']->output->status = $status; $GLOBALS['CI']->output->body = $message; }

$ci = $GLOBALS['CI'];
$ci->db = $db;
$ci->input = new class {
    public string $verb = 'POST';
    public array $post = [];
    public function method($upper = false) { return $upper ? $this->verb : strtolower($this->verb); }
    public function post($key = null, $filter = false) { return $key === null ? $this->post : ($this->post[$key] ?? null); }
};
$ci->output = new class {
    public int $status = 200;
    public string $body = '';
    public function set_header($value) { return $this; }
    public function set_content_type($value) { return $this; }
    public function set_output($value) { $this->body = $value; return $this; }
};
$columns = [];
for ($column = 0; $column < 7; $column++) $columns[] = ['data'=>(string)$column,'name'=>'','searchable'=>'true','orderable'=>'true','search'=>['value'=>'','regex'=>'false']];
$wire = ['draw'=>'1','start'=>'0','length'=>'25','columns'=>$columns,'order'=>[['column'=>'0','dir'=>'desc']],'search'=>['value'=>'','regex'=>'false'],'last_order_identifier'=>'payments'];
$call = static function(array $input, int $issuer, $client = null) use ($ci): array {
    CompanyRequestScope::$issuer = $issuer;
    $ci->input->post = $input;
    $ci->output->status = 200;
    $ci->output->body = '';
    NativeCompanyPaymentsHttp::handle($ci, 'table', $client);
    return [$ci->output->status, $ci->output->status === 200 ? json_decode($ci->output->body, true, 512, JSON_THROW_ON_ERROR) : $ci->output->body];
};
$startChecks = $GLOBALS['essentialChecks'];
$financialBefore = $db->query('SELECT * FROM '.$p.'invoicepaymentrecords ORDER BY id')->result_array();
[$status, $result] = $call($wire, $a);
essentialCheck($status === 200 && $result['draw'] === 1 && $result['iTotalRecords'] > 1 && count($result['aaData']) <= 25, 'Original seven-column DataTables request succeeds and remains paginated');
essentialCheck(count(array_filter(array_keys($result['aaData'][0]), 'is_int')) === 7 && $result['aaData'][0]['DT_RowClass'] === 'has-row-options', 'Native payment row preserves seven columns and original row options');
$allWire = $wire; $allWire['length'] = '-1';
[$status, $all] = $call($allWire, $a);
essentialCheck($status === 200 && count($all['aaData']) === $all['iTotalDisplayRecords'], 'The original All/export request is honored without HTTP 422 or silent truncation');
$searchWire = $allWire; $searchWire['search']['value'] = 'batch-valid';
[$status, $found] = $call($searchWire, $a);
essentialCheck($status === 200 && $found['iTotalDisplayRecords'] === 2 && count($found['aaData']) === 2, 'All keeps server-side search and returns the matching payment references');
[$status, $foreign] = $call($searchWire, $b);
essentialCheck($status === 200 && $foreign['iTotalDisplayRecords'] === 0 && $foreign['aaData'] === [], 'Switching company never carries the former company payment results');
CompanyRequestScope::$actor = 999999;
essentialCheck($call($wire, $a)[0] === 403, 'Unauthorized actor cannot list payments');
CompanyRequestScope::$actor = 7;
CompanyRequestScope::$csrf = false;
essentialCheck($call($wire, $a)[0] === 403, 'Invalid native CSRF is rejected before reading data');
CompanyRequestScope::$csrf = true;
$ci->input->verb = 'GET';
essentialCheck($call($wire, $a)[0] === 403, 'GET cannot bypass the native table transport');
$ci->input->verb = 'POST';
$bad = $wire; $bad['order'][0]['dir'] = 'desc; DROP TABLE invoices';
essentialCheck($call($bad, $a)[0] === 422, 'Invalid order is rejected before SQL');
$bad = $wire; $bad['search']['regex'] = 'true';
essentialCheck($call($bad, $a)[0] === 422, 'Unimplemented regex search is explicit');
$bad = $wire; $bad['last_order_identifier'] = 'projects';
essentialCheck($call($bad, $a)[0] === 422, 'Payment requests cannot write another table preference');
essentialCheck($db->query('SELECT * FROM '.$p.'invoicepaymentrecords ORDER BY id')->result_array() === $financialBefore, 'All table reads and rejected requests preserve the financial records exactly');

// Keep the production 500-row boundary. Padding rows belong to a disposable
// listing-only invoice; the moving payment and its reversal use the real writer.
// The proxy controls when that committed reversal occurs, not the SQL results.
foreach (['creditnotes', 'creditnote_refunds'] as $name) {
    if ($db->table_exists($p.$name)) continue;
    preg_match('/CREATE TABLE `tbl'.preg_quote($name, '/').'` \(.*?\) ENGINE=InnoDB[^;]*;/s', $native, $match);
    if (!$match) throw new RuntimeException('native fixture missing '.$name);
    $sql = str_replace('`tbl'.$name.'`', '`'.$p.$name.'`', $match[0]);
    $sql = str_replace('`id` int NOT NULL,', '`id` '.($db->driver() === 'mysql' ? 'int NOT NULL AUTO_INCREMENT PRIMARY KEY' : 'INTEGER PRIMARY KEY AUTOINCREMENT').',', $sql);
    if ($db->driver() === 'sqlite') {
        $sql = preg_replace('/ CHARACTER SET [A-Za-z0-9_]+ COLLATE [A-Za-z0-9_]+/', '', $sql);
        $sql = preg_replace('/enum\([^)]*\)/', 'varchar(50)', $sql);
    }
    $db->query($sql);
}
unset($db->data_cache['table_names']);
CompanyCreditSchema::ensure($db, $p);
$paddingInvoice = $s->create($a, 7, array_replace($input, ['items'=>[['description'=>'Listing padding only','qty'=>'1.00','rate'=>'500.00']]]), hash('sha256', 'payment-export-padding-invoice'));
$padding = $financialBefore[0];
unset($padding['id']);
$padding['invoiceid'] = $paddingInvoice;
$padding['amount'] = '1.00';
$paddingSql = 'INSERT INTO '.$p.'invoicepaymentrecords (`'.implode('`,`', array_keys($padding)).'`) VALUES ('.implode(',', array_fill(0, count($padding), '?')).')';
$db->trans_begin();
try {
    for ($n = 0; $n < 500; $n++) {
        $padding['transactionid'] = 'export-boundary-padding-'.$n;
        $db->query($paddingSql, array_values($padding));
    }
    $db->trans_commit();
} catch (Throwable $error) { $db->trans_rollback(); throw $error; }
$movingInvoice = $s->create($a, 7, array_replace($input, ['items'=>[['description'=>'Canonical moving receipt','qty'=>'1.00','rate'=>'10.00']]]), hash('sha256', 'payment-export-moving-invoice'));
$movingPayment = $writer->recordCompanyManual($a, 7, $movingInvoice, ['amount'=>'2.00','date'=>'2026-10-29','reference'=>'export-boundary-target','note'=>''], hash('sha256', 'payment-export-moving-receipt'));
$raceWire = $allWire;
$raceWire['search']['value'] = 'export-boundary';
$raceWire['order'] = [['column'=>'5','dir'=>'asc']];
[$status, $complete] = $call($raceWire, $a);
essentialCheck($status === 200 && $complete['iTotalDisplayRecords'] === 501 && count($complete['aaData']) === 501, 'All really crosses the unchanged 500-row production boundary');

$raceDb = new class($db) {
    public PDO $pdo;
    public bool $fired = false;
    public bool $empty = false;
    public $atBoundary = null;
    public function __construct(private EssentialTestDb $real) { $this->pdo = $real->pdo; }
    public function __call($method, $args) { return $this->real->$method(...$args); }
    public function query($sql, $args = []) {
        if (!$this->fired && str_starts_with($sql, 'SELECT p.id,p.invoiceid,') && preg_match('/ LIMIT 500 OFFSET 500$/D', $sql)) {
            $this->fired = true;
            if ($this->atBoundary) ($this->atBoundary)();
            // This second case explicitly simulates a page disappearing after
            // COUNT, separately from the real committed reversal above.
            if ($this->empty) return new EssentialTestResult([]);
        }
        return $this->real->query($sql, $args);
    }
};
$raceDb->atBoundary = static function() use ($writer, $a, $movingInvoice, $movingPayment): void {
    $writer->moveCompanyCredit($a, 7, 'manual_reverse', ['invoice_id'=>$movingInvoice,'payment_id'=>$movingPayment,'amount'=>'1.50','date'=>'2026-10-29','reference'=>'export-boundary-reversal','reason'=>'Concurrent partial reversal during export'], hash('sha256', 'payment-export-reversal'));
};
$ci->db = $raceDb;
try {
    [$status] = $call($raceWire, $a);
    essentialCheck($raceDb->fired && $status === 409, 'A real partial reversal moving the unread payment before OFFSET cannot export a duplicated row and omit the moved payment');
    $paymentAfter = $records->read($a, 7, $movingPayment);
    essentialCheck($paymentAfter['payment']['effective_amount'] === '0.50', 'The interleaved writer committed a real reconciled partial reversal');
    $raceDb->fired = false;
    $raceDb->atBoundary = null;
    $raceDb->empty = true;
    essentialCheck($call($raceWire, $a)[0] === 409 && $raceDb->fired, 'An empty page below the confirmed filtered count is a stale export, never silent truncation');
} finally { $ci->db = $db; }
echo 'Payments native DataTables: '.($GLOBALS['essentialChecks'] - $startChecks).' checks passed; real SQL and handler, no live HTTP/browser.'.PHP_EOL;
