<?php
declare(strict_types=1);
require __DIR__.'/banco_brasil_store.php';
define('BASEPATH',__DIR__);
define('APPPATH',dirname(__DIR__).'/application/');
$GLOBALS['issuerCoreReady']=true;
$GLOBALS['issuerRows']=[
 1=>['id'=>1,'legal_name'=>'EMPRESA SINTETICA','tax_id'=>'12ABC34501DE35'],
 2=>['id'=>2,'legal_name'=>'SEGUNDA EMPRESA SINTETICA','tax_id'=>'11222333000181'],
];
$GLOBALS['resourceCompanies']=[1];
$GLOBALS['currentCompanyId']=1;
class BbDenied extends RuntimeException{} class BbHttp extends RuntimeException{} class BbRedirect extends RuntimeException{}
$GLOBALS['permissions']=['view'=>true,'manage'=>true,'import'=>true];$GLOBALS['issuerGrant']=true;$GLOBALS['issuerView']=true;$GLOBALS['resourceGrants']=['view'=>true,'manage'=>true,'import'=>true,'download'=>false];$GLOBALS['explicitMaster']=false;
function staff_can($cap,$module){return $GLOBALS['permissions'][$cap]??false;}
function get_staff_user_id(){return 9;}function issuers_can_access($id,$actor,$cap){throw new LogicException('generic permission must not be used');}
function issuer_resource_can($id,$actor,$feature,$cap){return in_array($id,$GLOBALS['resourceCompanies'],true)&&$actor===9&&$feature==='banco_brasil'&&($GLOBALS['explicitMaster']||(($cap==='view'?$GLOBALS['issuerView']:$GLOBALS['issuerGrant'])&&($GLOBALS['resourceGrants'][$cap]??false)));}
function access_denied($v){throw new BbDenied('denied');}function show_error($m,$s){throw new BbHttp((string)$s);}
function admin_url($p){return 'https://crm.example.test/admin/'.$p;}function set_alert($kind,$text){$GLOBALS['alerts'][]=[$kind,$text];}
function redirect($url){throw new BbRedirect($url);}function db_prefix(){return 't_';}
class IssuerCore { public static function ready($db,$prefix): bool { return $GLOBALS['issuerCoreReady']; } }
class IssuerRegistry {
 public function __construct($db,$prefix) {}
 public function available($actor): array { return array_values($GLOBALS['issuerRows']); }
 public function get($id,$actor): ?array { return $GLOBALS['issuerRows'][$id]??null; }
}
final class BbSelectedCompany { public function issuerId(): int { return $GLOBALS['currentCompanyId']; } }
class CompanyRequestScope {
 public static function context(){ return new BbSelectedCompany(); }
 public static function isolated(): bool { return false; }
 public static function sealedFor($controller): bool { return false; }
 public static function token(){ return 'synthetic-company-context'; }
}

function html_escape($value){return htmlspecialchars((string)$value,ENT_QUOTES,'UTF-8');}
function init_head(){}function init_tail(){}function form_open($url){$form='<form method="post" action="'.html_escape($url).'">';if(CompanyRequestScope::token()!==null&&str_starts_with($url,'https://crm.example.test/'))$form.='<input type="hidden" name="_company_context" value="'.html_escape(CompanyRequestScope::token()).'">';return $form;}
function form_open_multipart($url){return form_open($url);}function form_close(){return '</form>';}
class BbInput{public $verb='GET';public $posts=[];public $gets=[];function method(){return $this->verb;}function post($k=null){return $k===null?$this->posts:($this->posts[$k]??null);}function get($k){return $this->gets[$k]??null;}}
class BbSession{public $data=[];function userdata($k){return $this->data[$k]??null;}function set_userdata($k,$v){$this->data[$k]=$v;}}
class BbLoad{public $viewData;function helper($v){}function model($v){}function view($v,$data){$this->viewData=$data;}}
class AdminController{
 public $db,$input,$session,$security,$output,$load,$app_modules,$issuers_model,$app_menu;
 function __construct(){foreach((array)$GLOBALS['adminCI'] as $k=>$v)$this->$k=$v;}
}
$GLOBALS['adminCI']=(object)['db'=>new BbDb(),'input'=>new BbInput(),'session'=>new BbSession(),'security'=>new class{function get_csrf_token_name(){return 'core_csrf';}},
 'output'=>new class{public $headers=[];function set_header($v){$this->headers[]=$v;}},'load'=>new BbLoad(),
 'app_modules'=>new class{public $active=true;function is_active($v){return $this->active;}},
 'issuers_model'=>new class{public $row=['id'=>1,'legal_name'=>'EMPRESA SINTETICA','tax_id'=>'12ABC34501DE35'];function available($actor){return [$this->row];}function get($id){return $id===1?$this->row:null;}}];
