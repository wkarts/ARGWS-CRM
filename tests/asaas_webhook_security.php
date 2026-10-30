<?php
declare(strict_types=1);
define('ASAAS_FIXTURES_ONLY',true);
require __DIR__.'/asaas_payment_origin_integration.php';
require_once FCPATH.'modules/asaas/libraries/Asaas/WebhookSecurity.php';
require_once FCPATH.'modules/asaas/libraries/Asaas/WebhookIntegrationStore.php';
use Asaas\Sdk\Http\Client;
use Asaas\WebhookSecurity;
require_once FCPATH.'system/libraries/Encryption.php';
$admin=true;
class WebhookDB extends FakeDB
{
    private ?array $snapshot=null;
    public int $_trans_depth=0;
    public int $rollbackCalls=0;
    public bool $rawTransaction=false;
    public bool $autocommit=true;
    public int $completionType=0;
    public bool $lockAvailable=true;
    public bool $failCommit=false;
    public bool $failBegin=false;
    public bool $nonTransactional=false;
    public bool $failInsertQuery=false;
    public array $locks=[];
    public function __construct() { $this->tables['tblasaas_webhook_integrations']=[]; $this->tables['tblasaas_webhook_events']=[];$this->tables['tblcredits']=[];$this->tables['tblasaas_settlement_outbox']=[];$this->tables['tblsales_activity']=[];$this->tables['tblasaas_webhook_admin_audit']=[]; }
    private int $lastInsertId=0;
    public function insert($table,$row){$ok=parent::insert($table,$row);if($ok)$this->lastInsertId=(int)end($this->tables[$table])['id'];return $ok;}
    public function insert_id(){return $this->lastInsertId;}
    public function order_by(...$x){return $this;}
    public function protect_identifiers($s,...$x){return '`'.$s.'`';}
    public function trans_begin(){ if($this->failBegin)return false; $this->snapshot=$this->tables; $this->_trans_depth=1; return true; }
    public function trans_status(){return true;}
    public function trans_commit(){if($this->failCommit)return false; $this->snapshot=null;$this->_trans_depth=0;return true;}
    public function trans_rollback(){++$this->rollbackCalls;if($this->snapshot!==null)$this->tables=$this->snapshot;$this->snapshot=null;$this->_trans_depth=0;return true;}
    public function query($sql,$args=[]){
        if($sql==='SELECT installed_version FROM tblmodules WHERE module_name=?')return parent::query($sql,$args);
        if(str_starts_with($sql,'SELECT COLUMN_TYPE,IS_NULLABLE,COLUMN_DEFAULT,COLLATION_NAME'))return new FakeResult([['COLUMN_TYPE'=>'text','IS_NULLABLE'=>'YES','COLUMN_DEFAULT'=>null,'COLLATION_NAME'=>'utf8mb4_bin']]);
        if(str_starts_with($sql,'SELECT @@SESSION.autocommit'))return new FakeResult([['autocommit'=>$this->autocommit?1:0,'completion_type'=>$this->completionType]]);
        if(str_starts_with($sql,'SET TRANSACTION'))return !$this->rawTransaction;
        if(str_starts_with($sql,'SELECT ENGINE FROM'))return new FakeResult([['ENGINE'=>$this->nonTransactional?'MyISAM':'InnoDB']]);
        if(str_starts_with($sql,'SELECT GET_LOCK')){ $this->locks[]=$args[0];return new FakeResult([['acquired'=>$this->lockAvailable?1:0]]); }
        if(str_starts_with($sql,'SELECT RELEASE_LOCK')){array_pop($this->locks);return new FakeResult([['released'=>1]]);}
        if(str_starts_with($sql,'INSERT IGNORE')){
            if($this->failInsertQuery)return false;
            $data=array_combine(['event_id','event_type','asaas_payment_id','received_at','payload','process_status','integration_id','scope_key','payload_hash'],$args);
            foreach($this->tables['tblasaas_webhook_events'] as $r)if($r['event_id']===$data['event_id']&&$r['scope_key']===$data['scope_key']){$this->affected=0;return true;}
            $data+=['attempt_count'=>0,'next_attempt_at'=>null];return $this->insert('tblasaas_webhook_events',$data);
        }
        if(str_starts_with($sql,'SELECT * FROM')&&preg_match('/`(\w+)`/',$sql,$m)){preg_match('/WHERE (\w+) =/',$sql,$w);return $this->get_where($m[1],[$w[1]??'id'=>$args[0]]);}
        if(str_starts_with($sql,'SELECT id FROM')){
            return new FakeResult(array_values(array_filter($this->tables['tblasaas_webhook_events'],fn($r)=>in_array($r['process_status'],['received','failed'])&&empty($r['next_attempt_at'])||in_array($r['process_status'],['received','failed'])&&$r['next_attempt_at']<=$args[0])));
        }
        if(str_starts_with($sql,'UPDATE `tblasaas_webhook_integrations` SET rejection_count')){
            foreach($this->tables['tblasaas_webhook_integrations'] as &$r)if($r['id']==$args[1]){$r['rejection_count']++;$r['last_rejected_at']=$args[0];}unset($r);return true;
        }
        throw new RuntimeException('Unexpected SQL fixture: '.$sql);
    }
}
class WebhookGateway extends Asaas_gateway
{
    public array $receiptCalls=[];
    public function addPayment($data){global $ci;$this->receiptCalls[]=$data;$ci->db->insert('tblinvoicepaymentrecords',['invoiceid'=>$data['invoiceid'],'transactionid'=>$data['transactionid'],'amount'=>$data['amount']]);$ci->db->where('id',$data['invoiceid'])->update('tblinvoices',['status'=>2]);return true;}
}
function webhookFixture(): array {
    global $ci;
    fixture();$ci->encryption=new CI_Encryption(['driver'=>'openssl','key'=>str_repeat('fixture-encryption-key',2)]);
    foreach (['api_key', 'api_key_sandbox'] as $key) $GLOBALS['options']['paymentmethod_asaas_' . $key] = $ci->encryption->encrypt($GLOBALS['options']['paymentmethod_asaas_' . $key]);
    $original=$ci->db->tables;$db=new WebhookDB();$db->tables=array_replace($db->tables,$original);$db->tables['tblinvoices'][0]['currency']=1;$db->tables['tblcurrencies']=[['id'=>1,'name'=>'BRL']];$ci->db=$db;
    $gw=new WebhookGateway();$ci->asaas_gateway=$gw;$GLOBALS['options']['paymentmethod_asaas_webhook_secret']=str_repeat('l',40);$adapter=$gw->getAdapter();known($adapter,$db);
    $scope=['issuer_id'=>null,'provider'=>'asaas','account_id'=>'wallet_original','environment'=>'sandbox'];
    $i=$adapter->webhookIntegrations()->createVerified('Fixture', $scope);$GLOBALS['fixtureToken']=$i['one_time_token'];
    return [$gw,$adapter,$db,$i];
}
function canonicalPayment($status='RECEIVED'):array { return payment($status)+['paymentDate'=>'2026-01-15','billingType'=>'PIX']; }
function eventBody($id='evt_1',$event='PAYMENT_RECEIVED',$paymentId='pay_original'): string { return json_encode(['id'=>$id,'event'=>$event,'payment'=>['id'=>$paymentId,'status'=>'RECEIVED','value'=>99999,'customer'=>'attacker','externalReference'=>'untrusted','creditCard'=>['number'=>'TEST_PAN_MUST_NOT_PERSIST','ccv'=>'TEST_CVV']]]); }
function headers($token=null):array {return ['Asaas-Access-Token'=>$token??($GLOBALS['fixtureToken']??str_repeat('t',40)),'Content-Type'=>'application/json; charset=utf-8'];}
function receive($adapter,$integration,$id='evt_1',$event='PAYMENT_RECEIVED'):array{return $adapter->handleWebhook(headers($integration['one_time_token']),eventBody($id,$event),$integration['public_id']);}
function processOne($adapter,$id=1):array {return (new ReflectionMethod($adapter,'processStoredWebhook'))->invoke($adapter,$id);}

