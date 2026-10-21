<?php
/** Actual HMVC routing and controller actions against PDO SQLite, with provider/effect probes only. */
declare(strict_types=1);
if(PHP_SAPI!=='cli'&&getenv('ASAAS_CHECKOUT_CONTROLLER_HTTP_FIXTURE')!=='1'){http_response_code(404);exit;}
define('BASEPATH',__DIR__);define('FCPATH',dirname(__DIR__).'/');define('APPPATH',FCPATH.'application/');define('APP_MODULES_PATH',FCPATH.'modules/');define('ADMIN_URI','admin');
class CI_Router{public $config;public $directory='';}
$CFG=new class{function item($name){return $name==='modules_locations'?[APP_MODULES_PATH=>'../../modules/']:null;}};
function log_message(...$args){}
require APPPATH.'third_party/MX/Router.php';
$checks=0;function scopeCheck($ok,string $message):void{global $checks;if(!$ok)throw new RuntimeException($message);$checks++;}
class ScopeHttpStop extends RuntimeException{function __construct(public int $status,public string $location=''){parent::__construct((string)$status);}}
function show_404(){throw new ScopeHttpStop(404);}function show_error($message,$status){throw new ScopeHttpStop($status);}function redirect($url,...$unused){$GLOBALS['scopeRedirect']=$url;}function db_prefix(){return 'tbl';}function site_url($path){return 'https://synthetic.invalid/'.$path;}
function check_invoice_restrictions($id,$hash){$GLOBALS['scopeRestrictions'][]=[$id,$hash];if($GLOBALS['scopeNativeRestrictionDenied']??false)show_404();}
function get_invoice_total_left_to_pay($id,$total){$GLOBALS['scopeFinancialReads'][]=$id;return (float)$total;}function set_alert(...$args){}
class ScopeInvoiceDb{
 public array $where=[],$sql=[],$hydrated=[];function __construct(public PDO $pdo){}
 function where($key,$value){$this->where[$key]=$value;return $this;}
 function get($table){if($table!=='tblinvoices')throw new LogicException('unexpected table');$sql='SELECT * FROM tblinvoices';$params=[];$conditions=[];foreach($this->where as $key=>$value){if(!in_array($key,['hash','issuer_id'],true))throw new LogicException('unexpected filter');if($value===null)$conditions[]=$key.' IS NULL';else{$conditions[]=$key.'=?';$params[]=$value;}}$this->where=[];if($conditions)$sql.=' WHERE '.implode(' AND ',$conditions);$this->sql[]=$sql;$s=$this->pdo->prepare($sql);$s->execute($params);$row=$s->fetch(PDO::FETCH_OBJ)?:null;if($row)$this->hydrated[]=(int)$row->id;return new class($row){function __construct(private $row){}function row(){return $this->row;}};}
}
class ScopeInput{public string $verb='GET';public array $posted=[];function method($upper=false){return $this->verb;}function post($key){return $this->posted[$key]??null;}}
class ScopeSession{public array $data=[];function userdata($key){return $this->data[$key]??null;}function set_userdata($key,$value){$this->data[$key]=$value;}function set_flashdata(...$x){}}
class ScopeGateway{
 public array $calls=[];function getSettingForOrigin($key,$origin){$this->calls[]=['origin_setting',$key,$origin['environment']];return '1';}function getSetting($key){$this->calls[]=['setting',$key];return '1';}function getEnvironmentLabel(){$this->calls[]=['environment'];return 'SANDBOX';}
 function get_charge($hash){$this->calls[]=['get_charge',$hash];return (object)['id'=>'pay_legacy','bankSlipUrl'=>'https://sandbox.asaas.com/b/pdf/legacy'];}
 function create_qrcode($id){$this->calls[]=['qrcode',$id];return json_encode(['encodedImage'=>'synthetic-image','payload'=>'synthetic-pix']);}
 function charge_credit_card($input){$this->calls[]=['hosted',$input];return json_encode(['id'=>'pay_legacy','billingType'=>'CREDIT_CARD','invoiceUrl'=>'https://sandbox.asaas.com/i/legacy']);}
 function getAdapter(){return new class{function originStore(){return new class{function byInvoice($id){return ['state'=>'issued','remote_id'=>'pay_legacy','environment'=>'sandbox','issuer_id'=>null,'provider'=>'asaas','channel'=>'api'];}};}};}
}
class ScopeLoader{public array $libraries=[],$helpers=[];function __construct(private $owner){}function library($name){$this->libraries[]=$name;if(!in_array($name,['asaas_gateway','asaas/asaas_gateway'],true))throw new LogicException('ambiguous module library');$this->owner->asaas_gateway=new ScopeGateway();}function helper($name){$this->helpers[]=$name;}}
#[AllowDynamicProperties]class ClientsController{public $db,$input,$session,$load,$asaas_gateway;public array $rendered=[],$effects=[];function __construct(){$this->load=new ScopeLoader($this);$this->db=$GLOBALS['scopeDb'];$this->input=new ScopeInput();$this->session=new ScopeSession();}function data($data){$this->rendered=$data;$this->effects[]='data';}function view($view){$this->effects[]='view:'.$view;}function layout(){$this->effects[]='layout';}function title($title){}function disableNavigation(){}function disableSubMenu(){}}
require FCPATH.'modules/asaas/controllers/Checkout.php';
$pdo=new PDO('sqlite::memory:');$pdo->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);$pdo->exec('CREATE TABLE tblinvoices(id INTEGER PRIMARY KEY,issuer_id INTEGER NULL,hash TEXT,status INTEGER,total TEXT,allowed_payment_modes TEXT)');$insert=$pdo->prepare('INSERT INTO tblinvoices VALUES(?,?,?,?,?,?)');foreach([[1,null,'legacy_hash'],[2,81,'company_a_hash'],[3,82,'company_b_hash'],[4,0,'invalid_zero_owner']] as [$id,$issuer,$hash])$insert->execute([$id,$issuer,$hash,1,'105.53',serialize(['asaas'])]);
function scopeController():Checkout{$GLOBALS['scopeDb']=new ScopeInvoiceDb($GLOBALS['pdo']);$GLOBALS['scopeRestrictions']=[];$GLOBALS['scopeFinancialReads']=[];$GLOBALS['scopeRedirect']=null;return new Checkout();}
if(PHP_SAPI!=='cli'){
 $segments=explode('/',trim((string)parse_url($_SERVER['REQUEST_URI']??'',PHP_URL_PATH),'/'));$r=new MX_Router();$r->config=$CFG;$located=$r->locate($segments);$action=$located[1]??'';$hash=$located[2]??'';$status=200;$c=null;
 if(($located[0]??'')!=='checkout'||!in_array($action,['index','boleto','cartao','qrcode'],true)||count($located)!==3)$status=404;
 else{$c=scopeController();try{$c->$action($hash);if($GLOBALS['scopeRedirect']!==null)$status=302;}catch(ScopeHttpStop $stop){$status=$stop->status;}}
 http_response_code($status);header('Content-Type: application/json');header('Cache-Control: no-store');if($GLOBALS['scopeRedirect']??null)header('Location: '.$GLOBALS['scopeRedirect']);
 echo json_encode(['status'=>$status,'invoice_id'=>$c->rendered['invoice']->id??null,'effects'=>$c->effects??[],'gateway_loads'=>$c->load->libraries??[],'gateway_calls'=>isset($c->asaas_gateway)?count($c->asaas_gateway->calls):0,'hydrated_ids'=>$c->db->hydrated??[],'financial_reads'=>$GLOBALS['scopeFinancialReads']??[]],JSON_THROW_ON_ERROR);exit;
}
foreach(['index','boleto','cartao','qrcode'] as $action){$r=new MX_Router();$r->config=$GLOBALS['CFG'];$located=$r->locate(explode('/','asaas/checkout/'.$action.'/company_a_hash'));scopeCheck(array_slice($located,0,2)===['checkout',$action]&&str_ends_with($r->directory,'/asaas/controllers/'),'actual HMVC routes '.$action.' to the protected module controller');
 foreach(['company_a_hash','company_b_hash','invalid_zero_owner','unknown_hash'] as $hash){$c=scopeController();$status=200;try{$c->$action($hash);}catch(ScopeHttpStop $stop){$status=$stop->status;}scopeCheck($status===404,'legacy '.$action.' uniformly denies named/invalid/unknown hash '.$hash);scopeCheck($c->rendered===[]&&$c->effects===[],'denied '.$action.' never passes invoice data into view/layout');scopeCheck($c->load->libraries===[]&&(!isset($c->asaas_gateway)||$c->asaas_gateway->calls===[]),'denied '.$action.' never initializes legacy gateway or reads its settings/transports');scopeCheck($GLOBALS['scopeFinancialReads']===[]&&$GLOBALS['scopeRestrictions']===[],'denied '.$action.' stops before native model/financial hydration');scopeCheck($c->db->hydrated===[],'SQL ownership predicate excludes named invoice before row hydration');}
 foreach(['../bad',str_repeat('a',129),null,[],17] as $hash){$c=scopeController();try{$c->$action($hash);scopeCheck(false,'malformed hash accepted');}catch(ScopeHttpStop $stop){scopeCheck($stop->status===404&&$c->db->sql===[],'malformed '.$action.' hash denied before query');}}
}
$c=scopeController();$c->index('legacy_hash');scopeCheck($c->load->libraries===['asaas/asaas_gateway'],'authorized NULL loads explicit module-qualified gateway');scopeCheck(($c->rendered['invoice']->id??null)===1&&in_array('view:asaas/payment',$c->effects,true),'authorized NULL keeps payment selector and invoice DTO');scopeCheck($GLOBALS['scopeRestrictions']===[[1,'legacy_hash']],'NULL selector retains native access restriction checks');scopeCheck($c->db->hydrated===[1]&&str_contains($c->db->sql[0],'issuer_id IS NULL'),'native query hydrates only explicit legacy NULL owner');
$c=scopeController();$c->boleto('legacy_hash');scopeCheck($GLOBALS['scopeRedirect']==='https://sandbox.asaas.com/b/pdf/legacy','authorized NULL billet transport/redirect still works');
$c=scopeController();$c->qrcode('legacy_hash');scopeCheck($GLOBALS['scopeFinancialReads']===[1]&&in_array('view:asaas/qrcode',$c->effects,true),'authorized NULL Pix retains amount/QR rendering');
$c=scopeController();$c->cartao('legacy_hash');scopeCheck(in_array('view:asaas/cartao',$c->effects,true)&&!array_filter($c->asaas_gateway->calls,fn($call)=>$call[0]==='hosted'),'authorized NULL hosted GET renders without charge creation');
$c=scopeController();$c->cartao('legacy_hash');$c->input->verb='POST';$c->input->posted=['hosted_card_csrf'=>$c->rendered['csrf_token'],'installmentCount'=>'1'];$c->cartao('legacy_hash');scopeCheck($GLOBALS['scopeRedirect']==='https://sandbox.asaas.com/i/legacy','authorized NULL hosted POST keeps CSRF/canonical redirect flow');
$GLOBALS['scopeNativeRestrictionDenied']=true;$c=scopeController();try{$c->index('legacy_hash');scopeCheck(false,'native restriction bypassed');}catch(ScopeHttpStop $stop){scopeCheck($stop->status===404&&$c->load->libraries===[]&&$c->effects===[],'native restriction denial precedes gateway settings and view for NULL');}
echo 'Asaas legacy checkout native SQL/HMVC/controller scope: '.$checks.' checks passed. No provider network.'.PHP_EOL;
