<?php
declare(strict_types=1);
require __DIR__.'/banco_brasil_store.php';
define('BASEPATH',__DIR__);
class BbDenied extends RuntimeException{} class BbHttp extends RuntimeException{} class BbRedirect extends RuntimeException{}
$GLOBALS['permissions']=['view'=>true,'manage'=>true,'import'=>true];$GLOBALS['issuerGrant']=true;$GLOBALS['issuerView']=true;
function staff_can($cap,$module){return $GLOBALS['permissions'][$cap]??false;}
function get_staff_user_id(){return 9;}function issuers_can_access($id,$actor,$cap){return $id===1&&$actor===9&&($cap==='view'?$GLOBALS['issuerView']:$GLOBALS['issuerGrant']);}
function access_denied($v){throw new BbDenied('denied');}function show_error($m,$s){throw new BbHttp((string)$s);}
function admin_url($p){return 'https://crm.example.test/admin/'.$p;}function set_alert($kind,$text){$GLOBALS['alerts'][]=[$kind,$text];}
function redirect($url){throw new BbRedirect($url);}function db_prefix(){return 't_';}
function html_escape($value){return htmlspecialchars((string)$value,ENT_QUOTES,'UTF-8');}
function init_head(){}function init_tail(){}function form_open($url){return '<form method="post" action="'.html_escape($url).'">';}
function form_open_multipart($url){return form_open($url);}function form_close(){return '</form>';}
class BbInput{public $verb='GET';public $posts=[];public $gets=[];function method(){return $this->verb;}function post($k=null){return $k===null?$this->posts:($this->posts[$k]??null);}function get($k){return $this->gets[$k]??null;}}
class BbSession{public $data=[];function userdata($k){return $this->data[$k]??null;}function set_userdata($k,$v){$this->data[$k]=$v;}}
class BbLoad{public $viewData;function helper($v){}function model($v){}function view($v,$data){$this->viewData=$data;}}
class AdminController{
 public $db,$input,$session,$security,$output,$load,$app_modules,$issuers_model;
 function __construct(){foreach((array)$GLOBALS['adminCI'] as $k=>$v)$this->$k=$v;}
}
$GLOBALS['adminCI']=(object)['db'=>new BbDb(),'input'=>new BbInput(),'session'=>new BbSession(),'security'=>new class{function get_csrf_token_name(){return 'core_csrf';}},
 'output'=>new class{public $headers=[];function set_header($v){$this->headers[]=$v;}},'load'=>new BbLoad(),
 'app_modules'=>new class{public $active=true;function is_active($v){return $this->active;}},
 'issuers_model'=>new class{public $row=['id'=>1,'legal_name'=>'EMPRESA SINTETICA','tax_id'=>'12ABC34501DE35'];function available($actor){return [$this->row];}function get($id){return $id===1?$this->row:null;}}];
require __DIR__.'/../modules/banco_brasil/controllers/Banco_brasil.php';
$n=0;$ci=$GLOBALS['adminCI'];$controller=new Banco_brasil();$controller->index();
check($ci->load->viewData['selected']===null,'no global issuer fallback');check(strlen($ci->session->data['bb_admin_csrf'])===64,'own CSRF established');
$csrf=$ci->session->data['bb_admin_csrf'];$ci->input->gets=['issuer_id'=>'1'];$controller->index();check($ci->load->viewData['selected']['tax_id']==='12ABC34501DE35','authorized issuer identity');
check($ci->load->viewData['can_manage']===true,'AND permissions allowed');
$GLOBALS['issuerGrant']=false;$controller->index();check($ci->load->viewData['can_manage']===false,'view does not imply manage');
$ci->input->verb='POST';$ci->input->posts=$data+['issuer_id'=>'1','csrf_bb'=>$csrf];rejects(fn()=>$controller->register_connection(),'admin without issuer grant blocked');check(!$ci->db->tables,'no write without grant');
$GLOBALS['issuerGrant']=true;$GLOBALS['permissions']['manage']=false;rejects(fn()=>$controller->register_connection(),'issuer grant lacks module manage');check(!$ci->db->tables,'no write without module permission');$GLOBALS['permissions']['manage']=true;
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
$ci->app_modules->active=false;$ci->input->gets=[];$controller->index();check($ci->load->viewData['issuers']===[],'missing issuer module consultative');$ci->app_modules->active=true;
$ci->input->gets=['issuer_id'=>'1'];$ci->issuers_model->row['legal_name']='<script>evil</script>';$controller->index();
extract($ci->load->viewData);ob_start();include __DIR__.'/../modules/banco_brasil/views/index.php';$html=ob_get_clean();
check(!str_contains($html,'<script>evil</script>')&&str_contains($html,'&lt;script&gt;evil&lt;/script&gt;'),'escaped issuer label');
foreach(['Autenticação','Ambiente','Convênio','Conta','Cobrança','Remessa','Retorno','Webhook'] as $label)check(str_contains($html,$label),'PTBR tab '.$label);
check(!str_contains($html,'name="client_secret"')&&!str_contains($html,'name="tax_id"'),'no secret or substitute identity field');
check(str_contains($html,'Nenhum pagamento será criado'),'financial impact visible');
$GLOBALS['permissions']['view']=false;rejects(fn()=>new Banco_brasil(),'constructor permission');
echo "OK {$n} Banco do Brasil admin checks\n";