foreach(['','short',str_repeat('a',256),str_repeat('a',31).' ', '$aact_'.str_repeat('a',40)] as $bad)check(!WebhookSecurity::validToken($bad),'invalid token fails');
$ok=WebhookSecurity::validate(str_repeat('t',40),headers(),eventBody());check($ok['ok'],'documented header authenticates');
check(WebhookSecurity::validate('',headers(),eventBody())['status']===503,'missing config closed');
check(WebhookSecurity::validate(str_repeat('t',40),headers(str_repeat('x',40)),eventBody())['status']===401,'invalid header rejected');
check(WebhookSecurity::validate(str_repeat('t',40),['asaas-access-token'=>str_repeat('t',40)],eventBody())['status']===415,'content type required');
check(WebhookSecurity::validate(str_repeat('t',40),headers(),str_repeat('x',1048577))['status']===413,'bounded payload');
foreach(['[]','{','{"id":"evt","event":"PAYMENT_RECEIVED","payment":{}}','{"id":"../evt","event":"PAYMENT_RECEIVED"}'] as $bad)check(!WebhookSecurity::validate(str_repeat('t',40),headers(),$bad)['ok'],'schema fails');
check(!WebhookSecurity::validate(str_repeat('t',40),headers()+['asaas-access-token'=>str_repeat('t',40)],eventBody())['ok'],'duplicate case-insensitive token header rejected');
check(!str_contains(json_encode($ok),'TEST_PAN')&&!str_contains(json_encode($ok),'TEST_CVV'),'no card details retained by validator');
[$gw,$a,$db,$i]=webhookFixture();
check(receive($a,$i)['http_status']===200,'durable receipt acknowledged');
check(count($db->tables['tblasaas_webhook_events'])===1,'one stored event');
check(!str_contains(json_encode($db->tables),'TEST_PAN')&&!str_contains(json_encode($db->tables),'TEST_CVV'),'no raw financial/customer/card payload in database');
check(receive($a,$i)['duplicated']===true&&count($db->tables['tblasaas_webhook_events'])===1,'duplicate event id durable');
check($a->handleWebhook(headers(),eventBody('evt_1','PAYMENT_CONFIRMED'),$i['public_id'])['http_status']===409,'event id reused for different meaning conflicts');
check(count($db->tables['tblasaas_webhook_events'])===1,'conflict preserves original');
check($a->handleWebhook(headers(str_repeat('x',40)),eventBody(),$i['public_id'])['http_status']===401,'unauthorized no ACK');
check($db->tables['tblasaas_webhook_integrations'][0]['rejection_count']===2,'rejection counter bounded and durable');
check(!str_contains(json_encode($a->webhookIntegrations()->listForAdmin()),'token_hash'),'admin listing masks digest');
check(!str_contains(json_encode($a->webhookIntegrations()->recentEvents(1)),'payload'),'admin event metadata excludes payload');
check(Client::$calls===[]&&$gw->receiptCalls===[],'ingress is persist-only no financial calls');
Client::$responses=[wallet(),canonicalPayment('RECEIVED'),customer()];
check(processOne($a)['processed'],'canonical known origin processes');
check(count($db->tables['tblinvoicepaymentrecords'])===1&&$gw->receiptCalls===[],'one receipt without generic gateway hooks or email');
check(array_column(Client::$calls,1)===['v3/wallets/','v3/payments/pay_original','v3/customers/cus_original'],'account payment and customer canonical GETs');
check($db->tables['tblasaas_webhook_events'][0]['verified_status']==='RECEIVED','canonical status persisted');
check(processOne($a)['duplicated']&&count($db->tables['tblinvoicepaymentrecords'])===1,'processed retry never duplicates');
receive($a,$i,'evt_2','PAYMENT_CONFIRMED');Client::$responses=[wallet(),canonicalPayment('RECEIVED'),customer()];processOne($a,2);
check($db->tables['tblasaas_webhook_events'][1]['process_status']==='processed','late confirmed reconciles the already proven receipt without duplicating it');
check(count($db->tables['tblinvoicepaymentrecords'])===1&&$db->tables['tblasaas_payments_map'][0]['status']==='RECEIVED','late confirmed cannot regress receipt or map');
receive($a,$i,'evt_3');Client::$responses=[wallet(),canonicalPayment('RECEIVED'),customer()];processOne($a,3);
check(count($db->tables['tblinvoicepaymentrecords'])===1,'new event id same payment still one receipt');

