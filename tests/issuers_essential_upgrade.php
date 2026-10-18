<?php
/** Real App upgrade orchestration; migration/transport collaborators are explicit doubles. */
declare(strict_types=1);
define('BASEPATH', dirname(__DIR__).'/system/');
require __DIR__.'/../application/libraries/App.php';
class UpgradeStop extends RuntimeException {}
function db_prefix(){return 'test_';}
function is_cli(){return $GLOBALS['cli'];}
function is_staff_logged_in(){return false;}
function set_alert($type,$message){}
function admin_url($path=''){return '/admin/'.$path;}
function redirect($url,$mode=null){throw new UpgradeStop($url);}
function show_error($message){throw new UpgradeStop($message);}
function delete_option($name){}
function add_option($name,$value){}
function update_option($name,$value){}
function log_message($level,$message){}
function hooks(){return new class {function do_action($name,...$args){$GLOBALS['events'][]=$name;}};}
class UpgradeDb {
 public int $version=382;
 function limit($n){return $this;}
 function get($table){return new class($this->version) {function __construct(public $version){} function row(){return (object)['version'=>$this->version];}};}
}
class UpgradeLoad {
 function __construct(public $ci){}
 function config($name){}
 function library($name,$config=null){$GLOBALS['loads'][]=$name;}
}
$checks=0;function check($ok,$name){global $checks;++$checks;if(!$ok)throw new RuntimeException($name);}
function fixture(){
 $ci=(object)['db'=>new UpgradeDb()];
 $ci->load=new UpgradeLoad($ci);
 $ci->config=new class {function item($name){return $name==='migration_version'?382:null;}};
 $ci->migration=new class($ci) {public bool $fail=false;function __construct(public $ci){} function current(){++$GLOBALS['coreCalls'];return !$this->fail;} function error_string(){return 'synthetic core failure';}};
 $ci->app_modules=new class {public int $calls=0;public string $mode='ok';function ensure_essential_modules(){++$this->calls;if($this->mode==='throw')throw new RuntimeException('synthetic module failure');return $this->mode==='ok';}};
 $reflection=new ReflectionClass(App::class);$app=$reflection->newInstanceWithoutConstructor();$reflection->getProperty('ci')->setValue($app,$ci);
 $GLOBALS['cli']=true;$GLOBALS['events']=[];$GLOBALS['loads']=[];$GLOBALS['coreCalls']=0;return [$app,$ci];
}
[$app,$ci]=fixture();$result=$app->apply_pending_migrations_for_provisioning();
check($result['success']===true,'controlled provisioning confirms success');check($ci->app_modules->calls===1,'module reconciliation follows core migration');
check(in_array(['app_modules','app_module_migration'],$GLOBALS['loads'],true),'isolated CI controller explicitly loads native module libraries');
check(in_array('database_updated',$GLOBALS['events'],true),'completion hook after essential confirmation');
foreach(['false','throw'] as $mode){[$app,$ci]=fixture();$ci->app_modules->mode=$mode;$result=$app->apply_pending_migrations_for_provisioning();check($result['success']===false,'essential failure prevents provisioning success');check($ci->app_modules->calls===1,'essential attempted once');check(!in_array('database_updated',$GLOBALS['events'],true),'no completion hook for essential failure');}
[$app,$ci]=fixture();$ci->migration->fail=true;$result=$app->apply_pending_migrations_for_provisioning();check($result['success']===false,'core failure propagated');check($ci->app_modules->calls===0,'core failure never activates module');
[$app,$ci]=fixture();$GLOBALS['cli']=false;$result=$app->apply_pending_migrations_for_provisioning();check($result['success']===false,'HTTP cannot invoke provisioning path');check($GLOBALS['coreCalls']===0 && $ci->app_modules->calls===0,'HTTP rejection before any migration');
[$app,$ci]=fixture();$GLOBALS['cli']=false;try{$app->upgrade_database();}catch(UpgradeStop $e){check($e->getMessage()==='/admin/authentication','web core upgrade returns to authentication');}check($GLOBALS['coreCalls']===1,'legacy core upgrade remains functional');check($ci->app_modules->calls===0,'web upgrade defers essential setup to mandatory CSRF action');
echo "Issuer essential upgrade orchestration: {$checks} checks passed (real App methods; explicit migration/transport doubles).\n";
