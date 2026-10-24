<?php
/** Real Efí controller methods with an injected synthetic SDK and storage; no network. */
declare(strict_types=1);
require __DIR__.'/operational_contract.php';
define('FCPATH',dirname(__DIR__,2).'/');
require FCPATH.'application/services/PaymentGatewayCatalog.php';
class App_Controller { public $db,$load,$input,$session,$invoices_model,$payments_model,$encryption; }
function db_prefix(){return 'tbl';}function get_option($name){return false;}
require FCPATH.'application/vendor/bainternet/php-hooks/php-hooks.php';
$GLOBALS['efiHooks']=new Hooks();
function hooks(){return $GLOBALS['efiHooks'];}

function check_invoice_restrictions($id,$hash){++$GLOBALS['restrictionChecks'];}
function is_staff_logged_in(){return $GLOBALS['admin'];}function is_admin(){return $GLOBALS['admin'];}
function show_error($message,$status=500){throw new DomainException('http_'.$status);}
function show_404(){throw new DomainException('http_404');}
require FCPATH.'modules/gerencianet_gateway/controllers/Gerencia_net.php';
class EfiControllerFixture extends Gerencia_net {
 public int $sdkCalls=0;public array $sdkResponse=[];public int $nativeWrites=0;
 public function boot(EfiFakeDb $db):void {
  $this->db=$db;$this->ci=$this;
  $this->gn_gateway=new class {function getSetting($key){return in_array($key,['active','test_mode_enabled'],true)?1:'';}function decryptSetting($key){return str_contains($key,'client_id')?'synthetic_client':'synthetic_secret';}};
  $this->session=new class {public array $data=['efi_checkout_csrf'=> 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa'];function userdata($key){return $this->data[$key]??null;}function set_userdata($key,$value){$this->data[$key]=$value;}};
  $this->input=new class {public string $verb='GET';public array $postData=[],$getData=[];function method($upper=false){return $upper?$this->verb:strtolower($this->verb);}function post($key){return $this->postData[$key]??null;}function get($key){return $this->getData[$key]??null;}};
  $this->load=new class {public array $views=[];function model($name){}function library($name){}function view($name){$this->views[]=$name;}};
  $this->invoices_model=new class($db){function __construct(private $db){}function get($id){return (object)($this->db->invoice+['token'=>'987','allowed_payment_modes'=>serialize(['gerencianet'])]);}};
  $this->payments_model=new class($db,$this){function __construct(private $db,private $controller){}function add($data){$data=hooks()->apply_filters('before_payment_recorded',$data);if(hooks()->apply_filters('before_invoice_payment_insert_allowed',true,$this->db->invoice,$data)!==true)return false;++$this->controller->nativeWrites;$this->db->receipts[]=$data+['id'=>81];return 81;}};
 }
 protected function client(array $options){++$this->sdkCalls;return new class($this->sdkResponse){function __construct(private $response){}function pixDetailCharge($params){return $this->response;}function detailCharge($params,$body){return $this->response;}};}
}
function controller(EfiFakeDb $db):EfiControllerFixture{$r=new ReflectionClass(EfiControllerFixture::class);$c=$r->newInstanceWithoutConstructor();$c->boot($db);return $c;}
$GLOBALS['admin']=false;$GLOBALS['restrictionChecks']=0;
$db=new EfiFakeDb();$l=new EfiChargeLedger($db,'tbl');$l->bind('pix',$provider,$options,$db->invoice);$c=controller($db);$c->sdkResponse=$paid;
ob_start();$c->callback_check_pix_pago($provider,17,'synthetic_hash');$output=ob_get_clean();check($output==='1'&&$c->sdkCalls===1&&$c->nativeWrites===1,'actual Pix callback uses SDK query then native writer');
ob_start();$c->callback_check_pix_pago($provider,17,'synthetic_hash');ob_end_clean();check($c->nativeWrites===1,'controller callback replay does not write twice');
foreach([1,2] as $issuer){$db->invoice['issuer_id']=$issuer;$calls=$c->sdkCalls;denied(fn()=>$c->callback_check_pix_pago($provider,17,'synthetic_hash'),'controller rejects foreign issuer');check($c->sdkCalls===$calls,'foreign issuer rejected before SDK');}$db->invoice['issuer_id']=null;
$calls=$c->sdkCalls;denied(fn()=>$c->callback_check_pix_pago($provider,17,'wrong'),'controller wrong hash');check($c->sdkCalls===$calls,'wrong hash before SDK');
$calls=$c->sdkCalls;denied(fn()=>$c->callback_check_pix_pago(str_repeat('b',26),17,'synthetic_hash'),'controller old unbound Pix');check($c->sdkCalls===$calls,'unbound before SDK');
denied(fn()=>$c->converterCertificado(),'anonymous converter denied');denied(fn()=>$c->fetch_pixes(),'anonymous reconciliation denied');
$GLOBALS['admin']=true;denied(fn()=>$c->converterCertificado(),'GET conversion denied');
$c->input->verb='POST';$c->input->postData=['efi_checkout_csrf'=>'wrong'];denied(fn()=>$c->converterCertificado(),'wrong conversion CSRF denied');
$calls=$c->sdkCalls;denied(fn()=>$c->gerar_pix_por_javascript(17,'synthetic_hash'),'wrong creation CSRF denied');check($c->sdkCalls===$calls,'CSRF denied before SDK');
check((new ReflectionMethod(Gerencia_net::class,'fetch_payment'))->isPrivate(),'internal fetch cannot be routed');
$db3=new EfiFakeDb();$ledger3=new EfiChargeLedger($db3,'tbl');$ledger3->bind('pix',$provider,$options,$db3->invoice);$c3=controller($db3);$c3->sdkResponse=$paid;
$mutate=static function($data){$data['amount']='9.00';return $data;};hooks()->add_filter('before_payment_recorded',$mutate);
denied(fn()=>$c3->callback_check_pix_pago($provider,17,'synthetic_hash'),'hook amount substitution vetoed before writer');
check($c3->nativeWrites===0&&$db3->receipts===[],'no receipt after mutation veto');hooks()->remove_filter('before_payment_recorded',$mutate);

echo "Efí actual controller synthetic SDK contracts: {$checks} cumulative checks PASS; no provider call.\n";
