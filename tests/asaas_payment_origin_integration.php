<?php

declare(strict_types=1);

namespace Asaas\Sdk\Http {
    enum Environment: string { case Sandbox = 'sandbox'; case Production = 'production'; }
    class Client {
        public static array $calls = [];
        public static array $responses = [];
        public function __construct(public string $key, public Environment $env, ...$ignored) {}
        public function request($method, $path, ...$ignored) {
            self::$calls[] = [$method, $path, $this->key, $this->env->value];
            if (!self::$responses) { throw new \RuntimeException('Unexpected mock network call'); }
            $next = array_shift(self::$responses);
            if ($next instanceof \Throwable) { throw $next; }
            return $next;
        }
    }
}
namespace Asaas\Sdk\Exception {
    class ApiException extends \RuntimeException { public function getStatusCode() { return 401; } }
    class TransportException extends \RuntimeException {}
}
namespace {
    define('BASEPATH', __DIR__);
    define('FCPATH', dirname(__DIR__) . '/');
    require_once FCPATH . 'application/libraries/gateways/App_gateway.php';
    class App_Model { public $db; }
    class FakeResult {
        public function __construct(private array $rows) {}
        public function row_array() { return $this->rows[0] ?? []; }
        public function row() { return isset($this->rows[0]) ? (object) $this->rows[0] : null; }
        public function result_array() { return $this->rows; }
        public function num_rows() { return count($this->rows); }
    }
    class FakeDB {
        public bool $db_debug = true;
        public array $tables = ['tblasaas_payment_origins'=>[], 'tblasaas_payment_origin_events'=>[], 'tblasaas_payments_map'=>[], 'tblinvoicepaymentrecords'=>[], 'tblinvoices'=>[], 'tblclients'=>[]];
        public ?string $failInsert = null;
        public ?string $failUpdate = null;
        protected array $where = [];
        protected string $from = '';
        protected int $affected = 0;
        public function query($sql, $args = []) {
            if ($sql === 'SELECT installed_version FROM tblmodules WHERE module_name=?') return $this->get_where('tblmodules',['module_name'=>$args[0]]);
            if ($sql === 'SELECT id FROM tblinvoices WHERE id = ? AND tblinvoices.issuer_id IS NULL') {
                return new FakeResult(array_values(array_filter($this->tables['tblinvoices'], static fn($r) => (string)$r['id'] === (string)$args[0] && ($r['issuer_id'] ?? null) === null)));
            }
            throw new \RuntimeException('Unexpected scope query: '.$sql);
        }
        public function table_exists($t) { return isset($this->tables[$t]); }
        public function field_exists($c, $t) { return true; }
        public function select($s) { return $this; }
        public function from($t) { $this->from=$t; return $this; }
        public function limit($n) { return $this; }
        public function where($k, $v = null, $escape = true) { $this->where[$k]=$v; return $this; }
        public function get_where($t, $where) { foreach ($where as $k=>$v) { $this->where($k,$v); } return $this->get($t); }
        protected function match(array $r): bool { foreach ($this->where as $k=>$v) { if ($k === 'remote_id IS NULL') { if (($r['remote_id']??null)!==null) return false; } elseif (($r[$k]??null) != $v) { return false; } } return true; }
        public function get($t = null) { $t=$t??$this->from; $r=array_values(array_filter($this->tables[$t]??[],fn($r)=>$this->match($r))); $this->where=[]; return new FakeResult($r); }
        public function insert($t,$data) {
            if ($this->failInsert === $t) return false;
            if ($t==='tblasaas_payment_origins') foreach($this->tables[$t] as $r) if($r['invoice_id']==$data['invoice_id']) return false;
            $data['id']=$data['id']??count($this->tables[$t]??[])+1; $this->tables[$t][]=$data; $this->affected=1; return true;
        }
        public function update($t,$data) {
            if ($this->failUpdate === $t) { $this->where=[]; return false; }
            $this->affected=0; foreach($this->tables[$t] as &$r) if($this->match($r)){ $r=array_replace($r,$data); $this->affected++; } unset($r); $this->where=[]; return true;
        }
        public function affected_rows() { return $this->affected; }
        public function error() { return ['code'=>'mock']; }
    }
    class FakeHooks {
        public array $filters=[]; public array $actions=[];
        public function add_filter($n,$f,...$ignored){$this->filters[$n][]=$f;}
        public function add_action($n,$f,...$ignored){$this->actions[$n][]=$f;}
        public function apply_filters($n,$v,...$args){foreach($this->filters[$n]??[] as $f)$v=$f($v,...$args); return $v;}
        public function do_action($n,...$args){foreach($this->actions[$n]??[] as $f)$f(...$args);}
    }
    $options=[]; $alerts=[]; $hooks=new FakeHooks(); $ci=(object)[];
    function &get_instance(){global $ci; return $ci;}
    function hooks(){global $hooks;return $hooks;}
    function get_option($n){global $options;return $options[$n]??'';}
    function profile_option($environment,$field,$value){global $ci;$name=\Asaas\EnvironmentSettings::option($field,$environment);foreach($ci->db->tables['tbloptions'] as &$r)if($r['name']===$name)$r['value']=$value;}
    function decrypt($v){return $v;}
    function db_prefix(){return 'tbl';}
    function get_staff_user_id(){return 7;}
    function log_message(...$v){$GLOBALS['asaas_test_logs'][]=$v;}
    function set_alert(...$v){global $alerts;$alerts[]=$v;}
    function register_activation_hook(...$v){}
    function register_payment_gateway(...$v){}
    require_once FCPATH . 'modules/asaas/libraries/Asaas_gateway.php';
    require_once FCPATH . 'application/services/InvoiceIssuerScope.php';
    require_once FCPATH . 'application/models/Invoices_model.php';
    require_once FCPATH . 'modules/asaas/asaas.php';
    class TestInvoices extends Invoices_model {
        public function __construct($db){$this->db=$db;}
        public function is_draft($id){return false;}
        public function log_invoice_activity($id,$description='',$client=false,$additional_data=''){}
    }
    $checks=0;
    function check($v,$m){global $checks;if(!$v)throw new \RuntimeException($m);$checks++;}
    function throws(callable $fn, string $message){try{$fn();}catch(\Throwable $e){check(true,$message);return;}throw new \RuntimeException($message);}
    function fixture(): array {
        global $ci,$alerts,$options;
        $options=['paymentmethod_asaas_profile_schema'=>'1','paymentmethod_asaas_sandbox'=>'1','paymentmethod_asaas_api_key'=>'fixture_production','paymentmethod_asaas_api_key_sandbox'=>'fixture_sandbox'];$alerts=[];$ci->db=new FakeDB();$ci->load=new class {public function library(...$x){}};
        $ci->encryption=new class {public function decrypt($v){return $v;}};
        $ci->db->tables['tblmodules']=[['module_name'=>'asaas','installed_version'=>'1.5.4']];
        $ci->db->tables['tbloptions']=[];
        $ci->db->tables['tblcurrencies']=[['id'=>1,'name'=>'BRL']];
        foreach(\Asaas\EnvironmentSettings::ENVIRONMENTS as $env)foreach(\Asaas\EnvironmentSettings::DEFAULTS as $name=>$value){$ci->db->tables['tbloptions'][]=['name'=>\Asaas\EnvironmentSettings::option($name,$env),'value'=>in_array($name,['active','delete_charge'],true)?'1':$value];}
        $ci->asaas_gateway=new Asaas_gateway();
        $ci->db->tables['tblinvoices'][]=['id'=>1,'hash'=>'invoice_hash','total'=>'100.00','duedate'=>'2026-11-01','status'=>1,'issuer_id'=>null,'clientid'=>10,'currency'=>1];
        $ci->db->tables['tblclients'][]=['userid'=>10,'vat'=>'11144477735'];
        \Asaas\Sdk\Http\Client::$calls=[];\Asaas\Sdk\Http\Client::$responses=[];
        return [$ci->asaas_gateway,$ci->asaas_gateway->getAdapter(),$ci->db];
    }
    function known($adapter,$db): void {
        $adapter->originStore()->reserve($db->tables['tblinvoices'][0],['account_id'=>'wallet_original','environment'=>'sandbox','provider'=>'asaas','channel'=>'api']);
        $adapter->originStore()->attachRemote(1,'pay_original');
        $db->tables['tblasaas_payments_map'][]=['id'=>1,'invoice_id'=>1,'asaas_payment_id'=>'pay_original','status'=>'PENDING'];
    }
    function customer(){return ['id'=>'cus_original','cpfCnpj'=>'11144477735'];}
    function wallet($id='wallet_original'){return ['data'=>[['id'=>$id]],'hasMore'=>false];}
    function payment($status='PENDING'){return ['id'=>'pay_original','status'=>$status,'externalReference'=>'invoice_hash','value'=>100,'dueDate'=>'2026-11-01','customer'=>'cus_original'];}
    use Asaas\Sdk\Http\Client;

