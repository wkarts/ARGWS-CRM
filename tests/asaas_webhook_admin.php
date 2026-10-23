<?php
declare(strict_types=1);
define('BASEPATH', __DIR__);
define('APP_BASE_URL', 'https://crm.example.test/subdir/');
require __DIR__ . '/../modules/asaas/libraries/Asaas/WebhookAdminPresentation.php';
use Asaas\WebhookAdminPresentation as Presentation;
$checks=0;
function check($condition,$message) { global $checks; ++$checks; if (!$condition) throw new RuntimeException($message); }
function fails($callable,$message) { try {$callable();} catch(Throwable $e) {check($e->getMessage()===$message,'Expected '.$message.', got '.$e->getMessage());return;} check(false,'Missing error '.$message); }
$id=str_repeat('a',32);
check(Presentation::callbackUrl(APP_BASE_URL,$id)==='https://crm.example.test/subdir/asaas/gateways/callback/integration/'.$id,'configured base subpath');
check(Presentation::companyCallbackUrl(APP_BASE_URL,$id,'index.php')==='https://crm.example.test/subdir/index.php/company_asaas_callback/integration/'.$id,'company callback uses trusted base and index');
check(Presentation::callbackUrl(APP_BASE_URL,$id,'index.php')==='https://crm.example.test/subdir/index.php/asaas/gateways/callback/integration/'.$id,'configured index page');
$_SERVER['HTTP_HOST']='attacker.test'; $_SERVER['HTTP_X_FORWARDED_HOST']='attacker.test';
check(!str_contains(Presentation::callbackUrl(APP_BASE_URL,$id),'attacker'),'never trusts request host');
foreach (['http://crm.test/','//crm.test/','https://user:pass@crm.test/','https://crm.test/?token=x','https://crm.test/#x','https://crm.test/a/../','https://crm.test/a/%2e%2e/','https://crm.test/ space','https://crm.test/\\evil'] as $base) {
    check(Presentation::callbackUrl($base,$id)===null,'invalid configured URL refused');
}
check(Presentation::callbackUrl(APP_BASE_URL,'../bad')===null,'invalid public ID');
check(Presentation::callbackUrl(APP_BASE_URL,$id,'../index.php')===null,'invalid index path');
$safe=Presentation::eventRows([['event_id'=>'evt_1','event_type'=>'PAYMENT_RECEIVED','process_status'=>'failed','error_message'=>'secret','payload'=>'secret','token_hash'=>'secret','webhook_token'=>'secret']]);
check(!str_contains(json_encode($safe),'secret'),'only event metadata whitelisted');

