<?php
/** Real App upgrade orchestration; migration/transport collaborators are explicit doubles. */
declare(strict_types=1);
define('BASEPATH', dirname(__DIR__).'/system/');
define('APPPATH', dirname(__DIR__).'/application/');
require __DIR__.'/../application/libraries/App.php';
// Explicit core-schema collaborator; actual SQL/schema is tested independently.
class IssuerCore {
 public static int $calls=0;
 public static string $mode='ok';
 public static function reconcile($db,$prefix):void { ++self::$calls; if(self::$mode==='throw')throw new RuntimeException('synthetic schema failure'); }
 public static function ready($db,$prefix):bool { return self::$mode==='ok'; }
}
// Explicit context collaborator. Real membership/default resolution is covered by
// tests/company_context.php; here we assert fresh post-migration orchestration.
class CompanyContext {
 public static string $mode='empty';
 public static int $instances=0;
 public function __construct($db,$prefix,$session,$staff){++self::$instances;check(IssuerCore::$calls>0,'context constructed only after core reconciliation');check($staff===7,'context uses authenticated actor');}
 public function resolve($recovery,$allowEmpty){check($recovery===false && $allowEmpty===true,'upgrade never silently recovers revoked selection');if(self::$mode==='revoked')throw new DomainException('company_context_membership_revoked');return self::$mode==='empty'?null:12;}
 public function isPrimary(){return self::$mode==='primary';}
}
class UpgradeStop extends RuntimeException {}
function db_prefix(){return 'test_';}
function is_cli(){return $GLOBALS['cli'];}
function is_staff_logged_in(){return $GLOBALS['loggedIn'];}
function is_admin(){return $GLOBALS['isAdmin'];}
function get_staff_user_id(){return 7;}
function set_alert($type,$message){$GLOBALS['alerts'][]=[$type,$message];}
function admin_url($path=''){return '/admin/'.$path;}
function redirect($url,$mode=null,$status=null){$GLOBALS['redirect']=[$url,$mode,$status];throw new UpgradeStop($url);}
function show_error($message,$status=500){throw new UpgradeStop($message,$status);}
function delete_option($name){}
function add_option($name,$value){}
function update_option($name,$value){}
function log_message($level,$message){}
function hooks(){return new class {function do_action($name,...$args){$GLOBALS['events'][]=$name;}};}
class UpgradeDb {
 public int $version=393;
 public bool $db_debug=true;
 public bool $save_queries=true;
 function limit($n){return $this;}
 function get($table){return new class($this->version) {function __construct(public $version){} function row(){return (object)['version'=>$this->version];}};}
}
class UpgradeLoad {
 function __construct(public $ci){}
 function config($name){}
 function model($name){}
 function library($name,$config=null){$GLOBALS['loads'][]=$name;}
}
$checks=0;function check($ok,$name){global $checks;++$checks;if(!$ok)throw new RuntimeException($name);}
function fixture(){
 $ci=(object)['db'=>new UpgradeDb()];
 $ci->load=new UpgradeLoad($ci);
 $ci->staff_model=new class {public $staff;function __construct(){$this->staff=(object)['staffid'=>7,'active'=>1,'admin'=>1];}function get($id){return $this->staff;}};
 $ci->session=new class {public array $data=[];function userdata($key){return $this->data[$key]??null;}function set_userdata($key,$value){$this->data[$key]=$value;}function unset_userdata($key){unset($this->data[$key]);}};
 $ci->input=new class {public string $verb='POST';public array $data=[];function method($upper){return $this->verb;}function post($key){return $this->data[$key]??null;}};
 $ci->session->data['admin_database_upgrade_csrf']=str_repeat('a',64);
 $ci->input->data['admin_database_upgrade_csrf']=str_repeat('a',64);
 $ci->config=new class {function item($name){return $name==='migration_version'?393:null;}};
 $ci->migration=new class($ci) {public bool $fail=false;function __construct(public $ci){} function current(){++$GLOBALS['coreCalls'];check(!$this->ci->db->db_debug && !$this->ci->db->save_queries,'native migration suppresses SQL diagnostics');return !$this->fail;} function error_string(){return 'synthetic core failure';}};

 $reflection=new ReflectionClass(App::class);$app=$reflection->newInstanceWithoutConstructor();$reflection->getProperty('ci')->setValue($app,$ci);
 CompanyContext::$mode='empty';CompanyContext::$instances=0;IssuerCore::$calls=0;IssuerCore::$mode='ok';$GLOBALS['loggedIn']=true;$GLOBALS['isAdmin']=true;$GLOBALS['alerts']=[];$GLOBALS['cli']=true;$GLOBALS['events']=[];$GLOBALS['loads']=[];$GLOBALS['coreCalls']=0;return [$app,$ci];
}
[$app,$ci]=fixture();$result=$app->apply_pending_migrations_for_provisioning();
check($result['success']===true,'controlled provisioning confirms success');check(IssuerCore::$calls===1,'core schema reconciliation follows native migration');
check(!in_array(['app_modules','app_module_migration'],$GLOBALS['loads'],true),'core upgrade never loads module activation libraries');
check(in_array('database_updated',$GLOBALS['events'],true),'completion hook after company core confirmation');check($ci->db->db_debug && $ci->db->save_queries,'success restores database diagnostics');
foreach(['false','throw'] as $mode){[$app,$ci]=fixture();IssuerCore::$mode=$mode;$result=$app->apply_pending_migrations_for_provisioning();check($result['success']===false,'core schema failure prevents provisioning success');check(IssuerCore::$calls===1,'core reconcile attempted once');check(!in_array('database_updated',$GLOBALS['events'],true),'no completion hook for core schema failure');}
[$app,$ci]=fixture();$ci->migration->fail=true;$result=$app->apply_pending_migrations_for_provisioning();check($result['success']===false,'core failure propagated');check(IssuerCore::$calls===0,'native migration failure never reconciles schema');
[$app,$ci]=fixture();$ci->db->version=999;$result=$app->upgrade_database();check($result['success']===false,'newer persisted schema rejects downgrade');check($GLOBALS['coreCalls']===0 && IssuerCore::$calls===0,'downgrade rejected before any migration');
[$app,$ci]=fixture();$ci->db->version=392;$result=$app->upgrade_database();check($result['success']===false,'unpersisted native migration marker cannot report web success');check(IssuerCore::$calls===0 && !in_array('database_updated',$GLOBALS['events'],true),'tracking failure stops before reconciliation/completion');check(isset($ci->session->data['admin_database_upgrade_csrf']),'tracking failure preserves retry token');
[$app,$ci]=fixture();$GLOBALS['cli']=false;$result=$app->apply_pending_migrations_for_provisioning();check($result['success']===false,'HTTP cannot invoke provisioning path');check($GLOBALS['coreCalls']===0 && IssuerCore::$calls===0,'HTTP rejection before any migration');
[$app,$ci]=fixture();$GLOBALS['cli']=false;try{$app->upgrade_database();}catch(UpgradeStop $e){check($e->getMessage()==='/admin/company_context','web native upgrade returns to isolated company selector without wizard');}check($GLOBALS['coreCalls']===1,'native core upgrade remains functional');check(IssuerCore::$calls===1,'web upgrade completes company core in the same action');check(!isset($ci->session->data['admin_database_upgrade_csrf']),'success consumes upgrade token');check($GLOBALS['redirect']===['/admin/company_context','location',303],'successful POST has an explicit303 Location redirect');check($GLOBALS['alerts']===[],'success is not exposed before the authorized destination');$receipt=$ci->session->data['admin_database_upgrade_success'];check($receipt['actor']===7 && $receipt['version']==='393','native success receipt belongs to the actual actor and persisted version');check($receipt['expires_at']>time() && $receipt['expires_at']<=time()+300,'native feedback has a short finite TTL');check(AdminDatabaseUpgrade::consumeSuccess($ci->session,7,'393'),'authorized native destination can consume native success');set_alert('success','O CRM foi atualizado com sucesso.');check($GLOBALS['alerts'][0]===['success','O CRM foi atualizado com sucesso.'],'success has native destination feedback');check(!AdminDatabaseUpgrade::consumeSuccess($ci->session,7,'393'),'native feedback cannot repeat');
foreach (['primary'=>'/admin/','named'=>'/admin/company_context','empty'=>'/admin/company_context','revoked'=>'/admin/company_context'] as $mode=>$destination) {
 [$app,$ci]=fixture();$GLOBALS['cli']=false;CompanyContext::$mode=$mode;
 try{$app->upgrade_database();}catch(UpgradeStop $e){check($e->getMessage()===$destination,'fresh '.$mode.' scope returns to safe destination');}
 check(CompanyContext::$instances===1,'one fresh context after successful upgrade');
 check($GLOBALS['redirect']===[$destination,'location',303],'post-upgrade '.$mode.' uses explicit303');
}