    if (defined('ASAAS_FIXTURES_ONLY')) return;

    [$gw,$adapter,$db]=fixture(); known($adapter,$db);
    $GLOBALS['options']['paymentmethod_asaas_sandbox']='0'; // Global environment changed after issue.
    Client::$responses=[wallet(),payment(),['id'=>'pay_original','deleted'=>true]];
    $model=new TestInvoices($db);
    check($model->mark_as_cancelled(1)===true,'real core cancel passes after removal');
    check($db->tables['tblinvoices'][0]['status']===5,'core updates status after remote result');
    check(array_column(Client::$calls,1)===['v3/wallets/','v3/payments/pay_original','v3/payments/pay_original'],'wallet, GET, DELETE order');
    check(array_column(Client::$calls,0)===['GET','GET','DELETE'],'official delete, no cancel/refund');
    check(count(array_unique(array_column(Client::$calls,3)))===1&&Client::$calls[0][3]==='sandbox','saved environment wins');
    check(count(array_unique(array_column(Client::$calls,2)))===1&&Client::$calls[0][2]==='fixture_sandbox','one client/key for lookup and removal');
    check($db->tables['tblasaas_payments_map'][0]['status']==='DELETED','map updated only on confirmed delete');
    check($adapter->originStore()->byInvoice(1)['account_id']==='wallet_original','origin preserved');
    check(asaas_guard_invoice_mutation(true,1,'cancel')===true&&count(Client::$calls)===3,'idempotent repeated removal has no new remote effects');

