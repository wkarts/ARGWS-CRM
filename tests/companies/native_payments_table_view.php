<?php
/** Real Payments route, view, table helper and persisted order; synthetic framework I/O. */
require __DIR__.'/native_dashboard_preferences.php';
require_once APPPATH.'services/NativeCompanyPaymentsHttp.php';
require_once APPPATH.'helpers/datatables_helper.php';

class CompanyRequestScope
{
    public static int $issuer;
    public static int $actor = 7;
    public static function nativeFor($ci): bool { return $ci === $GLOBALS['CI']; }
    public static function sealedFor($ci): bool { return self::nativeFor($ci); }
    public static function context() { return new class {
        public function issuerId(): int { return CompanyRequestScope::$issuer; }
        public function staffId(): int { return CompanyRequestScope::$actor; }
    }; }
}
function _l($key, ...$unused) { return $key; }
function init_head() { echo '<!doctype html><html><body><header data-payment-test-shell></header>'; }
function init_tail() { echo '<script data-payment-test-tail></script>'; }
function staff_can($cap, $feature) { return (new IssuerResourceAccess($GLOBALS['CI']->db, db_prefix()))->can(CompanyRequestScope::$issuer, CompanyRequestScope::$actor, $feature, $cap); }
function show_error($message, $status = 500) { $GLOBALS['CI']->output->status = $status; echo $message; }

$ci = $GLOBALS['CI'];
$ci->db = $db;
$ci->input = new class { public function method($upper = false) { return $upper ? 'GET' : 'get'; } };
$ci->agent = new class { public function browser() { return 'Chrome'; } };
$ci->output = new class {
    public int $status = 200;
    public string $body = '';
    public function set_header($header) { return $this; }
    public function set_output($body) { $this->body = $body; return $this; }
};
$ci->load = new class {
    public $load;
    public function __construct() { $this->load = $this; }
    public function view($name, $data = [], $return = false) {
        extract($data);
        if ($return) ob_start();
        require APPPATH.'views/'.$name.'.php';
        if ($return) return ob_get_clean();
    }
};
$render = static function(int $issuer, int $actor = 7) use ($ci): array {
    CompanyRequestScope::$issuer = $issuer;
    CompanyRequestScope::$actor = $actor;
    $ci->output->status = 200;
    $ci->output->body = '';
    ob_start();
    try { NativeCompanyPaymentsHttp::handle($ci, 'index'); return [$ci->output->status, ob_get_contents().$ci->output->body]; }
    finally { ob_end_clean(); }
};

$before = $GLOBALS['essentialChecks'];
$primary = (new IssuerResourceAccess($db, $p))->primaryId();
foreach ([$primary, $a, $b] as $issuer) {
    [$status, $html] = $render($issuer);
    essentialCheck($status === 200 && str_contains($html, 'table-payments') && str_contains($html, 'data-payment-test-tail'), 'Payments index renders the full original table and tail for company '.$issuer.' (HTTP status '.$status.')');
    essentialCheck(substr_count($html, '<th>') === 6 && substr_count($html, '<th ') === 1, 'Payments retains the seven original columns');
}
$ta = new NativeCompanyTableOrder($db, $p, $a, 7);
$tb = new NativeCompanyTableOrder($db, $p, $b, 7);
$tp = new NativeCompanyTableOrder($db, $p, $primary, 7);
$db->query('INSERT INTO '.$p.'user_meta(staff_id,meta_key,meta_value) VALUES(?,?,?)', [7, 'payments-table-last-order', '[[6,"desc"]]']);
$ta->save('payments', [['column' => '1', 'dir' => 'asc']], 7);
essentialCheck($ta->read('payments') === '[[1,"asc"]]' && $tb->read('payments') === '' && $tp->read('payments') === '[[6,"desc"]]', 'Payments order stays with its company and preserves the primary preference');
[$status, $html] = $render($a);
essentialCheck($status === 200 && str_contains($html, 'data-default-order="[[1,&quot;asc&quot;]]"'), 'Real table HTML restores the company order');
[$status, $html] = $render($a, 51);
essentialCheck($status === 403 && !str_contains($html, 'table-payments'), 'Restricted actor cannot render payment data or controls');
essentialThrows(fn() => $ta->save('payments', [[7, 'asc']], 7), 'native_table_order_invalid', 'Out-of-range payment sorting cannot be persisted');
$db->failOn = 'SELECT meta_value FROM '.$p.'user_meta';
try {
    [$status, $html] = $render($a);
    essentialCheck($status === 503 && !str_contains($html, 'data-payment-test-shell') && !str_contains($html, 'table-payments'), 'A storage error returns one clean error response without leaking a partial admin shell');
} finally { $db->failOn = null; }
echo 'Payments native view: '.($GLOBALS['essentialChecks'] - $before).' checks passed with real view/helper and SQLite storage; not a browser session.'.PHP_EOL;