[$gw,$a,$db,$i]=webhookFixture();receive($a,$i,'evt_confirm','PAYMENT_CONFIRMED');Client::$responses=[wallet(),canonicalPayment('CONFIRMED'),customer()];processOne($a);
check($db->tables['tblasaas_webhook_events'][0]['verified_status']==='CONFIRMED' && count($db->tables['tblinvoicepaymentrecords'])===1, 'deployed confirmed settlement policy preserved');
check($db->tables['tblasaas_settlement_outbox'][0]['verified_status']==='CONFIRMED', 'confirmed receipt preserves exact status rather than implying available received');
receive($a,$i,'evt_received_after_confirm');Client::$responses=[wallet(),canonicalPayment('RECEIVED'),customer()];processOne($a,2);
check(count($db->tables['tblinvoicepaymentrecords'])===1 && $db->tables['tblasaas_webhook_events'][1]['verified_status']==='RECEIVED', 'received after confirmed preserves a single receipt and its distinct canonical observation');
foreach(['account','environment','issuer','remote_id','reference','amount','customer','draft','partial','removed'] as $case){
    [$gw,$a,$db,$i]=webhookFixture();receive($a,$i);$p=canonicalPayment('RECEIVED');$c=customer();
    if($case==='account')$db->tables['tblasaas_webhook_integrations'][0]['account_id']='other';
    if($case==='environment')$db->tables['tblasaas_webhook_integrations'][0]['environment']='production';
    if($case==='issuer')$db->tables['tblasaas_webhook_integrations'][0]['issuer_id']=1;
    if($case==='remote_id')$p['id']='pay_other';
    if($case==='reference')$p['externalReference']='other';
    if($case==='amount')$p['value']=99.99;
    if($case==='customer')$c['cpfCnpj']='22233344455';
    if($case==='draft')$db->tables['tblinvoices'][0]['status']=6;
    if($case==='partial')$db->tables['tblinvoicepaymentrecords'][]=['invoiceid'=>1,'transactionid'=>'manual','amount'=>50];
    if($case==='removed')$db->tables['tblasaas_payment_origins'][0]['state']='removed';
    Client::$responses=[wallet(),$p,$c];processOne($a);
    check($db->tables['tblinvoicepaymentrecords']===($case==='partial'?[['invoiceid'=>1,'transactionid'=>'manual','amount'=>50]]:[]),'mismatch cannot settle: '.$case);
    check($db->tables['tblasaas_webhook_events'][0]['process_status']==='review_required','mismatch durable review: '.$case);
}
[$gw,$a,$db,$i]=webhookFixture();receive($a,$i);Client::$responses=[wallet('foreign')];processOne($a);
check($gw->receiptCalls===[]&&$db->tables['tblasaas_webhook_events'][0]['process_status']==='failed','rotated credential foreign wallet fails safely');
check($db->tables['tblasaas_webhook_events'][0]['next_attempt_at']!==null,'failure has backoff');
check($a->processPendingWebhookEvents()['pending']===0,'cron respects retry schedule');
check(!str_contains(json_encode($db->tables),'fixture_sandbox'),'no key in errors');
[$gw,$a,$db,$i]=webhookFixture();$db->failInsertQuery=true;
throws(fn()=>receive($a,$i),'database failure cannot ACK');
check($db->tables['tblasaas_webhook_events']===[],'failed ingress not stored');
[$gw,$a,$db,$i]=webhookFixture();receive($a,$i);$db->lockAvailable=false;
check(!processOne($a)['processed']&&Client::$calls===[],'second worker lock fails before network');
$db->lockAvailable=true;Client::$responses=[wallet(),canonicalPayment('RECEIVED'),customer()];$db->failUpdate='tblasaas_payments_map';processOne($a);
check($db->tables['tblinvoicepaymentrecords']===[]&&$db->tables['tblinvoices'][0]['status']===1,'map failure rolls back financial transaction');
$db->failUpdate=null;Client::$responses=[wallet(),canonicalPayment('RECEIVED'),customer()];processOne($a);
check(count($db->tables['tblinvoicepaymentrecords'])===1,'retry after rollback writes exactly one receipt');
[$gw,$a,$db,$i]=webhookFixture();
check($a->handleWebhook(headers(str_repeat('l',40)),eventBody())['http_status']===200,'legacy authenticated endpoint persists');processOne($a);
check($db->tables['tblasaas_webhook_events'][0]['process_status']==='review_required'&&Client::$calls===[],'legacy scope never inferred for settlement');
$rotated=$a->webhookIntegrations()->rotateToken(1);
check($a->handleWebhook(headers(),eventBody(),$i['public_id'])['http_status']===401,'old token rejected immediately after rotation');
check($a->handleWebhook(headers($rotated['one_time_token']),eventBody(),$i['public_id'])['http_status']===200,'new token accepted for same immutable scope');
$a->webhookIntegrations()->disable(1);check($a->handleWebhook(headers($rotated['one_time_token']),eventBody(),$i['public_id'])['http_status']===401,'disabled scope closed');