    foreach(['cancel','delete'] as $action){
        [$gw,$adapter,$db]=fixture();known($adapter,$db);
        $db->tables['tblinvoicepaymentrecords'][]=['id'=>10,'invoiceid'=>1,'amount'=>10];
        $model=new TestInvoices($db);
        check(($action==='cancel'?$model->mark_as_cancelled(1):$model->delete(1))===false,'real core guard vetoes receipt ' . $action);
        check($db->tables['tblinvoices'][0]['status']===1&&count($db->tables['tblinvoicepaymentrecords'])===1,'invoice and receipts preserved');
        check(Client::$calls===[],'receipt guard before any provider request');
    }
    foreach([wallet('wallet_other'),new \Asaas\Sdk\Exception\TransportException('timeout')] as $response){
        [$gw,$adapter,$db]=fixture();known($adapter,$db);Client::$responses=[$response];
        check((new TestInvoices($db))->mark_as_cancelled(1)===false,'wrong account/failed wallet vetoes local cancel');
        check(count(Client::$calls)===1&&$db->tables['tblasaas_payments_map'][0]['status']==='PENDING','failure preserves map without DELETE');
    }
    foreach(['CONFIRMED','RECEIVED','REFUNDED','PARTIALLY_REFUNDED'] as $status){
        [$gw,$adapter,$db]=fixture();known($adapter,$db);Client::$responses=[wallet(),payment($status)];
        check((new TestInvoices($db))->delete(1)===false,'received/refunded remote state vetoes core delete');
        check(count(Client::$calls)===2&&$db->tables['tblinvoices'][0]['status']===1,'no DELETE for protected remote state');
    }
    foreach([[],new \Asaas\Sdk\Exception\TransportException('timeout')] as $response){
        [$gw,$adapter,$db]=fixture();known($adapter,$db);Client::$responses=[wallet(),payment(),$response];
        check((new TestInvoices($db))->mark_as_cancelled(1)===false,'empty/error delete response not success');
        check($adapter->originStore()->byInvoice(1)['state']==='review_required','failure queued visibly');
        check($db->tables['tblasaas_payments_map'][0]['status']==='PENDING','error response never marks cancelled/deleted');
    }
    [$gw,$adapter,$db]=fixture();$db->tables['tblasaas_payments_map'][]=['id'=>1,'invoice_id'=>1,'asaas_payment_id'=>'legacy'];
    check(asaas_guard_invoice_mutation(true,1,'delete')===true,'unknown legacy has no broad local veto');
    check(Client::$calls===[]&&$adapter->originStore()->byInvoice(1)===null,'legacy never guesses account or creates origin');
    check(count($db->tables['tblasaas_payment_origin_events'])===1,'legacy review recorded');
    asaas_invoice_status_changed(['invoice_id'=>1,'status'=>4]);check(Client::$calls===[],'OVERDUE hook never deletes');
    check(isset(hooks()->actions['invoice_marked_as_cancelled']),'actual core cancel event registered');
    check(asaas_guard_invoice_mutation(false,1,'delete')===false,'preserve earlier veto');
    [$gw,$adapter,$db]=fixture();known($adapter,$db);profile_option('sandbox','delete_charge','0');
    check(asaas_guard_invoice_mutation(true,1,'delete')===false&&Client::$calls===[],'disabled automatic delete preserved and known invoice retained');

