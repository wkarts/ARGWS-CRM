<?php
require_once __DIR__.'/asaas_language_fixture.php';
/** Actual named-company controller with real service/cipher/SQLite and synthetic request/session. */
require __DIR__.'/asaas_positive.php';
require_once APPPATH.'services/CompanySettingsRuntime.php';
if(!defined('APP_BASE_URL'))define('APP_BASE_URL','https://crm.example.test/subdir/');
class AdminController {public $db,$load,$input,$session,$output,$config,$encryption;public function __construct(){}}
class CompanyRequestScope {public static function sealedFor($owner){return true;}public static function token(){return 'company-fixture-context';}public static function adminUrl($owner,$path){return 'https://crm.example.test/admin/'.$path;}}
function show_error($message,$status){$GLOBALS['controllerDenied']=$status;}
require APPPATH.'controllers/admin/Settings.php';
$controllerStart=$GLOBALS['essentialChecks'];
// Only this capability suite advances its disposable tracking; financial151/152/153 fixtures retain their own checkpoints.
\Asaas\CompanyCheckoutSchema::ensure($db,$p);unset($db->data_cache['table_names']);$secretControllerVersion=$db->query('SELECT installed_version FROM '.$p.'modules WHERE module_name=?',['asaas'])->row()->installed_version;$db->query('UPDATE '.$p.'modules SET installed_version=? WHERE module_name=?',['1.5.4','asaas']);
$controller=(new ReflectionClass(Settings::class))->newInstanceWithoutConstructor();$controller->db=$db;
$controller->input=new class{public string $verb='GET';public array $posted=[];public function method($upper){return $this->verb;}public function post($name=null,$clean=false){return $name===null?$this->posted:($this->posted[$name]??null);}public function get($name){return null;}};
$controller->session=new class{public array $data=[];public function userdata($key){return $this->data[$key]??null;}public function set_userdata($key,$value){$this->data[$key]=$value;}};
$controller->output=new class{public array $headers=[];public int $status=200;public function set_header($value){$this->headers[]=$value;}public function set_status_header($value){$this->status=$value;}};
$controller->config=new class{public function item($key){return $key==='index_page'?'index.php':null;}};
$controller->load=new class{public array $view=[];public function view($name,$data){$this->view=$data;}public function library($name){}};
$controller->encryption=new class($dec){public function __construct(private $decryptor){}public function decrypt($v){return ($this->decryptor)($v);}};
$GLOBALS['CI']->encryption=$nativeWebhookCipher;$GLOBALS['CI']->load=$controller->load;
$run=function($issuer=null,$actor=7)use($db,$p,$controller,$a){$issuer=$issuer??$a;$GLOBALS['controllerDenied']=null;$controller->load->view=[];return CompanySettingsRuntime::run($db,$p,$issuer,$actor,fn()=>$controller->index());};
$run();$csrf=$controller->session->data['company_asaas_webhook_csrf'];
essentialCheck(strlen($csrf)===64&&$controller->load->view['one_time']===null&&$controller->load->view['revealed_api_key']===null,'GET initializes CSRF without decrypting any secret');
essentialCheck(!str_contains(json_encode($controller->load->view),'token_cipher')&&!str_contains(json_encode($controller->load->view),'secret_cipher'),'full view DTO has no ciphertext');
essentialCheck(in_array('Cache-Control: no-store, private, max-age=0',$controller->output->headers,true)&&in_array('Referrer-Policy: no-referrer',$controller->output->headers,true),'all secret controller responses disable caching and referrers');
$dto=$asaas->accounts($a,7)[0];$controller->input->verb='POST';
foreach(['rotate','reveal_webhook','reveal_api_key'] as $action){$controller->input->posted=['action'=>$action,'account_row'=>$accounts[$a],'revision'=>$dto['revision'],'confirm'=>'1'];$run();essentialCheck($GLOBALS['controllerDenied']===403&&$controller->load->view===[],'missing CSRF blocks '.$action.' before rendering secret');}
$controller->input->posted=['action'=>'reveal_webhook','account_row'=>$accounts[$a],'revision'=>$dto['revision'],'company_asaas_webhook_csrf'=>$csrf];$run();
essentialCheck(strlen($controller->load->view['one_time']['one_time_token']??'')===64,'valid explicit POST can recover token through native encryption');$secret=$controller->load->view['one_time']['one_time_token'];
essentialCheck(!str_contains(json_encode($controller->session->data),$secret),'recovered token is not put in session');
$run();essentialCheck($GLOBALS['controllerDenied']===403&&$controller->load->view===[],'replayed reveal CSRF is rejected');
$controller->input->posted['company_asaas_webhook_csrf']=$controller->session->data['company_asaas_webhook_csrf'];$controller->input->posted['action']='rotate';unset($controller->input->posted['confirm']);$run();essentialCheck($GLOBALS['controllerDenied']===403,'rotation requires explicit revocation confirmation');
$controller->input->posted['action']='reveal_api_key';$run();essentialCheck($controller->load->view['revealed_api_key']['api_key']===$dec($db->query('SELECT secret_cipher FROM '.$p.'asaas_company_accounts WHERE id=?',[$accounts[$a]])->row()->secret_cipher),'API key recovery uses exact account ciphertext');
$controller->input->posted['company_asaas_webhook_csrf']=$controller->session->data['company_asaas_webhook_csrf'];$run($b);essentialCheck($controller->load->view['revealed_api_key']===null,'same numeric account cannot leak into another active company');
$db->query('INSERT INTO '.$p.'staff(staffid,active,admin) VALUES(21,1,0)');$db->query('INSERT INTO '.$p.'issuers_staff(issuer_id,staff_id,can_view) VALUES(?,21,1)',[$a]);$db->query('INSERT INTO '.$p.'issuers_resource_permissions(issuer_id,staff_id,feature,capability) VALUES(?,21,?,?)',[$a,'invoices','view']);
$controller->input->posted['company_asaas_webhook_csrf']=$controller->session->data['company_asaas_webhook_csrf'];$run($a,21);essentialCheck($controller->load->view['revealed_api_key']===null&&!$controller->load->view['can_webhook'],'active company non-admin cannot recover a secret through the controller');
$db->query('INSERT INTO '.$p.'staff(staffid,active,admin) VALUES(22,1,1)');
$db->query('INSERT INTO '.$p.'issuers_staff(issuer_id,staff_id,can_view) VALUES(?,22,1)',[$a]);
foreach(['view','edit'] as $capability)$db->query('INSERT INTO '.$p.'issuers_resource_permissions(issuer_id,staff_id,feature,capability) VALUES(?,22,?,?)',[$a,'settings',$capability]);
essentialCheck(!(new IssuerResourceAccess($db,$p))->administrative($a,22),'secondary credential exception does not expand general administrative ACL');
$controller->input->posted=['action'=>'reveal_webhook','account_row'=>$accounts[$a],'revision'=>$dto['revision'],'company_asaas_webhook_csrf'=>$controller->session->data['company_asaas_webhook_csrf']];$run($a,22);
essentialCheck($controller->load->view['can_webhook']&&($controller->load->view['one_time']['one_time_token']??null)===$secret,'actual controller admits scoped non-master secondary admin for token recovery');
$controller->input->posted['action']='reveal_api_key';$controller->input->posted['company_asaas_webhook_csrf']=$controller->session->data['company_asaas_webhook_csrf'];$run($a,22);
essentialCheck(($controller->load->view['revealed_api_key']['api_key']??null)===$dec($db->query('SELECT secret_cipher FROM '.$p.'asaas_company_accounts WHERE id=?',[$accounts[$a]])->row()->secret_cipher),'actual controller admits scoped non-master secondary admin for API key recovery');
$controller->input->verb='GET';$run();essentialCheck($controller->load->view['one_time']===null&&$controller->load->view['revealed_api_key']===null,'subsequent GET forgets response-only secrets');
echo 'Company secret controller: '.($GLOBALS['essentialChecks']-$controllerStart).' added checks; '.$GLOBALS['essentialChecks'].' cumulative passed.'.PHP_EOL;

$db->query('UPDATE '.$p.'modules SET installed_version=? WHERE module_name=?',[$secretControllerVersion,'asaas']);