require __DIR__.'/../modules/banco_brasil/controllers/Banco_brasil.php';
$n=0;$ci=$GLOBALS['adminCI'];$controller=new Banco_brasil();$controller->index();
check($ci->load->viewData['selected']['id']===1,'native company context selects the current company without an ID parameter');check(strlen($ci->session->data['bb_admin_csrf'])===64,'own CSRF established');
$csrf=$ci->session->data['bb_admin_csrf'];$ci->input->gets=['issuer_id'=>'1'];$controller->index();check($ci->load->viewData['selected']['tax_id']==='12ABC34501DE35','authorized issuer identity');
check($ci->load->viewData['can_manage']===true,'AND permissions allowed');
$GLOBALS['issuerGrant']=false;$controller->index();check($ci->load->viewData['can_manage']===false,'view does not imply manage');
$ci->input->verb='POST';$ci->input->posts=$data+['issuer_id'=>'1','csrf_bb'=>$csrf];rejects(fn()=>$controller->register_connection(),'admin without issuer grant blocked');check(!$ci->db->tables,'no write without grant');
$GLOBALS['issuerGrant']=true;$GLOBALS['permissions']['manage']=false;$controller->index();check($ci->load->viewData['can_manage']===true,'contextual grant not vetoed by global deny');check(!$ci->db->tables,'GET remains read only');$GLOBALS['permissions']['manage']=true;
$ci->input->verb='GET';try{$controller->register_connection();throw new LogicException('expected405');}catch(BbHttp $e){check($e->getMessage()==='405','POST only');}
$ci->input->verb='POST';$ci->input->posts['csrf_bb']='wrong';try{$controller->register_connection();throw new LogicException('expected403');}catch(BbHttp $e){check($e->getMessage()==='403','constant time own CSRF enforced');}
$ci->input->posts['csrf_bb']=[$csrf];rejects(fn()=>$controller->register_connection(),'array token refused');
$ci->input->posts['csrf_bb']=$csrf;$ci->input->posts['tax_id']='99999999999999';try{$controller->register_connection();}catch(BbRedirect $e){}
check(!$ci->db->tables,'cannot replace issuer CNPJ in form');unset($ci->input->posts['tax_id']);
try{$controller->register_connection();}catch(BbRedirect $e){check(str_contains($e->getMessage(),'issuer_id=1'),'scope retained after action');}
check(count($ci->db->tables['t_bb_connections'])===1,'real registration method persists');check($ci->db->tables['t_bb_connections'][0]['issuer_tax_id']==='12ABC34501DE35','identity from issuer registry');
check($ci->db->tables['t_bb_connections'][0]['lifecycle']==='pending_verification','no bank verification fabricated');
$ci->input->posts['issuer_id']='2';rejects(fn()=>$controller->register_connection(),'cross issuer POST');
$ci->input->gets=['issuer_id'=>'2'];rejects(fn()=>$controller->index(),'cross issuer GET');
$ci->input->gets=['issuer_id'=>['1']];rejects(fn()=>$controller->index(),'array issuer refused');
$ci->app_modules->active=false;$ci->input->gets=[];$controller->index();check($ci->load->viewData['selected']['id']===1,'BB company access uses the native core without the legacy issuers module');$ci->app_modules->active=true;
$GLOBALS['issuerCoreReady']=false;$controller->index();check($ci->load->viewData['issuers']===[],'unready native company core fails closed');$GLOBALS['issuerCoreReady']=true;
$ci->input->gets=['issuer_id'=>'1'];$GLOBALS['issuerRows'][1]['legal_name']='<script>evil</script>';$controller->index();
extract($ci->load->viewData);ob_start();include __DIR__.'/../modules/banco_brasil/views/index.php';$html=ob_get_clean();
check(!str_contains($html,'<script>evil</script>')&&str_contains($html,'&lt;script&gt;evil&lt;/script&gt;'),'escaped issuer label');
foreach(['Autenticação','Ambiente','Convênio','Conta','Cobrança','Remessa','Retorno','Webhook'] as $label)check(str_contains($html,$label),'PTBR tab '.$label);
check(!str_contains($html,'name="client_secret"')&&!str_contains($html,'name="tax_id"'),'no secret or substitute identity field');
check(substr_count($html,'name="_company_context"')===1,'connection form carries one company scope token');
check(str_contains($html,'aria-labelledby="bb-tab-auth"')&&str_contains($html,'aria-selected="true"'),'BB tabs expose selected state and panel labels');
check(str_contains($html,'Nenhum pagamento será criado'),'financial impact visible');
$ci->input->verb='POST';$ci->input->posts=['issuer_id'=>'1','connection_id'=>'1','layout_id'=>\BancoBrasil\LayoutCatalog::CNAB400,'csrf_bb'=>$csrf];
$GLOBALS['issuerGrant']=false;rejects(fn()=>$controller->register_layout(),'profile needs issuer manage grant');$GLOBALS['issuerGrant']=true;
$GLOBALS['permissions']['manage']=false;$controller->index();check($ci->load->viewData['can_manage']===true,'profile contextual grant overrides global deny');$GLOBALS['permissions']['manage']=true;
$ci->input->verb='GET';try{$controller->register_layout();throw new LogicException('expected405');}catch(BbHttp $e){check($e->getMessage()==='405','profile POST only');}$ci->input->verb='POST';
$ci->input->posts['csrf_bb']='bad';try{$controller->register_layout();throw new LogicException('expected403');}catch(BbHttp $e){check($e->getMessage()==='403','profile own CSRF');}$ci->input->posts['csrf_bb']=$csrf;
$ci->input->posts['environment']='production';try{$controller->register_layout();}catch(BbRedirect $e){}check(!isset($ci->db->tables['t_bb_layout_bindings']),'profile cannot alter connection environment');unset($ci->input->posts['environment']);
try{$controller->register_layout();}catch(BbRedirect $e){}check(count($ci->db->tables['t_bb_layout_bindings'])===1,'actual profile action inserts');
check($ci->db->tables['t_bb_layout_bindings'][0]['verification_state']==='declared_unverified','UI no homologation claim');
try{$controller->register_layout();}catch(BbRedirect $e){}check(count($ci->db->tables['t_bb_layout_bindings'])===1,'same profile idempotent');
$ci->input->posts['layout_id']=\BancoBrasil\Agreement::LAYOUT_ID;try{$controller->register_layout();}catch(BbRedirect $e){}
check(count($ci->db->tables['t_bb_layout_bindings'])===2,'second layout distinct new ID');
$controller->index();extract($ci->load->viewData);ob_start();include __DIR__.'/../modules/banco_brasil/views/index.php';$html=ob_get_clean();
check(str_contains($html,'name="layout_binding_id"')&&str_contains($html,'Selecione explicitamente'),'explicit import profile selector');
check(str_contains($html,'name="layout_id"')&&str_contains($html,'CNAB 400')&&str_contains($html,'CNAB 240'),'GUI profile registration both formats');
check(str_contains($html,'não contém evidência inequívoca')&&str_contains($html,'2000–2099'),'provenance and dates limitations visible');
check(!str_contains($html,'getenv(')&&!str_contains($html,'app-config'),'functional parameters in native GUI');
$GLOBALS['resourceGrants']['view']=false;$ci->input->gets=[];rejects(fn()=>$controller->index(),'selected company access requires resource view');
$ci->input->gets=['issuer_id'=>'1'];rejects(fn()=>$controller->index(),'resource view required on direct URL');$GLOBALS['resourceGrants']['view']=true;
$GLOBALS['resourceGrants']['manage']=false;$ci->input->posts['layout_id']=\BancoBrasil\LayoutCatalog::CNAB400;
rejects(fn()=>$controller->register_layout(),'module/global role does not inherit company resource manage');
$GLOBALS['resourceGrants']['import']=true;$controller->index();check($ci->load->viewData['can_manage']===false&&$ci->load->viewData['can_import']===true,'capabilities checked independently per resource');
$GLOBALS['resourceGrants']['import']=false;rejects(fn()=>$controller->import_return(),'resource import required before processing upload');
$GLOBALS['explicitMaster']=true;$controller->index();check($ci->load->viewData['can_manage']&&$ci->load->viewData['can_import'],'explicit master decision comes from issuer helper');$GLOBALS['explicitMaster']=false;
check(!str_contains(file_get_contents(__DIR__.'/../modules/banco_brasil/controllers/Banco_brasil.php'),"issuers_can_access("),'no generic permission fallback');
$GLOBALS['resourceGrants']['manage']=true;$GLOBALS['resourceGrants']['import']=true;
$GLOBALS['permissions']['view']=false;$controller2=new Banco_brasil();$controller2->index();check($ci->load->viewData['selected']['id']===1,'global view deny cannot veto contextual view');
$GLOBALS['resourceGrants']['view']=false;rejects(fn()=>$controller2->index(),'contextual view deny wins even when global role changes');$GLOBALS['resourceGrants']['view']=true;
$GLOBALS['permissions']['manage']=false;$ci->input->posts=['issuer_id'=>'1','connection_id'=>'1','layout_id'=>\BancoBrasil\LayoutCatalog::CNAB400,'csrf_bb'=>$csrf];
try{$controller2->register_layout();}catch(BbRedirect $e){}check(end($GLOBALS['alerts'])[0]==='success','actual contextual write succeeds despite global deny');
function &get_instance(){return $GLOBALS['adminCI'];}
function register_activation_hook(...$args){}function register_staff_capabilities(...$args){$GLOBALS['native_caps']=$args;}
function hooks(){return new class{function add_action(...$args){}};}
$ci->app_menu=new class{public $items=[];function add_sidebar_menu_item($key,$item){$this->items[$key]=$item;}};
require __DIR__.'/../modules/banco_brasil/banco_brasil.php';
banco_brasil_admin_init();check(isset($ci->app_menu->items['banco_brasil']),'contextual menu visible despite global deny');
check(isset($GLOBALS['native_caps'][1]['capabilities']['view'],$GLOBALS['native_caps'][1]['capabilities']['manage'],$GLOBALS['native_caps'][1]['capabilities']['import']),'native capability catalog retained');
$ci->app_menu->items=[];$GLOBALS['permissions']['view']=true;$GLOBALS['resourceGrants']['view']=false;banco_brasil_admin_init();check($ci->app_menu->items===[],'global allow cannot expose contextual menu');
$GLOBALS['explicitMaster']=true;banco_brasil_admin_init();check(isset($ci->app_menu->items['banco_brasil']),'explicit master from helper can see menu');$GLOBALS['explicitMaster']=false;