    [$gw,$adapter,$db]=fixture();Client::$responses=[wallet(),customer(),payment()];
    $result=$adapter->createPayment(['externalReference'=>'invoice_hash','customer'=>'cus_original','value'=>100]);
    check($result['id']==='pay_original'&&$adapter->originStore()->byInvoice(1)['state']==='issued','new issue connected to persisted origin');
    check(count($db->tables['tblasaas_payments_map'])===1,'new issue stores map in same call');
    $GLOBALS['options']['paymentmethod_asaas_sandbox']='0';Client::$responses=[wallet(),payment()];
    $adapter->createPayment(['externalReference'=>'invoice_hash','customer'=>'cus_original']);
    check(count(array_filter(Client::$calls,fn($c)=>$c[0]==='POST'))===1,'repeat issuance uses persisted payment not new POST');
    check(!$gw->upsert_payment_map(1,['id'=>'pay_different']),'cannot overwrite immutable map');
    throws(fn()=>$adapter->originStore()->attachRemote(1,'pay_different'),'cannot overwrite immutable remote id');
    check($adapter->originStore()->byInvoice(1)['remote_id']==='pay_original','remote id unchanged');

    [$gw,$adapter,$db]=fixture();Client::$responses=[wallet(),customer(),new \Asaas\Sdk\Exception\TransportException('timeout')];
    throws(fn()=>$adapter->createPayment(['externalReference'=>'invoice_hash','customer'=>'cus_original']),'uncertain POST throws');
    check($adapter->originStore()->byInvoice(1)['state']==='review_required','uncertain issue persists review before retry');
    throws(fn()=>$adapter->createPayment(['externalReference'=>'invoice_hash','customer'=>'cus_original']),'uncertain POST cannot blind retry');
    check(count(Client::$calls)===3,'only one actual POST');
    Client::$responses=[wallet(),payment()];
    check($adapter->reconcileOrigin(1,'pay_original','sandbox','wallet_original')['success'],'assisted reconciliation recovers uncertain emission');
    check($adapter->originStore()->byInvoice(1)['remote_id']==='pay_original','reconciled id bound once');
    throws(fn()=>$adapter->reconcileOrigin(1,'pay_other','sandbox','wallet_original'),'reconciliation cannot reassign origin');

    [$gw,$adapter,$db]=fixture();$db->failInsert='tblasaas_payment_origins';Client::$responses=[wallet(),customer()];
    throws(fn()=>$adapter->createPayment(['externalReference'=>'invoice_hash','customer'=>'cus_original']),'reserve failure blocks POST');
    check(count(Client::$calls)===2&&count(array_filter(Client::$calls,fn($c)=>$c[0]==='POST'))===0,'no POST without durable reservation');
    [$gw,$adapter,$db]=fixture();Client::$responses=[wallet(),customer(),payment()];$db->failInsert='tblasaas_payments_map';
    throws(fn()=>$adapter->createPayment(['externalReference'=>'invoice_hash','customer'=>'cus_original']),'map failure surfaces');
    check($adapter->originStore()->byInvoice(1)['remote_id']==='pay_original','remote binding retained when map fails');

