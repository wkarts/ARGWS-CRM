<?php
/** Real native controller/helper/model/ACL. Framework input/session/redirect alone are adapted. */
require __DIR__.'/staff_native_atomic.php';
$controllerBaseline=$GLOBALS['staffChecks'];
#[AllowDynamicProperties] class AdminController {}
class StaffControllerRedirect extends RuntimeException {}
function issuer_core_ready(){return true;}
function _l($key,...$args){return $key;}
function html_escape($value){return htmlspecialchars((string)$value,ENT_QUOTES,'UTF-8');}
function admin_url($path){return '/admin/'.$path;}
function redirect($path){throw new StaffControllerRedirect($path);}
function set_alert($type,$message){$GLOBALS['staffAlerts'][]=[$type,$message];}
function log_message($level,$message){$GLOBALS['staffLogs'][]=$message;}
function staff_cant($cap,$feature){return false;} // Native global check never substitutes for the real issuer ACL.
function access_denied($feature){throw new DomainException('native_access_denied');}
function nl2br_save_html($value){return $value;}
function handle_staff_profile_image_upload(...$args){$GLOBALS['staffImageCalls'][]=$GLOBALS['nativeDb']->pdo->inTransaction();}
function get_available_staff_permissions(){return ['tasks'=>['name'=>'Tarefas <synthetic>','capabilities'=>['view'=>'Consultar','create'=>'Criar','edit'=>'Editar','view_own'=>['name'=>'Own','not_applicable'=>true]]]];}
require APPPATH.'helpers/staff_access_helper.php';require APPPATH.'controllers/admin/Staff.php';
$GLOBALS['staffAlerts']=[];$GLOBALS['staffLogs']=[];$GLOBALS['staffImageCalls']=[];
$session=new class{public array $data=[];function userdata($key){return $this->data[$key]??null;}function set_userdata($key,$value){$this->data[$key]=$value;}};
$loader=new class{function model($name){}function view($name,$data){extract($data);require APPPATH.'views/'.$name.'.php';}};
$GLOBALS['nativeCI']->session=$session;$GLOBALS['nativeCI']->load=$loader;
$inputObject=new class{public array $data=[];function post($key=null,$clean=null){return $key===null?$this->data:($this->data[$key]??'');}};
$controller=new Staff();$controller->load=$loader;$controller->staff_model=$model;$controller->input=$inputObject;
function staffNativePost(array $data,$target='',bool $personal=false):string
{
    $GLOBALS['inputObject']->data=array_merge(['email_signature'=>'','password'=>''],$data);
    try{if($personal){$GLOBALS['controller']->edit_profile();}else{$GLOBALS['controller']->member($target);}}catch(StaffControllerRedirect $e){return $e->getMessage();}throw new RuntimeException('Missing native redirect');
}
$token=issuers_staff_form_token();staffCheck(strlen($token)===64,'native form session token generated');
$base=['firstname'=>'Controller','lastname'=>'Synthetic','email'=>'controller@example.invalid','phonenumber'=>'000','role'=>'','password'=>'synthetic-controller-123','departments'=>[1],'permissions'=>['tasks'=>['view']],'issuers_access_present'=>'1','issuers_access_csrf'=>$token,'issuer_memberships'=>[1=>'1',2=>'1'],'issuer_default'=>'2','issuer_resource_permissions'=>[1=>['tasks'=>['view']],2=>['tasks'=>['view']]]];
$before=staffSnapshot($db);staffNativePost(['firstname'=>'Denied','lastname'=>'Synthetic','email'=>'no-company@example.invalid','password'=>'synthetic-controller-123']);staffCheck($before===staffSnapshot($db),'new native employee without company/default refused before SQL');
staffNativePost(array_replace($base,['issuer_default'=>'']));staffCheck($before===staffSnapshot($db),'new native employee with empty default refused before SQL');
$url=staffNativePost($base);preg_match('~/([0-9]+)$~',$url,$m);$nativeId=(int)$m[1];staffCheck($nativeId>0&&$url==='/admin/staff/member/'.$nativeId,'native controller redirects to actual created identity');
$resource=new IssuerResourceAccess($db,$p);staffCheck($resource->can(2,$nativeId,'tasks','view'),'native controller stores real company ACL');staffCheck(!$resource->can(3,$nativeId,'tasks','view'),'native global allow stub cannot cross issuer');
staffCheck($GLOBALS['staffImageCalls']===[false],'avatar executed only after commit');
$before=staffSnapshot($db);$images=count($GLOBALS['staffImageCalls']);staffNativePost(array_replace($base,['issuers_access_csrf'=>'wrong']),$nativeId);staffCheck($before===staffSnapshot($db),'bad own csrf before profile/ACL writes');staffCheck(count($GLOBALS['staffImageCalls'])===$images,'bad own csrf before avatar');
$GLOBALS['nativeActor']=21;staffNativePost($base,$nativeId);staffCheck($before===staffSnapshot($db),'nonadministrative actor cannot forge native assignment despite global allow stub');$GLOBALS['nativeActor']=17;
staffNativePost(array_replace($base,['issuer_default'=>'3']),$nativeId);staffCheck($before===staffSnapshot($db),'invalid default blocked before native profile mutation');
$change=array_replace($base,['password'=>'','firstname'=>'Controller updated']);staffNativePost($change,$nativeId);staffCheck($db->query('SELECT firstname FROM '.$p.'staff WHERE staffid=?',[$nativeId])->row()->firstname==='Controller updated','native controller positive profile edit');
$before=staffSnapshot($db);$db->failOn='INSERT INTO '.$p.'issuers_audit ';$GLOBALS['staffAlerts']=[];$images=count($GLOBALS['staffImageCalls']);staffNativePost(array_replace($change,['firstname'=>'Must rollback','issuer_memberships'=>[1=>'1'],'issuer_default'=>'1','issuer_resource_permissions'=>[1=>['tasks'=>['edit']]]]),$nativeId);$db->failOn=null;
staffCheck($before===staffSnapshot($db),'ACL failure rolls back real profile and all grants/default');staffCheck(count($GLOBALS['staffImageCalls'])===$images,'ACL failure skips avatar');staffCheck(count(array_filter($GLOBALS['staffAlerts'],static fn($a)=>$a[0]==='success'))===0,'failure never announces profile success');staffCheck(str_contains(end($GLOBALS['staffAlerts'])[1],'não houve confirmação parcial'),'failure message does not report partial grants success');
$GLOBALS['nativeActor']=$nativeId;$own=['firstname'=>'Personal','lastname'=>'Synthetic','email'=>'controller@example.invalid','issuers_access_present'=>'1','issuers_access_csrf'=>$token,'issuer_default'=>'1'];staffNativePost($own,$nativeId,true);staffCheck((int)$db->query('SELECT issuer_id FROM '.$p.'issuers_staff_defaults WHERE staff_id=?',[$nativeId])->row()->issuer_id===1,'personal native controller saves valid default');
$before=staffSnapshot($db);staffNativePost($own+['issuer_memberships'=>[3=>'1']],$nativeId,true);staffCheck($before===staffSnapshot($db),'personal controller cannot forge membership');staffNativePost($own+['issuer_resource_permissions'=>[1=>['tasks'=>['edit']]]],$nativeId,true);staffCheck($before===staffSnapshot($db),'personal controller cannot forge resources');
$GLOBALS['nativeActor']=17;ob_start();issuers_render_staff_access($nativeId);$html=ob_get_clean();staffCheck(str_contains($html,'name="issuer_memberships[2]"'),'native membership form retained');staffCheck(str_contains($html,'issuer_resource_permissions[2][tasks][]'),'native resource granularity retained');staffCheck(!str_contains($html,'value="manage_integrations"'),'no generic integration authority offered');staffCheck(!str_contains($html,'value="view_own"'),'not-applicable capability omitted');staffCheck(str_contains($html,'Tarefas &lt;synthetic&gt;'),'native permission names escaped');
ob_start();issuers_render_staff_access(17);$html=ob_get_clean();staffCheck(!str_contains($html,'name="issuer_memberships['),'master access cannot be unchecked');staffCheck(str_contains($html,'issuer_master_access_automatic'),'master automatic access explained');
$GLOBALS['nativeActor']=$nativeId;ob_start();issuers_render_staff_access($nativeId,true);$html=ob_get_clean();staffCheck(!str_contains($html,'issuer_resource_permissions['),'own profile exposes no ACL controls');
$payload=['firstname'=>'Legacy'];staffCheck(issuers_prepare_staff_access($payload,$nativeId)===null&&$payload===['firstname'=>'Legacy'],'forms without issuer fields preserve native compatibility');
$record=$db->query('SELECT metadata_json FROM '.$p.'issuers_audit WHERE subject_id=? AND event=\'staff_issuer_access_saved\' ORDER BY id DESC',[$nativeId])->row_array();$audit=json_decode($record['metadata_json'],true);staffCheck($audit['after']['companies']===[1,2]&&$audit['after']['default']===2,'native audit contains exact company/default state');staffCheck(!str_contains($record['metadata_json'],'Controller'),'audit excludes profile identity');
echo 'Native Staff controller: '.($GLOBALS['staffChecks']-$controllerBaseline).' additional checks passed; all use real model writes.'.PHP_EOL;