$GLOBALS['resourceGrants']['view']=true;$ci->input->gets=['issuer_id'=>'1'];
$ci->db=new class extends BbDb {public $protectedReads=0;function get($table){if($this->db_debug||$this->save_queries)throw new LogicException('diagnostics exposed');$this->protectedReads++;return false;}};
$failureController=new Banco_brasil();$failureController->index();check($ci->load->viewData['storage_error']===true,'query failure separate from no data or denied');
check($ci->db->protectedReads===1&&$ci->db->db_debug===true&&$ci->db->save_queries===true,'controller query guarded and diagnostics restored');
check($ci->load->viewData['connections']===[]&&$ci->load->viewData['can_manage']===false&&$ci->load->viewData['can_import']===false,'failed reads disable actions');
extract($ci->load->viewData);ob_start();include __DIR__.'/../modules/banco_brasil/views/index.php';$failedHtml=ob_get_clean();
check(str_contains($failedHtml,'Armazenamento indisponível')&&!str_contains($failedHtml,'SELECT '),'generic storage alert without SQL');
// Explicit A/B context tests: direct issuer IDs cannot escape the selected Companies record.
$ci->db=new BbDb();$GLOBALS['currentCompanyId']=2;$GLOBALS['resourceCompanies']=[1,2];
$ci->input->verb='GET';$ci->input->gets=[];$companyTwoController=new Banco_brasil();$companyTwoController->index();
check($ci->load->viewData['selected']['id']===2&&array_column($ci->load->viewData['issuers'],'id')===[2],'company B context lists and selects B only');
$ci->input->gets=['issuer_id'=>'1'];rejects(fn()=>$companyTwoController->index(),'authorized company A ID cannot bypass selected B context');
$ci->input->gets=[];$ci->input->verb='POST';$ci->input->posts=$data+['issuer_id'=>'1','csrf_bb'=>$csrf];
rejects(fn()=>$companyTwoController->register_connection(),'company A POST cannot escape selected B context');
$ci->input->posts=$data+['issuer_id'=>'2','csrf_bb'=>$csrf];try{$companyTwoController->register_connection();}catch(BbRedirect $e){check(str_contains($e->getMessage(),'issuer_id=2'),'company B remains immutable through connection action');}
check(count($ci->db->tables['t_bb_connections'])===1&&$ci->db->tables['t_bb_connections'][0]['issuer_id']===2&&$ci->db->tables['t_bb_connections'][0]['issuer_tax_id']==='11222333000181','new bank connection is bound to company B identity');
$GLOBALS['currentCompanyId']=1;
echo "OK {$n} Banco do Brasil admin checks\n";