    [$gw,$adapter,$db]=fixture();
    throws(fn()=>$gw->getProvider()->apiKey(2),'explicit company cannot inherit global key');
    throws(fn()=>$gw->getProvider()->env(2),'explicit company cannot inherit global sandbox');
    $options['paymentmethod_asaas_sandbox_company_2']='1';
    throws(fn()=>$gw->getProvider()->apiKey(2),'explicit environment alone still cannot inherit credentials');
    $options['paymentmethod_asaas_api_key_sandbox_company_2']='fixture_company2';
    check($gw->getProvider()->apiKey(2)==='fixture_company2','explicit complete company settings work');
    $db->tables['tblinvoices'][0]['issuer_id']=2;
    throws(fn()=>$adapter->createPayment(['externalReference'=>'invoice_hash','customer'=>'cus_original']),'issuer rollout cannot silently inherit legacy context');
    check(Client::$calls===[],'blocked issuer before network');
    [$gw,$adapter,$db]=fixture();Client::$responses=[new \Asaas\Sdk\Exception\ApiException('unauthorized')];
    throws(fn()=>$adapter->listCustomers(['cpfCnpj'=>'fixture']),'API errors must not become empty lists');
    check(count(Client::$calls)===1,'lookup error never creates customer');
    [$gw,$adapter,$db]=fixture();known($adapter,$db);
    $db->failUpdate='tblasaas_payments_map';Client::$responses=[wallet(),payment(),['id'=>'pay_original','deleted'=>true]];
    check((new TestInvoices($db))->mark_as_cancelled(1)===false,'map failure after remote removal vetoes local mutation');
    check($adapter->originStore()->byInvoice(1)['state']==='removed','confirmed removal retained across map failure');
    check((new TestInvoices($db))->mark_as_cancelled(1)===false&&count(Client::$calls)===3,'retry still vetoes while map cannot be repaired');
    $db->failUpdate=null;
    check((new TestInvoices($db))->mark_as_cancelled(1)===true&&count(Client::$calls)===3,'retry repairs map before allowing core cancel, no second DELETE');
    check($db->tables['tblasaas_payments_map'][0]['status']==='DELETED','map repaired');

    foreach([['id'=>'cus_original','cpfCnpj'=>'22233344455'],['id'=>'cus_other','cpfCnpj'=>'11144477735'],[]] as $remoteCustomer){
        [$gw,$adapter,$db]=fixture();Client::$responses=[wallet(),$remoteCustomer];
        throws(fn()=>$adapter->createPayment(['externalReference'=>'invoice_hash','customer'=>'cus_original']),'foreign customer cannot enter new origin');
        check(count(Client::$calls)===2&&$adapter->originStore()->byInvoice(1)===null,'foreign customer blocked before POST/reservation');
    }
    [$gw,$adapter,$db]=fixture();$db->tables['tblinvoices'][0]['issuer_id']=2;
    $result=$gw->charge_billet((object)$db->tables['tblinvoices'][0]);
    check(isset(json_decode($result,true)['errors'])&&Client::$calls===[],'checkout boundary returns explicit error and does not fall through');

    [$gw,$adapter,$db]=fixture();known($adapter,$db);
    Client::$responses=[wallet('wallet_other')];
    $webhook=new \ReflectionMethod($adapter,'reconcileWebhookEvent');
    check($webhook->invoke($adapter,['event'=>'PAYMENT_RECEIVED','payment'=>payment('RECEIVED')])['state']==='review_required','known-origin webhook cannot settle through another account');
    check(count(Client::$calls)===0&&$db->tables['tblinvoicepaymentrecords']===[],'unscoped webhook preserves receipts without remote calls');
    [$gw,$adapter,$db]=fixture();$db->tables['tblinvoices'][0]['issuer_id']=2;
    $webhook=new \ReflectionMethod($adapter,'reconcileWebhookEvent');
    check($webhook->invoke($adapter,['event'=>'PAYMENT_RECEIVED','payment'=>payment('RECEIVED')])['state']==='review_required'&&Client::$calls===[],'issuer webhook remains fail-closed');

    foreach(['', 'sandbox', 'true', 'false', '2', 0, 1, true, false] as $ambiguous){
        [$gw,$adapter,$db]=fixture();$GLOBALS['options']['paymentmethod_asaas_sandbox']=$ambiguous;
        throws(fn()=>$adapter->createPayment(['externalReference'=>'invoice_hash','customer'=>'cus_original']),'ambiguous environment fails closed');
        check(Client::$calls===[],'invalid environment cannot default to production');
    }
    foreach([2,3,5,6] as $status){
        [$gw,$adapter,$db]=fixture();$db->tables['tblinvoices'][0]['status']=$status;
        throws(fn()=>$adapter->createPayment(['externalReference'=>'invoice_hash','customer'=>'cus_original']),'paid/partial/cancelled/draft invoice cannot issue');
        check(Client::$calls===[],'ineligible invoice blocked before provider');
    }
    [$gw,$adapter,$db]=fixture();$db->tables['tblinvoicepaymentrecords'][]=['invoiceid'=>1,'amount'=>1];
    throws(fn()=>$adapter->createPayment(['externalReference'=>'invoice_hash','customer'=>'cus_original']),'receipt blocks new issuance even when status stale');
    check(Client::$calls===[],'receipt blocks issuance before wallet lookup');