[$gw,$a,$db,$i]=webhookFixture();$a->handleWebhook(headers(str_repeat('l',40)),eventBody());processOne($a);
$a->retryReviewedWebhook(1,1);
$row=$db->tables['tblasaas_webhook_events'][0];
check($row['scope_key']==='legacy'&&$row['integration_id']===1&&$row['reviewed_by']===7,'assisted retry preserves original scope and records actor');
check($row['process_status']==='received'&&Client::$calls===[],'assisted retry schedules verification, does not settle');
Client::$responses=[wallet(),canonicalPayment('RECEIVED'),customer()];processOne($a);check(count($db->tables['tblinvoicepaymentrecords'])===1,'assisted legacy can settle only after canonical scoped checks');
throws(fn()=>$a->retryReviewedWebhook(1,1),'completed event cannot be reopened');
[$gw,$a,$db,$i]=webhookFixture();$a->handleWebhook(headers(str_repeat('l',40)),eventBody());processOne($a);$db->tables['tblasaas_payment_origins']=[];
throws(fn()=>$a->retryReviewedWebhook(1,1),'unknown origin cannot be guessed in assisted retry');
check($db->tables['tblasaas_webhook_events'][0]['integration_id']===null,'failed assisted retry leaves original association untouched');
[$gw,$a,$db,$i]=webhookFixture();receive($a,$i);$db->failCommit=true;Client::$responses=[wallet(),canonicalPayment('RECEIVED'),customer()];processOne($a);
check($db->tables['tblinvoicepaymentrecords']===[],'commit failure rolls back fixture financial records');
$db->failCommit=false;Client::$responses=[wallet(),canonicalPayment('RECEIVED'),customer()];processOne($a);check(count($db->tables['tblinvoicepaymentrecords'])===1,'commit failure retry converges to one durable record');
[$gw,$a,$db,$i]=webhookFixture();receive($a,$i);
for($x=0;$x<10;$x++){Client::$responses=[new RuntimeException('SECRET_PROVIDER_MESSAGE')];processOne($a);}
check($db->tables['tblasaas_webhook_events'][0]['process_status']==='review_required','persistent canonical failure escalates visibly after bounded retries');
check(!str_contains(json_encode($db->tables),'SECRET_PROVIDER_MESSAGE'),'provider exceptions never persisted');
[$gw,$a,$db,$i]=webhookFixture();$scope=['issuer_id'=>null,'provider'=>'asaas','environment'=>'sandbox','account_id'=>'wallet_other'];
$j=$a->webhookIntegrations()->createVerified('Second',$scope);receive($a,$i);receive($a,$j);
check(count($db->tables['tblasaas_webhook_events'])===2,'same event id isolated across two integrations');
processOne($a,2);check($gw->receiptCalls===[]&&Client::$calls===[],'foreign integration cannot use known charge of original account');
[$gw,$a,$db,$i]=webhookFixture();Client::$responses=[wallet('unexpected')];
throws(fn()=>$a->registerWebhookIntegration('Wrong','sandbox','expected'),'integration registration verifies explicit wallet');
check(count($db->tables['tblasaas_webhook_integrations'])===1,'mismatched registration not saved');
Client::$responses=[wallet('expected')];$registered=$a->registerWebhookIntegration('Valid','sandbox','expected');
check($registered['account_id']==='expected'&&!isset($registered['token_hash']),'registration result safe metadata');
foreach([['status'=>'RECEIVED','deleted'=>true],['status'=>'RECEIVED','value'=>100.001],['status'=>'RECEIVED','dueDate'=>'2026-11-02'],['status'=>'REFUNDED'],['status'=>'PARTIALLY_REFUNDED'],['status'=>'REFUND_IN_PROGRESS']] as $change){
    [$gw,$a,$db,$i]=webhookFixture();receive($a,$i);Client::$responses=[wallet(),array_replace(canonicalPayment('RECEIVED'),$change),customer()];processOne($a);
    check($db->tables['tblinvoicepaymentrecords']===[] && $db->tables['tblasaas_webhook_events'][0]['process_status']==='review_required','deleted/inexact amount/date/refund cannot settle');
}
[$gw,$a,$db,$i]=webhookFixture();receive($a,$i);$db->tables['tblclients'][0]['vat']='12ABC34501DE35';
$c=customer();$c['cpfCnpj']='12abc34501de35';Client::$responses=[wallet(),canonicalPayment('RECEIVED'),$c];processOne($a);
check(count($db->tables['tblinvoicepaymentrecords'])===1,'alphanumeric CNPJ case-normalized without stripping letters');
[$gw,$a,$db,$i]=webhookFixture();receive($a,$i);$db->tables['tblclients'][0]['vat']='12ABC34501DE35';
$c=customer();$c['cpfCnpj']='12XYZ34501ZZ35';Client::$responses=[wallet(),canonicalPayment('RECEIVED'),$c];processOne($a);
check($gw->receiptCalls===[],'alphanumeric CNPJ collision cannot settle');
foreach(['nonTransactional','failBegin'] as $failure){
    [$gw,$a,$db,$i]=webhookFixture();receive($a,$i);$db->$failure=true;processOne($a);
    check($gw->receiptCalls===[]&&Client::$calls===[],'storage guard before network and mutation: '.$failure);
}
$money=new ReflectionMethod($a,'equalMoney');
check($money->invoke($a,'9999999999999.99','9999999999999.99'),'maximum decimal exact equality');
check(!$money->invoke($a,'9999999999999.99','9999999999999.98'),'one cent maximum decimal distinct');
[$gw,$a,$db,$i]=webhookFixture();receive($a,$i);Client::$responses=[wallet(),canonicalPayment('RECEIVED'),customer()];processOne($a);
check(count($db->tables['tblasaas_settlement_outbox'])===1&&$db->tables['tblasaas_settlement_outbox'][0]['state']==='pending','post-commit automation remains visibly pending');
check($db->tables['tblasaas_settlement_outbox'][0]['receipt_id']===$db->tables['tblinvoicepaymentrecords'][0]['id'],'outbox references exact receipt');
check(count($a->webhookIntegrations()->pendingAutomations())===1,'pending automation exposed to admin');
check(count($db->tables['tblsales_activity'])===1,'local audit committed with receipt');
foreach(['tblasaas_settlement_outbox','tblsales_activity'] as $table){
    [$gw,$a,$db,$i]=webhookFixture();receive($a,$i);$db->failInsert=$table;Client::$responses=[wallet(),canonicalPayment('RECEIVED'),customer()];processOne($a);
    check($db->tables['tblinvoicepaymentrecords']===[]&&$db->tables['tblasaas_settlement_outbox']===[],'audit/outbox failure rolls back receipt: '.$table);
}
[$gw,$a,$db,$i]=webhookFixture();receive($a,$i);$db->tables['tblcredits'][]=['invoice_id'=>1,'amount'=>'1.00'];Client::$responses=[wallet(),canonicalPayment('RECEIVED'),customer()];processOne($a);
check($db->tables['tblinvoicepaymentrecords']===[]&&$db->tables['tblasaas_webhook_events'][0]['process_status']==='review_required','applied credits prevent full auto-receipt');
[$gw,$a,$db,$i]=webhookFixture();receive($a,$i);$db->tables['tblcurrencies'][0]['name']='USD';Client::$responses=[wallet(),canonicalPayment('RECEIVED'),customer()];processOne($a);
check($db->tables['tblinvoicepaymentrecords']===[],'unsupported invoice currency cannot settle as BRL');
check(strlen($i['one_time_token'])===64&&!str_contains(json_encode($db->tables),$i['one_time_token']),'generated token never stored in plaintext');
$oldHash=$db->tables['tblasaas_webhook_integrations'][0]['token_hash'];$rotated=$a->webhookIntegrations()->rotateToken(1);
check(strlen($rotated['one_time_token'])===64&&$oldHash!==$db->tables['tblasaas_webhook_integrations'][0]['token_hash'],'regeneration creates fresh CSPRNG token');
check($db->tables['tblasaas_webhook_integrations'][0]['rotated_by']===7,'rotation actor recorded');
check(count($db->tables['tblasaas_webhook_admin_audit'])===2,'creation and rotation have append-only audit');
$prior=$db->tables['tblasaas_webhook_integrations'][0]['token_hash'];$db->failInsert='tblasaas_webhook_admin_audit';
throws(fn()=>$a->webhookIntegrations()->rotateToken(1),'audit failure aborts token rotation');
check($db->tables['tblasaas_webhook_integrations'][0]['token_hash']===$prior,'audit failure preserves working token');
// Provider examples use an ampersand in event IDs; it is data, never a URL.
[$gw,$a,$db,$i]=webhookFixture();
check(receive($a,$i,'evt_documented&123')['http_status']===200,'documented event identifier accepted without losing dedup identity');
Client::$responses=[wallet(),canonicalPayment('RECEIVED'),customer()];processOne($a);
check($db->tables['tblinvoicepaymentrecords'][0]['date']==='2026-01-15','delayed processing retains canonical payment date');
check($db->tables['tblinvoicepaymentrecords'][0]['paymentmethod']==='PIX','receipt retains canonical payment method');
check($db->tables['tblasaas_settlement_outbox'][0]['payment_date']==='2026-01-15','outbox retains same financial date');
foreach([null,'','2026-02-30','2026-2-01','1999-01-01','2999-01-01',12345] as $invalidDate){
    [$gw,$a,$db,$i]=webhookFixture();receive($a,$i);
    $p=canonicalPayment('RECEIVED');$p['paymentDate']=$invalidDate;$p['clientPaymentDate']='2026-01-15';$p['creditDate']='2026-01-16';
    Client::$responses=[wallet(),$p,customer()];processOne($a);
    check($db->tables['tblinvoicepaymentrecords']===[] && $db->tables['tblasaas_webhook_events'][0]['process_status']==='review_required','missing or invalid canonical payment date has no inferred fallback');
}
foreach(['manual','asaas'] as $mode){
    [$gw,$a,$db,$i]=webhookFixture();receive($a,$i);
    $db->tables['tblinvoicepaymentrecords'][]=['id'=>1,'transactionid'=>'pay_original','invoiceid'=>1,'amount'=>'100.00','paymentmode'=>$mode,'paymentmethod'=>'PIX','date'=>'2026-01-15'];
    Client::$responses=[wallet(),canonicalPayment('RECEIVED'),customer()];processOne($a);
    check($db->tables['tblasaas_webhook_events'][0]['process_status']==='review_required' && $db->tables['tblinvoices'][0]['status']===1 && $db->tables['tblasaas_settlement_outbox']===[],'matching transaction id without canonical provenance is not certified');
}
foreach(['invoice_id','origin_id','receipt_id','account_id','environment','payment_date','billing_type'] as $field){
    [$gw,$a,$db,$i]=webhookFixture();receive($a,$i);Client::$responses=[wallet(),canonicalPayment('RECEIVED'),customer()];processOne($a);
    $db->tables['tblasaas_settlement_outbox'][0][$field]='unrelated';
    receive($a,$i,'evt_after_corrupt_proof');Client::$responses=[wallet(),canonicalPayment('RECEIVED'),customer()];processOne($a,2);
    check($db->tables['tblasaas_webhook_events'][1]['process_status']==='review_required' && count($db->tables['tblinvoicepaymentrecords'])===1,'outbox provenance mismatch refuses certification: '.$field);
}
[$gw,$a,$db,$i]=webhookFixture();$GLOBALS['asaas_test_logs']=[];
Client::$responses=[new Asaas\Sdk\Exception\ApiException('TEST_SECRET_PROVIDER_BODY')];
throws(fn()=>$a->request('GET','/customers',['cpfCnpj'=>'TEST_PRIVATE_QUERY']),'API error stays failure');
check(!str_contains(json_encode($GLOBALS['asaas_test_logs']),'TEST_SECRET') && !str_contains(json_encode($GLOBALS['asaas_test_logs']),'TEST_PRIVATE'),'provider errors and query data never enter logs');
Client::$responses=[new Asaas\Sdk\Exception\ApiException('TEST_SECRET_PROVIDER_BODY')];
$envelope=$a->requestStructured('GET','/customers');
check(!$envelope['success'] && !str_contains(json_encode($envelope),'TEST_SECRET'),'structured errors preserve failure without provider body');
[$gw,$a,$db,$i]=webhookFixture();
check(hooks()->apply_filters('before_invoice_payment_insert_allowed',true,$db->tables['tblinvoices'][0],['invoiceid'=>1])===false,'real core filter requires canonical receipt for known origin');
$db->tables['tblasaas_payment_origins']=[];
check(hooks()->apply_filters('before_invoice_payment_insert_allowed',true,$db->tables['tblinvoices'][0],['invoiceid'=>1])===true,'legacy invoice without origin retains manual receipt eligibility');
check(hooks()->apply_filters('before_invoice_payment_insert_allowed',false,$db->tables['tblinvoices'][0],['invoiceid'=>1])===false,'module never overrides another writer veto');
[$gw,$a,$db,$i]=webhookFixture();
check(hooks()->apply_filters('before_invoice_credit_insert_allowed',true,$db->tables['tblinvoices'][0],['invoice_id'=>1])===false,'known remote origin forbids uncoordinated credit application');
receive($a,$i);Client::$responses=[wallet(),canonicalPayment('RECEIVED'),customer()];processOne($a);
foreach(['update','delete'] as $operation)check(hooks()->apply_filters('before_invoice_payment_mutation_allowed',true,1,1,$operation)===false,'canonical receipt immutable in generic '.$operation);
$db->tables['tblasaas_settlement_outbox'][0]['invoice_id']=999;
check(hooks()->apply_filters('before_invoice_payment_mutation_allowed',true,1,1,'delete')===false,'damaged invoice association cannot unprotect canonical receipt');
check(hooks()->apply_filters('before_invoice_payment_mutation_allowed',true,2,1,'delete')===true,'unrelated legacy receipt not broadly blocked');
check(hooks()->apply_filters('before_invoice_payment_mutation_allowed',false,2,1,'delete')===false,'pre-existing receipt mutation veto preserved');

