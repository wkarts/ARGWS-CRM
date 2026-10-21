<?php
declare(strict_types=1);
define('BASEPATH',dirname(__DIR__).'/system/');
define('APPPATH',dirname(__DIR__).'/application/');
define('APP_MODULES_PATH',dirname(__DIR__).'/modules/');
require APPPATH.'services/PaymentGatewayCatalog.php';
$n=0;function check($ok,$message){global $n;++$n;if(!$ok)throw new RuntimeException($message);}
function fails($call,$expected){try{$call();}catch(Throwable $e){check($e->getMessage()===$expected,'expected '.$expected.', got '.$e->getMessage());return;}check(false,'missing '.$expected);}
function db_prefix(){return 'tbl';}
function &get_instance(){return $GLOBALS['ci'];}
function is_admin(){return $GLOBALS['admin']??true;}
function access_denied($name){throw new RuntimeException('denied');}
function show_404(){throw new RuntimeException('404');}
function show_error($message,$status=500){throw new RuntimeException('http:'.$status);}
function admin_url($path=''){return 'https://crm.example.test/admin/'.$path;}
function redirect($url){$GLOBALS['redirect']=$url;}
function set_alert($level,$message){$GLOBALS['alerts'][]=[$level,$message];}
function log_message($level,$message){}
function register_payment_gateway($id,$module){$GLOBALS['registeredGateway']=[$id,$module];}
function register_activation_hook($module,$callback){$GLOBALS['activation']=$callback;}
function add_option($name){$GLOBALS['options'][$name]??='';}
function hooks(){static $hooks;return $hooks??=new class{function add_action(...$args){}function add_filter(...$args){}};}
class MigrationDb {
    public string $version='1.0.0';public int $active=0;public bool $exists=true,$failBaseline=false,$failTracking=false;public array $writes=[],$locks=[];
    function select($fields){return $this;}function where($key,$value){if($key!=='module_name'||$value!=='ideal')throw new RuntimeException('unexpected_scope');return $this;}
    function get($table){if($table!=='tblmodules')throw new RuntimeException('unexpected_table');$row=$this->exists?(object)['installed_version'=>$this->version,'active'=>$this->active]:null;return new class($row){function __construct(private $row){}function row(){return $this->row;}};}
    function update($table,$data){if($table!=='tblmodules'||array_keys($data)!==['installed_version'])throw new RuntimeException('unexpected_mutation');$this->writes[]=$data['installed_version'];if(($this->failBaseline&&$data['installed_version']==='1.0.0')||($this->failTracking&&$data['installed_version']==='1.0.1'))return false;$this->version=$data['installed_version'];return true;}
    function query($sql,$bindings=[]){if(str_starts_with($sql,'SELECT GET_LOCK(')){$this->locks[]='GET_LOCK';return new class{function row(){return (object)['acquired'=>1];}};}if(str_starts_with($sql,'SELECT RELEASE_LOCK(')){$this->locks[]='RELEASE_LOCK';return true;}throw new RuntimeException('no_sql_allowed_for_noop');}
}
class ModuleFixture {
    public int $upgrades=0;public string $header='1.0.1';
    function __construct(public MigrationDb $db){}
    function get($name=null){$row=['system_name'=>'ideal','headers'=>['version'=>$this->header], 'installed_version'=>$this->db->exists?$this->db->version:false,'activated'=>$this->db->active];return $name===null?[$row]:($name==='ideal'?$row:null);}
    function upgrade_database($name){$this->upgrades++;$migration=new App_module_migration($name);return $migration->to_latest()===false?$migration->error_string():true;}
    function is_essential($name){return $name==='issuers';}
    function is_database_upgrade_required($name){return $this->db->version!==$this->header;}
    function database_maintenance_inventory(){if(!$this->db->exists||$this->db->version===$this->header)return []; $status=version_compare($this->db->version,$this->header,'<')?'upgrade':'downgrade'; return ['ideal'=>['system_name'=>'ideal','installed_version'=>$this->db->version,'code_version'=>$this->header,'activated'=>$this->db->active,'status'=>$status,'can_upgrade'=>$status==='upgrade']];}
    function number_of_modules_that_require_database_upgrade(){return count($this->database_maintenance_inventory());}
}
class MigrationLoad { public array $data=[];function dbforge(){}function view($name,$data){$this->data=$data;} }
class MigrationSession {public array $data=[];function userdata($key){return $this->data[$key]??null;}function set_userdata($key,$value){$this->data[$key]=$value;}}
class MigrationInput {public string $verb='POST';public $token=null;function method($upper){return $this->verb;}function post($key){return $this->token;}}
class AdminController {public $db,$session,$input,$app_modules,$config,$load;function __construct(){}}
require APPPATH.'libraries/App_modules.php';
require APPPATH.'libraries/App_module_migration.php';
putenv('CRM_ENABLE_IDEAL=false');
require APP_MODULES_PATH.'ideal/ideal.php';
require APPPATH.'controllers/admin/Mods.php';
function setup(){
    $db=new MigrationDb();$loader=new MigrationLoad();$modules=new ModuleFixture($db);
    $GLOBALS['ci']=(object)['db'=>$db,'load'=>$loader,'app_modules'=>$modules,'lang'=>new class{function load($name){}function line($name){return $name.' %s';}}];
    $GLOBALS['admin']=true;$GLOBALS['alerts']=[];$GLOBALS['redirect']=null;
    $controller=new Mods();$controller->db=$db;$controller->load=$loader;$controller->app_modules=$modules;$controller->session=new MigrationSession();$controller->input=new MigrationInput();
    $controller->config=new class{function load($name){}function item($key){return ['ideal'=>['name'=>'iDEAL','description'=>'Fixture','requires'=>[]]];}};
    return [$db,$controller];
}
check(preg_match('/^Version: 1\.0\.1$/m',file_get_contents(APP_MODULES_PATH.'ideal/ideal.php'))===1,'header increments to1.0.1');
check($GLOBALS['registeredGateway']===['Ideal_gateway','ideal'],'legacy gateway remains registered with flag false');
[$db,$controller]=setup();$db->version='1.0.1';$GLOBALS['options']=['ideal_module_stripe_webhook_signing_secret'=>'fixture-existing'];
idealModuleActivation();check($db->version==='1.0.1'&&$db->writes===['1.0.0','1.0.1'],'fresh native activation replays 101 from real baseline');
check($db->active===0,'migration hook does not enable module');check($GLOBALS['options']['ideal_module_stripe_webhook_signing_secret']==='fixture-existing','existing webhook option preserved');
idealModuleActivation();check($db->version==='1.0.1'&&count($db->writes)===4,'activation replay is idempotent');
[$db]=setup();idealModuleActivation();check($db->version==='1.0.1'&&$db->writes===['1.0.1'],'older installed version is upgraded without resetting its marker');
foreach(['0.9.9','1.0.0.1','invalid'] as $invalid){[$db]=setup();$db->version=$invalid;fails(fn()=>idealModuleActivation(),'ideal_activation_version_invalid');check($db->writes===[],'unsupported older/intermediate version remains unchanged');}
[$db]=setup();$migration=new App_module_migration('ideal');check((string)$migration->to_latest()==='101'&&$db->version==='1.0.1','native upgrade 100 to101');
$count=count($db->writes);check($migration->to_latest()===true&&count($db->writes)===$count,'native upgrade replay has no write');
check(array_keys($migration->find_migrations())===[101],'only real101 migration, no synthetic100');
[$db]=setup();$db->version='1.0.1';$db->failBaseline=true;fails(fn()=>idealModuleActivation(),'ideal_activation_version_write_failed');check($db->active===0&&$db->version==='1.0.1','baseline write failure cannot activate');
[$db]=setup();$db->failTracking=true;$trackingFailure=null;try{idealModuleActivation();}catch(Throwable $e){$trackingFailure=$e->getMessage();}check(in_array($trackingFailure,['ideal_migration_failed','ideal_migration_version_unconfirmed'],true)&&$db->active===0&&$db->version==='1.0.0','failed native marker cannot claim success');
[$db]=setup();$db->version='1.0.2';fails(fn()=>idealModuleActivation(),'ideal_activation_version_invalid');check($db->writes===[],'no downgrade on activation');
[$db]=setup();$db->exists=false;fails(fn()=>idealModuleActivation(),'ideal_activation_version_invalid');
foreach(['false','true'] as $flag){
    putenv('CRM_ENABLE_IDEAL='.$flag);[$db,$controller]=setup();$controller->index();
    check(count($controller->load->data['resources'])===1,'all modules remain visible regardless of commercial flag');
    check($controller->load->data['resources'][0]['can_manage_availability']===($flag==='true'),'visibility does not widen activation authorization');
    check($controller->load->data['maintenance_count']===1,'catalog links to technical maintenance for both availability states');
    $controller->maintenance();check(isset($controller->load->data['pending']['ideal']),'hidden or visible installed module appears in maintenance inventory');
    $controller->input->token=$controller->session->data['module_migration_csrf'];$controller->upgrade_database('ideal');
    check($db->version==='1.0.1'&&$controller->app_modules->upgrades===1,'maintenance upgrades with flag '.$flag);
    check($db->active===0&&getenv('CRM_ENABLE_IDEAL')===$flag,'maintenance preserves inactive module and environment');
    check($GLOBALS['alerts'][0][0]==='success','verified marker reports success');
}
putenv('CRM_ENABLE_IDEAL=false');
[$db,$controller]=setup();$db->active=1;$controller->maintenance();$controller->input->token=$controller->session->data['module_migration_csrf'];$controller->upgrade_database('ideal');
check($db->active===1&&$db->version==='1.0.1','hidden active legacy module stays active after maintenance');
foreach(['GET','missing','wrong','array','nonadmin','uninstalled','downgrade'] as $case){
    [$db,$controller]=setup();$controller->maintenance();$controller->input->token=$controller->session->data['module_migration_csrf'];$expected='http:403';
    if($case==='GET'){$controller->input->verb='GET';$expected='404';}
    if($case==='missing')$controller->input->token=null;
    if($case==='wrong')$controller->input->token='wrong';
    if($case==='array')$controller->input->token=[];
    if($case==='nonadmin'){$GLOBALS['admin']=false;$expected='denied';}
    if($case==='uninstalled'){$db->exists=false;$expected='404';}
    if($case==='downgrade'){$db->version='1.0.2';$expected='http:409';}
    if($case==='downgrade'){$controller->upgrade_database('ideal');check($GLOBALS['alerts'][0][0]==='danger','downgrade reports failure without runner');}else{fails(fn()=>$controller->upgrade_database('ideal'),$expected);}
    check($controller->app_modules->upgrades===0&&$db->writes===[],'negative maintenance case has no write: '.$case);
}
[$db,$controller]=setup();$controller->maintenance();$controller->input->token=$controller->session->data['module_migration_csrf'];$db->failTracking=true;$controller->upgrade_database('ideal');
check($db->version==='1.0.0'&&$GLOBALS['alerts'][0][0]==='danger','upgrade route checks persisted marker after native false write');
check($db->active===0,'failure does not activate module');
[$db,$controller]=setup();$db->version='1.0.1';$controller->maintenance();check($controller->load->data['pending']===[],'completed hidden migration has no maintenance prompt');
$definition=new ReflectionMethod(Mods::class,'get_resource_definition');check($definition->invoke($controller,'ideal')===false,'maintenance does not unlock activation/configuration');
$install=file_get_contents(APP_MODULES_PATH.'ideal/install.php');check(!preg_match('/\b(CREATE|ALTER|DROP|TRUNCATE)\s+TABLE\b/i',$install),'install owns no DDL');
$source=file_get_contents(APP_MODULES_PATH.'ideal/controllers/Ideal.php');$webhook=substr($source,strpos($source,'    public function webhook(): void'));
check(!str_contains($webhook,'idealEnabled()'),'legacy callback not blocked by availability flag');
$view=file_get_contents(APPPATH.'views/admin/modules/list.php');check(!str_contains($view,'upgrade_database/'),'catalog routes maintenance through dedicated technical screen');
$view=file_get_contents(APPPATH.'views/admin/modules/maintenance.php');check(substr_count($view,'name="module_migration_csrf"')===1&&str_contains($view,'form_open'),'eligible resources send their session CSRF in POST maintenance forms');
putenv('CRM_ENABLE_IDEAL');
echo "iDEAL native migration and maintenance: {$n} checks passed\n";