    [$gw,$adapter,$db]=fixture();$db->tables['tblclients'][0]['vat']='AB.1C2.345/6789-01';
    Client::$responses=[wallet(),['id'=>'cus_original','cpfCnpj'=>'12345678901']];
    throws(fn()=>$adapter->createPayment(['externalReference'=>'invoice_hash','customer'=>'cus_original']),'alphanumeric CNPJ cannot collapse to an unrelated CPF');
    check($adapter->originStore()->byInvoice(1)===null&&count(Client::$calls)===2,'identity collision blocked before reservation or POST');
    [$gw,$adapter,$db]=fixture();$db->tables['tblclients'][0]['vat']='AB.1C2.345/6789-01';
    Client::$responses=[wallet(),['id'=>'cus_original','cpfCnpj'=>'ab1c2345678901'],payment()];
    check($adapter->createPayment(['externalReference'=>'invoice_hash','customer'=>'cus_original'])['id']==='pay_original','CNPJ identity preserves letters and normalizes case/formatting');
    [$gw,$adapter,$db]=fixture();$db->tables['tblclients'][0]['vat']='@11144477735';
    Client::$responses=[wallet(),customer()];
    throws(fn()=>$adapter->createPayment(['externalReference'=>'invoice_hash','customer'=>'cus_original']),'unexpected identity characters cannot be silently removed');
    check(count(Client::$calls)===2,'invalid document does not issue');

    class AdminController {
        public $load; public $input; public $session; public $asaas_gateway;
        public function __construct(){global $ci;$this->load=$ci->load;$this->asaas_gateway=$ci->asaas_gateway;}
    }
    $admin=true;
    function is_admin(){global $admin;return $admin;}
    function access_denied(...$a){throw new \RuntimeException('access_denied');}
    function show_error(...$a){throw new \RuntimeException('forbidden');}
    function show_404(){throw new \RuntimeException('not_found');}
    function admin_url($url){return '/admin/'.$url;}
    function redirect($url){$GLOBALS['originRedirect']=$url;}
    require_once FCPATH.'modules/asaas/controllers/Payment_origins.php';
    [$gw,$adapter,$db]=fixture();
    $admin=false;throws(fn()=>new Payment_origins(),'non-admin cannot access reconciliation');$admin=true;
    $controller=new Payment_origins();
    $controller->input=new class {
        public array $data=['invoice_id'=>1,'remote_id'=>'pay_original','account_id'=>'wallet_original','environment'=>'sandbox','origin_csrf'=>'bad'];
        public string $httpMethod='POST';
        public array $query=[];
        public function get($name){return $this->query[$name]??null;}
        public function method(){return $this->httpMethod;}
        public function post($name){return $this->data[$name]??null;}
    };
    $controller->session=new class {public function userdata($name){return str_repeat('a',64);}};
    throws(fn()=>$controller->reconcile(),'controller rejects invalid independent CSRF token');
    check(Client::$calls===[],'CSRF rejected before provider or binding');
    $controller->input->data['origin_csrf']=str_repeat('a',64);$controller->input->httpMethod='GET';
    throws(fn()=>$controller->reconcile(),'GET cannot mutate binding');
    $controller->input->httpMethod='POST';$controller->input->query=['panel'=>'1'];Client::$responses=[wallet(),payment()];
    $controller->reconcile();
    check($adapter->originStore()->byInvoice(1)['remote_id']==='pay_original','valid admin POST verifies remote evidence and records origin');
    check(array_column(Client::$calls,0)===['GET','GET'],'reconciliation is read-only at provider');
    check($GLOBALS['originRedirect']==='/admin/asaas/payment_origins?panel=1','embedded reconciliation returns to protected partial without losing panel context');
    check(end($db->tables['tblasaas_payment_origin_events'])['actor_id']===7,'reconciliation records authenticated actor');

    echo "OK: {$checks} integration assertions; real provider, gateway, hooks and core guards; no network.\n";
}