class AdminController { public $load,$input,$session,$config,$asaas_gateway,$output; public function __construct() {} }
class InputStub { public $method='GET',$post=[],$get=[]; function method($upper=false){return $upper?$this->method:strtolower($this->method);} function post($key=null){return $key===null?$this->post:($this->post[$key]??null);} function get($key){return $this->get[$key]??null;} }
class SessionStub { public $values=[],$flash=[]; function flashdata($key){return $this->flash[$key]??null;} function userdata($key){return $this->values[$key]??null;} function set_userdata($key,$value){$this->values[$key]=$value;} }
class OutputStub { public $headers=[]; function set_header($header){$this->headers[]=$header;return $this;} }
class ConfigStub { function item($key){return '';} }
class LoadStub { public $data=[]; function view($name,$data){$this->data=$data;} }
class StoreStub { public $ready=true,$calls=[]; function ready(){return $this->ready;} function listForAdmin(){return [['id'=>1,'public_id'=>str_repeat('a',32),'label'=>'<script>name</script>','issuer_id'=>null,'provider'=>'asaas','account_id'=>'wallet_test','environment'=>'sandbox','enabled'=>1,'last_valid_at'=>null,'last_rejected_at'=>null,'rejection_count'=>0,'rotated_at'=>null,'token_hash'=>'NEVER_RENDER_TOKEN_HASH']];} function recentEvents($id,$limit){$this->calls[]=['events',$id,$limit];return [['event_id'=>'evt_1','event_type'=>'PAYMENT_RECEIVED','process_status'=>'failed','verified_status'=>'','received_at'=>'2026-10-18','processed_at'=>null,'attempt_count'=>1,'payload'=>'NEVER_RENDER_PAYLOAD']];} function reviewQueue($limit){return [['id'=>7,'event_id'=>'evt_old','event_type'=>'PAYMENT_RECEIVED','asaas_payment_id'=>'pay_old','process_status'=>'review','received_at'=>'2026-10-18','integration_id'=>null]];} function pendingAutomations($limit){return [['id'=>2,'invoice_id'=>3,'state'=>'pending']];} function rotateToken($id){$this->calls[]=['rotate',$id];return ['integration_id'=>$id,'one_time_token'=>str_repeat('e',64)];} function revealToken($id){$this->calls[]=['reveal',$id];return ['integration_id'=>$id,'one_time_token'=>str_repeat('e',64),'revealed'=>true];} function disable($id){$this->calls[]=['disable',$id];} }
class AdapterStub { public $store,$calls=[]; function __construct(){$this->store=new StoreStub();} function webhookIntegrations(){return $this->store;} function registerWebhookIntegration(...$args){$this->calls[]=['register',...$args];return ['id'=>1,'one_time_token'=>str_repeat('d',64)];} function retryReviewedWebhook(...$args){$this->calls[]=['review',...$args];} }
class GatewayStub { public $adapter; function __construct(){$this->adapter=new AdapterStub();} function getAdapter(){return $this->adapter;} }
$GLOBALS['admin']=true; $GLOBALS['alerts']=[];
function is_admin(){return $GLOBALS['admin'];} function access_denied($reason){throw new RuntimeException('denied');} function show_404(){throw new RuntimeException('404');} function show_error($message,$code){throw new RuntimeException((string)$code);} function set_alert($type,$message){$GLOBALS['alerts'][]=[$type,$message];} function redirect($url){$GLOBALS['redirect']=$url;} function admin_url($path=''){return 'https://crm.example.test/admin/'.$path;}
require __DIR__.'/../modules/asaas/controllers/Asaas.php';
function controller(){ $c=(new ReflectionClass(Asaas::class))->newInstanceWithoutConstructor(); $c->input=new InputStub();$c->session=new SessionStub();$c->config=new ConfigStub();$c->load=new LoadStub();$c->output=new OutputStub();$c->asaas_gateway=new GatewayStub();return $c; }
$GLOBALS['admin']=false;fails(fn()=>new Asaas(),'denied');$GLOBALS['admin']=true;
$c=controller();$c->index();
check(strlen($c->load->data['csrf_token'])===64,'cryptographic session token created');
check($c->load->data['trusted_url_ready'],'trusted base recognized');
check($c->load->data['selected_id']===1,'select first integration for event metadata');
check($c->asaas_gateway->adapter->calls===[],'GET performs no remote registration/verification');
check(!str_contains(json_encode($c->load->data['events']),'NEVER_RENDER_PAYLOAD'),'controller scrubs event payload');
$c->input->get=['integration'=>'999'];fails(fn()=>$c->index(),'404');
$c->input->get=['integration'=>['1']];fails(fn()=>$c->index(),'404');
$c->input->get=[];
foreach (['register_integration','rotate_integration_token','reveal_integration_token','disable_integration','retry_reviewed_event'] as $method) {
    $c=controller();$GLOBALS['admin']=false;fails(fn()=>$c->$method(),'denied');$GLOBALS['admin']=true;
    fails(fn()=>$c->$method(),'404');
    $c->input->method='POST';fails(fn()=>$c->$method(),'403');
    $c->session->values['asaas_webhook_admin_csrf']=str_repeat('c',64);$c->input->post=['webhook_admin_csrf'=>'wrong'];fails(fn()=>$c->$method(),'403');
    $c->input->post=['webhook_admin_csrf'=>str_repeat('c',64)];$c->asaas_gateway->adapter->store->ready=false;fails(fn()=>$c->$method(),'503');
    check($c->asaas_gateway->adapter->calls===[] && $c->asaas_gateway->adapter->store->calls===[],'guard runs before mutations');
}
$c=controller();$c->input->method='POST';$c->session->values['asaas_webhook_admin_csrf']=str_repeat('c',64);
$basePost=['webhook_admin_csrf'=>str_repeat('c',64),'label'=>'Local sandbox','environment'=>'sandbox','account_id'=>'wallet_test'];
$c->input->post=$basePost;$c->register_integration();check(count($c->asaas_gateway->adapter->calls)===1,'registration dispatched once');
check(count($c->asaas_gateway->adapter->calls[0])===4,'registration never accepts a token');
check($c->load->data['generated_token']===str_repeat('d',64),'generated token only in protected POST response');
check(!str_contains(json_encode($c->session->values),str_repeat('d',64)),'generated token never persisted in session');
check(!str_contains(json_encode($GLOBALS['alerts']),str_repeat('d',64)),'secret not flashed');
check(in_array('Cache-Control: no-store, private, max-age=0',$c->output->headers,true),'secret response no-store');
fails(fn()=>$c->register_integration(),'403');
$c->input->post=$basePost+['integration_id'=>'1'];$c->input->post['webhook_admin_csrf']=$c->session->values['asaas_webhook_admin_csrf'];
$c->rotate_integration_token();check(in_array(['rotate',1],$c->asaas_gateway->adapter->store->calls,true),'rotation local without external token');
check($c->load->data['generated_token']===str_repeat('e',64),'rotation returns new token');
$c->index();check($c->load->data['generated_token']===null,'subsequent GET never recovers generated token');
$c->input->post['webhook_admin_csrf']=$c->session->values['asaas_webhook_admin_csrf'];$c->reveal_integration_token();check($c->load->data['generated_token']===str_repeat('e',64)&&$c->load->data['token_revealed'],'explicit protected POST recovers token');
$c->input->post['webhook_admin_csrf']=$c->session->values['asaas_webhook_admin_csrf'];
$c->disable_integration();check(in_array(['disable',1],$c->asaas_gateway->adapter->store->calls,true),'disable local');
$c->input->post+=['event_row_id'=>'7'];$c->retry_reviewed_event();check($c->asaas_gateway->adapter->calls[1]===['review',7,1],'review explicit event/integration');
$c->input->post['integration_id']='1e1';$before=count($c->asaas_gateway->adapter->store->calls);$c->disable_integration();check(count($c->asaas_gateway->adapter->store->calls)===$before,'malformed ID rejected');
foreach (['webhook_token','one_time_token','token'] as $field) {
  $c->input->post=[$field=>['malformed'],'webhook_admin_csrf'=>$c->session->values['asaas_webhook_admin_csrf']];
  fails(fn()=>$c->register_integration(),'400');
}
foreach (['setup_webhook','set_webhook','set_webhook_invoice','set_webhook_transfer'] as $method) {fails(fn()=>$c->$method(),'404');}