foreach(['GET','guest','staff','inactive','deleted','revoked','csrf_missing','csrf_array','csrf_wrong','stored_array'] as $case){
 [$app,$ci]=fixture();$GLOBALS['cli']=false;
 if($case==='GET')$ci->input->verb='GET';
 if($case==='guest')$GLOBALS['loggedIn']=false;
 if($case==='staff')$GLOBALS['isAdmin']=false;
 if($case==='inactive')$ci->staff_model->staff->active=0;
 if($case==='deleted')$ci->staff_model->staff=null;
 if($case==='revoked')$ci->staff_model->staff->admin=0;
 if($case==='csrf_missing')$ci->input->data=[];
 if($case==='csrf_array')$ci->input->data['admin_database_upgrade_csrf']=[];
 if($case==='csrf_wrong')$ci->input->data['admin_database_upgrade_csrf']=str_repeat('b',64);
 if($case==='stored_array')$ci->session->data['admin_database_upgrade_csrf']=[];
 $status=null;try{$app->upgrade_database();}catch(UpgradeStop $e){$status=$e->getCode();}
 check($status===403,$case.' rejected');check($GLOBALS['coreCalls']===0 && IssuerCore::$calls===0,$case.' cannot migrate');
}
foreach(['false','throw'] as $mode){
 [$app,$ci]=fixture();$GLOBALS['cli']=false;IssuerCore::$mode=$mode;
 $result=$app->upgrade_database();
 check($result['success']===false,'real core schema failure returns to native update form');
 check(strpos($result['message'],'synthetic')===false,'failure does not expose storage details');
 check(isset($ci->session->data['admin_database_upgrade_csrf']),'failure retains upgrade token');check($ci->db->db_debug && $ci->db->save_queries,'failure restores database diagnostics');
 check(!in_array('database_updated',$GLOBALS['events'],true),'failed core has no completion hook');check(!isset($ci->session->data['admin_database_upgrade_success']),'failed core cannot create a success receipt');
 IssuerCore::$mode='ok';try{$app->upgrade_database();}catch(UpgradeStop $e){}
 check(!isset($ci->session->data['admin_database_upgrade_csrf']),'successful retry consumes token');
}
[$app,$ci]=fixture();IssuerCore::$mode='false';check($app->is_core_upgrade_required(),'incomplete core requests normal upgrade');check(IssuerCore::$calls===0,'readiness never reconciles');IssuerCore::$mode='ok';check(!$app->is_core_upgrade_required(),'ready core needs no wizard');
[$app,$ci]=fixture();$GLOBALS['cli']=false;check($app->apply_pending_migrations_for_installation()['success']===false,'HTTP cannot forge inline installer context');check($GLOBALS['coreCalls']===0,'rejected installer context has no DDL');
echo "Company core upgrade orchestration: {$checks} checks passed (real App methods; explicit migration/transport doubles).\n";
