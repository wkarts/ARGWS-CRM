<?php
/** Actual Staff controller/helper/service and native partial; only framework/session/core writer I/O are doubles. */
require __DIR__.'/issuers_staff_access.php';
$nativeBaseline=$checks;
define('BASEPATH',__DIR__);define('APPPATH',dirname(__DIR__).'/application/');define('ISSUERS_MODULE_NAME','issuers');
#[AllowDynamicProperties] class AdminController {}
class StaffRedirect extends RuntimeException {}
function &get_instance(){return $GLOBALS['staffCI'];}
function db_prefix(){return $GLOBALS['prefix'];}
function get_staff_user_id(){return $GLOBALS['staffActor'];}
function _l($key,...$args){return $GLOBALS['lang'][$key]??$key;}
function html_escape($value){return htmlspecialchars((string)$value,ENT_QUOTES,'UTF-8');}
function admin_url($path){return '/admin/'.$path;}
function redirect($path){throw new StaffRedirect($path);}
function set_alert($type,$message){$GLOBALS['staffAlerts'][]=[$type,$message];}
function log_message($level,$message){$GLOBALS['staffLogs'][]=$message;}
function staff_cant($cap,$feature){return false;} // Global permission deliberately cannot grant company ACL.
function access_denied($feature){throw new DomainException('native_access_denied');}
function nl2br_save_html($value){return $value;}
function handle_staff_profile_image_upload(...$args){$GLOBALS['imageCalls']++;}
function hooks(){return new class{function do_action(...$args){}};}
function get_available_staff_permissions(){return ['banco_brasil'=>['name'=>'Banco <synthetic>','capabilities'=>['view'=>'Consultar','manage'=>'Gerenciar','import'=>'Importar']],'pige_bridge'=>['name'=>'Ponte','capabilities'=>['view'=>'Consultar','edit'=>'Editar']],'customers'=>['name'=>'Clientes','capabilities'=>['view_own'=>['name'=>'Próprios','not_applicable'=>true],'view'=>'Consultar','edit'=>'Editar']]];}
require __DIR__.'/../application/language/portuguese_br/portuguese_br_lang.php';
require __DIR__.'/../application/helpers/issuers_helper.php';
require __DIR__.'/../application/controllers/admin/Staff.php';
$staffActor=4;$staffAlerts=[];$staffLogs=[];$imageCalls=0;
$session=new class{public array $data=[];function userdata($key){return $this->data[$key]??null;}function set_userdata($key,$value){$this->data[$key]=$value;}};
$loader=new class{function model($name){} function view($name,$data){extract($data);require __DIR__.'/../application/views/admin/issuers/staff_access.php';}};
$staffCI=(object)['db'=>$db,'session'=>$session,'load'=>$loader];
$db->query("CREATE TABLE {$prefix}options(name VARCHAR(100) PRIMARY KEY,value TEXT)");
$db->query("INSERT INTO {$prefix}options VALUES('companyname','Synthetic primary'),('company_vat','12345678000195')");
$db->query("CREATE TABLE {$prefix}staff_permissions(staff_id INT NOT NULL,feature VARCHAR(100) NOT NULL,capability VARCHAR(100) NOT NULL)");
$db->query("CREATE TABLE {$prefix}modules(module_name VARCHAR(100) PRIMARY KEY,active INT NOT NULL)");
IssuerCore::reconcile($db,$prefix);
$token=issuers_staff_form_token();same(64,strlen($token),'staff form issues session token');
$model=new class($db,$prefix){public array $calls=[];public $response=true;public function __construct(private $db,private $prefix){}function add($data){$this->calls[]=['add',$data];$this->db->query("INSERT INTO {$this->prefix}staff VALUES(50,0,1,'Synthetic','New')");return 50;}function update($data,$id){$this->calls[]=['update',$data,$id];return $this->response;}function update_profile($data,$id){$this->calls[]=['profile',$data,$id];return $this->response;}};
$controller=new Staff();$controller->load=$loader;$controller->staff_model=$model;
$input=new class{public array $data=[];function post($key=null,$clean=null){return $key===null?$this->data:($this->data[$key]??'');}};$controller->input=$input;
function nativeStaffPost($data,$target='3',$personal=false){global$controller,$input;$input->data=array_merge(['email_signature'=>'','password'=>''],$data);try{if($personal)$controller->edit_profile();else$controller->member($target);}catch(StaffRedirect $stop){return $stop->getMessage();}throw new RuntimeException('missing native redirect');}
$base=['issuers_access_present'=>'1','issuers_access_csrf'=>$token,'issuer_memberships'=>[$a=>'1'],'issuer_default'=>(string)$a,'issuer_resource_permissions'=>[$a=>['banco_brasil'=>['view','manage']]]];
same('/admin/staff/member/50',nativeStaffPost($base,''),'new staff grants use actual created ID');
same(true,issuer_resource_can($a,50,'banco_brasil','manage'),'helper uses actual company ACL');
same(false,issuer_resource_can($b,50,'banco_brasil','manage'),'global stub allow never crosses issuer');
foreach(['issuers_access_present','issuers_access_csrf','issuer_memberships','issuer_default','issuer_resource_permissions'] as$key){same(false,array_key_exists($key,$model->calls[0][1]),'reserved field never reaches native staff writer');}
$before=count($model->calls);$images=$imageCalls;
nativeStaffPost(array_merge($base,['issuers_access_csrf'=>'wrong']));same($before,count($model->calls),'bad token blocked before core update');same($images,$imageCalls,'bad token blocked before image upload');
$staffActor=3;nativeStaffPost($base);same($before,count($model->calls),'non-admin cannot forge assignment despite native global stub allow');$staffActor=4;
nativeStaffPost(array_merge($base,['issuer_default'=>(string)$b]));same($before,count($model->calls),'invalid default blocked before native profile mutation');
$model->response=false;nativeStaffPost($base);same(true,$acl->can($a,3,'banco_brasil','manage'),'grant-only edit works when core profile unchanged');
$model->response=['cant_remove_main_admin'=>true];nativeStaffPost(array_merge($base,['issuer_memberships'=>[],'issuer_default'=>'','issuer_resource_permissions'=>[]]));same(true,$acl->can($a,3,'banco_brasil','manage'),'native refused profile update cannot change issuer grants');$model->response=true;
$db->failOn="INSERT INTO {$p}audit";$staffAlerts=[];nativeStaffPost(array_merge($base,['issuer_memberships'=>[],'issuer_default'=>'','issuer_resource_permissions'=>[]]));$db->failOn=null;
same(true,$acl->can($a,3,'banco_brasil','manage'),'grant failure rolls back despite core profile being processed');
same(['warning',_l('issuer_staff_access_not_saved')],end($staffAlerts),'partial profile/grant outcome explicitly warned');
$staffActor=3;$own=['issuers_access_present'=>'1','issuers_access_csrf'=>$token,'issuer_default'=>(string)$a];nativeStaffPost($own,'3',true);same($a,$r->defaultForStaff(3),'own native profile saves authorized default');
$before=count($model->calls);nativeStaffPost(array_merge($own,['issuer_memberships'=>[$b=>'1']]),'3',true);same($before,count($model->calls),'own profile cannot grant memberships');
nativeStaffPost(array_merge($own,['issuer_resource_permissions'=>[$a=>['banco_brasil'=>['import']]]]),'3',true);same($before,count($model->calls),'own profile cannot grant resources');
$staffActor=4;ob_start();issuers_render_staff_access(3);$html=ob_get_clean();
same(true,str_contains($html,'name="issuer_memberships['.$a.']"'),'native staff UI has company membership checkbox');
same(true,str_contains($html,'issuer_resource_permissions['.$a.'][banco_brasil][]'),'native catalog resource grant is company-specific');
same(false,str_contains($html,'value="manage_integrations"'),'generic issuer permissions removed from staff UI');
same(false,str_contains($html,'value="view_own"'),'not-applicable native capability not offered');
same(true,str_contains($html,'Banco &lt;synthetic&gt;'),'resource label escaped');
$staffActor=4;ob_start();issuers_render_staff_access(4);$masterHtml=ob_get_clean();same(false,str_contains($masterHtml,'name="issuer_memberships['),'master access cannot be unchecked');same(true,str_contains($masterHtml,_l('issuer_master_access_automatic')),'master automatic access explained');
$staffActor=3;ob_start();issuers_render_staff_access(3,true);$ownHtml=ob_get_clean();same(false,str_contains($ownHtml,'issuer_resource_permissions['),'own profile does not render grant controls');
$payload=['firstname'=>'Synthetic'];same(null,issuers_prepare_staff_access($payload,3),'legacy forms without module fields remain accepted');same(['firstname'=>'Synthetic'],$payload,'ordinary core fields untouched');
$staffActor=4;$record=$db->query("SELECT metadata_json FROM {$p}audit WHERE subject_id=50 AND event='staff_issuer_access_saved' ORDER BY id DESC")->result_array()[0];$audit=json_decode($record['metadata_json'],true,512,JSON_THROW_ON_ERROR);
same([$a],$audit['after']['companies'],'atomic audit contains normalized company IDs');same($a,$audit['after']['default'],'audit records default');same(2,count($audit['after']['resources']),'audit records exact resources and capabilities');same(false,str_contains($record['metadata_json'],'Synthetic'),'audit excludes names');
echo 'Issuer native Staff integration: '.($checks-$nativeBaseline).' additional checks passed.'.PHP_EOL;
