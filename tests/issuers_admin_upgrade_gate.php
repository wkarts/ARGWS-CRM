<?php
/** Actual AdminController constructor; rendering/transport are explicit test doubles. */
declare(strict_types=1);
define('BASEPATH',dirname(__DIR__).'/system/');
define('APPPATH',dirname(__DIR__).'/application/');
class GateStop extends RuntimeException {}
#[AllowDynamicProperties]
class App_Controller { protected $current_db_version=393; public function __construct(){foreach($GLOBALS['fixture'] as $key=>$value)$this->$key=$value;} }
function is_staff_logged_in(){return $GLOBALS['logged'];}
function is_admin(){return $GLOBALS['admin'];}
function get_staff_user_id(){return 17;}
function current_full_url(){return '/admin/';}
function get_admin_uri(){return 'admin';}
function redirect_after_login_to_current_url(){}
function admin_url($path=''){return '/admin/'.$path;}
function redirect($url){throw new GateStop($url,302);}
function show_error($error,$status=500){throw new GateStop($error,$status);}
function hooks(){return new class {public function do_action($name){$GLOBALS['hooks'][]=$name;}};}
require APPPATH.'core/AdminController.php';
class TestedAdminController extends AdminController {
 protected function renderDatabaseUpgrade(string $token,?string $error):void {$GLOBALS['render']=[$token,$error];throw new GateStop('native_update_form',200);}
}
$checks=0;function check($ok,$name){global $checks;++$checks;if(!$ok)throw new RuntimeException($name);}
function fixture(){
 $GLOBALS['logged']=true;$GLOBALS['admin']=true;$GLOBALS['hooks']=[];$GLOBALS['render']=null;
 $ci=(object)[];
 $ci->uri=new class {function segment($n){return null;}};
 $ci->session=new class {public $data=[];function has_userdata($k){return isset($this->data[$k]);}function unset_userdata($k){unset($this->data[$k]);}function userdata($k){return $this->data[$k]??null;}function set_userdata($k,$v){$this->data[$k]=$v;}};
 $ci->db=new class {public $updates=0;function where($k,$v){return $this;}function update($t,$d){++$this->updates;}};
 $ci->staff_model=new class {public $staff;function __construct(){$this->staff=(object)['staffid'=>17,'active'=>1,'admin'=>1];}function get($id){return $this->staff;}};
 $ci->load=new class {function model($name){}};
 $ci->authentication_model=new class {public $logouts=0;function logout(){++$this->logouts;}};
 $ci->input=new class {public $verb='GET';public $data=[];function method($upper){return $this->verb;}function post($key){return $this->data[$key]??null;}};
 $ci->output=new class {public $status=200;function set_status_header($status){$this->status=$status;}};
 $ci->app=new class {public $pending=true;public $corePending=false;public $calls=0;public $failure=false;function is_db_upgrade_required($v=null){return $this->pending;}function is_core_upgrade_required(){return $this->corePending;}function get_current_db_version(){return 393;}function upgrade_database(){++$this->calls;if($this->failure)return ['success'=>false,'message'=>'Synthetic safe failure'];throw new GateStop('success_redirect',302);}};
 $GLOBALS['fixture']=$ci;return $ci;
}
function runGate(){try{new TestedAdminController();}catch(GateStop $e){return $e;}throw new RuntimeException('unexpected constructor completion');}
foreach(['GET','company_pending','guest','staff','inactive','deleted','revoked','bad_csrf','good_csrf','failed_upgrade'] as $case){
 $ci=fixture();
 if($case==='company_pending'){$ci->app->pending=false;$ci->app->corePending=true;}
 if($case==='guest')$GLOBALS['logged']=false;
 if($case==='staff')$GLOBALS['admin']=false;
 if($case==='inactive')$ci->staff_model->staff->active=0;
 if($case==='deleted')$ci->staff_model->staff=null;
 if($case==='revoked')$ci->staff_model->staff->admin=0;
 if(in_array($case,['bad_csrf','good_csrf','failed_upgrade'],true)){
   $ci->input->verb='POST';$ci->input->data=['upgrade_database'=>'true'];
   $ci->session->data['admin_database_upgrade_csrf']=str_repeat('a',64);
   $ci->input->data['admin_database_upgrade_csrf']=$case==='bad_csrf'?str_repeat('b',64):str_repeat('a',64);
 }
 if($case==='failed_upgrade')$ci->app->failure=true;
 $result=runGate();
 check(!in_array('pre_admin_init',$GLOBALS['hooks'],true),$case.' never starts optional admin hooks before schema ready');
 if(in_array($case,['GET','company_pending'],true)){
   check($result->getMessage()==='native_update_form',$case.' shows normal native update form');
   check(strlen($GLOBALS['render'][0])===64,$case.' issues session CSRF token');
   check($ci->app->calls===0,$case.' is read-only, with no upgrade call');
 }elseif(in_array($case,['guest','inactive','deleted'],true)){
   check($result->getMessage()==='/admin/authentication',$case.' routes to usable login');check($ci->app->calls===0,$case.' no DDL');
 }elseif(in_array($case,['staff','revoked'],true)){
   check($result->getCode()===503,$case.' cannot access update form');check($ci->app->calls===0,$case.' no DDL');
 }elseif($case==='bad_csrf'){
   check($result->getCode()===403,'invalid CSRF rejected before hook');check($ci->app->calls===0 && $GLOBALS['hooks']===[],'invalid CSRF cannot migrate or call pre-upgrade');
 }elseif($case==='good_csrf'){
   check($ci->app->calls===1 && $result->getMessage()==='success_redirect','authorized native upgrade runs exactly once');
 }else{
   check($ci->output->status===503,'failure preserves HTTP503');check($GLOBALS['render'][1]==='Synthetic safe failure','failure preserves native form error');check($GLOBALS['render'][0]===str_repeat('a',64),'failure retains same retry token');
 }
}
$authentication=file_get_contents(APPPATH.'controllers/admin/Authentication.php');
check(strpos($authentication,'is_db_upgrade_required')===false,'login no longer loops to admin before authenticated upgrade');
echo "Native admin upgrade gate: {$checks} checks passed (actual controller; explicit transport doubles).\n";