function init_head(){$GLOBALS['headCalls']=($GLOBALS['headCalls']??0)+1;} function init_tail(){$GLOBALS['tailCalls']=($GLOBALS['tailCalls']??0)+1;} function html_escape($value){return htmlspecialchars((string)$value,ENT_QUOTES,'UTF-8');} function form_open($url,$attrs=[]){return '<form method="post" action="'.html_escape($url).'">';} function form_close(){return '</form>';}
$c=controller();$c->index();extract($c->load->data);ob_start();require __DIR__.'/../modules/asaas/views/webhooks.php';$html=ob_get_clean();
check(str_contains($html,'&lt;script&gt;name&lt;/script&gt;') && !str_contains($html,'<script>name</script>'),'untrusted label escaped');
check(!str_contains($html,'NEVER_RENDER_TOKEN_HASH') && !str_contains($html,'NEVER_RENDER_PAYLOAD'),'view never renders secrets/payload');
check(str_contains($html,'Não verificado'),'remote status not inferred');
check(str_contains($html,'Copiar URL') && str_contains($html,'readonly'),'URL read-only and copyable');
preg_match_all('/<form[^>]*>(.*?)<\/form>/s',$html,$forms);
check(count($forms[0])===4,'create/rotate/disable/review forms');
foreach($forms[0] as $form){check(str_contains($form,'method="post"')&&str_contains($form,'webhook_admin_csrf'),'own token even with global CSRF disabled');check(substr_count($form,'<form')===1,'no nested forms');}
check(!str_contains($html,'name="webhook_token"'),'no arbitrary token inputs');
check(str_contains($html,'GERAR token') && str_contains($html,'REGERAR token'),'generation actions explicit');
check(!str_contains($html,'id="generated-webhook-token"'),'GET has no token field');
$generated_token=str_repeat('f',64);$generated_id=1;ob_start();require __DIR__.'/../modules/asaas/views/webhooks.php';$postHtml=ob_get_clean();
check((bool)preg_match('/<input[^>]*id="generated-webhook-token"[^>]*type="password"[^>]*readonly[^>]*>/', $postHtml),'generated token readonly and masked');
check(str_contains($postHtml,'Copiar token') && str_contains($postHtml,'Revelar'),'explicit copy/reveal');
check(str_contains($postHtml,'cópia cifrada'),'encrypted recovery storage disclosed');
check(!str_contains($html,'setup_webhook'),'no old remote mutation button');
check(\Asaas\WebhookAdminPresentation::statusLabel('observed')==='Observado, sem baixa','observation status localized without implying receipt');
check(str_contains($html,'Homologação')&&!str_contains($html,'Homologação [sandbox]')&&!str_contains($html,'>Sandbox<'),'consistent environment labels');
// Partial rendering must preserve admin checks/CSRF while omitting the duplicated shell.
function get_instance(){return $GLOBALS['webhookPanelCi'];}
$c=controller();$c->input->get=['panel'=>'1'];$c->index();
check($c->load->data['embedded_panel']===true&&$c->load->data['generated_token']===null,'explicit panel GET renders no recovered token');
$GLOBALS['webhookPanelCi']=(object)['session'=>$c->session];$c->session->flash['message-warning']='<script>synthetic-alert</script>';
extract($c->load->data);$headBefore=$GLOBALS['headCalls'];$tailBefore=$GLOBALS['tailCalls'];
ob_start();require __DIR__.'/../modules/asaas/views/webhooks.php';$panelHtml=ob_get_clean();
check($GLOBALS['headCalls']===$headBefore&&$GLOBALS['tailCalls']===$tailBefore,'embedded view never initializes a second shell');
check(str_contains($panelHtml,'data-asaas-operation-content')&&!str_contains($panelHtml,'<script>'),'embedded marker present and no inline scripts execute');
check(str_contains($panelHtml,'&lt;script&gt;synthetic-alert&lt;/script&gt;'),'partial alerts are escaped');
check(str_contains($panelHtml,'webhook_admin_csrf')&&!str_contains($panelHtml,'id="generated-webhook-token"'),'partial retains action CSRF without hydrating a token');
$c->input->get=['panel'=>'unexpected'];$c->index();check($c->load->data['embedded_panel']===false,'only explicit panel1 suppresses shell');
echo "Asaas webhook admin: {$checks} checks passed\n";