foreach(['depth','raw','autocommit','chain'] as $outer){
    [$gw,$a,$db,$i]=webhookFixture();receive($a,$i);$before=$db->tables;
    if($outer==='depth')$db->_trans_depth=1;
    if($outer==='raw')$db->rawTransaction=true;
    if($outer==='autocommit')$db->autocommit=false;
    if($outer==='chain')$db->completionType=1;
    check(!processOne($a)['processed'] && Client::$calls===[] && $db->tables===$before,'worker refuses external transaction without mutation: '.$outer);
    check($db->rollbackCalls===0 && $db->locks===[],'worker never releases or rolls back caller transaction: '.$outer);
    throws(fn()=>receive($a,$i,'evt_outer'),'ingress cannot ACK uncommitted caller transaction: '.$outer);
    throws(fn()=>$a->webhookIntegrations()->rotateToken(1),'admin cannot commit caller transaction: '.$outer);
    throws(fn()=>$a->retryReviewedWebhook(1,1),'assisted retry cannot enter caller transaction: '.$outer);
    check($db->tables===$before && $db->rollbackCalls===0,'all external transaction attempts leave caller state unchanged: '.$outer);
}
[$gw,$a,$db,$i]=webhookFixture();$db->failCommit=true;
throws(fn()=>receive($a,$i),'ingress failed commit cannot ACK');
check($db->tables['tblasaas_webhook_events']===[],'ingress rollback preserves retry eligibility');
echo "OK: {$checks} webhook assertions; scoped auth, durable dedup, canonical reconciliation, no real network.\n";
