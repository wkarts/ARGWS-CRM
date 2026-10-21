<?php
/** Real App runner: NOOP452 marker failures/ignored SQL/readback, retry/replay, no schema changes. */
declare(strict_types=1);
if(PHP_SAPI!=='cli'||!preg_match('/^essential_[a-f0-9]{10}_$/D',$argv[1]??'')||!str_starts_with(getenv('ISSUERS_TEST_DSN')?:'','mysql:'))exit(2);
define('BASEPATH',dirname(__DIR__,2).'/system/');define('APPPATH',dirname(__DIR__,2).'/application/');
require dirname(__DIR__).'/support/BbPdoDatabase.php';require BASEPATH.'libraries/Migration.php';require APPPATH.'libraries/App_Migration.php';require APPPATH.'libraries/App.php';require APPPATH.'services/issuers/IssuerCore.php';
function db_prefix(){return$GLOBALS['native452Prefix'];}function &get_instance(){return$GLOBALS['native452CI'];}function config_item($name){return$name==='subclass_prefix'?'App_':null;}function log_message($level,$message){$GLOBALS['native452Logs'][]=[$level,$message];}function hooks(){return$GLOBALS['native452Hooks'];}function delete_option($name){}function add_option($name,$value){}
function native452Check(bool$ok,string$message):void{++$GLOBALS['native452Checks'];if(!$ok)throw new RuntimeException($message);}
final class Native452Database extends BbPdoDatabase{
 public bool$failMarker=false;public bool$ignoreMarkerSql=false;public int$markerFailures=0;public int$ignoredMarkerWrites=0;public array$ddl=[];
 public function query($sql,$bind=[]){
  if(preg_match('/^\s*(?:CREATE|ALTER|DROP|TRUNCATE|RENAME)\b/i',$sql))$this->ddl[]=$sql;
  if(preg_match('/^UPDATE `?'.preg_quote(db_prefix().'migrations','/').'`? SET /i',$sql)){
   if($this->failMarker){++$this->markerFailures;return false;}
   if($this->ignoreMarkerSql){++$this->ignoredMarkerWrites;return true;}
  }
  return parent::query($sql,$bind);
 }
}
$GLOBALS['native452Prefix']=$argv[1];$GLOBALS['native452Checks']=0;$p=db_prefix();
$db=new Native452Database(new PDO(getenv('ISSUERS_TEST_DSN'),getenv('ISSUERS_TEST_USER')?:null,getenv('ISSUERS_TEST_PASSWORD')?:null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]));
$config=[];require APPPATH.'config/migration.php';$shippedVersion=trim(file_get_contents(dirname(APPPATH).'/VERSION'));
native452Check(preg_match('/^(\d)\.(\d)\.(\d)$/D',$shippedVersion,$shippedParts)===1,'canonical shipped native version shape');$shippedMarker=(int)($shippedParts[1].$shippedParts[2].$shippedParts[3]);
native452Check(($config['migration_version']??null)===$shippedMarker&&$shippedMarker>=452,'shipped configuration matches canonical VERSION and supports historical452');
$config['migration_version']=452; // Explicit historical target, including future452 source runs.
native452Check($db->table_exists($p.'migrations'),'previous451 native App tracking table already exists');native452Check((int)$db->query('SELECT version FROM `'.$p.'migrations`')->row_array()['version']===451,'actual451 App marker is preserved; no manual previous-marker insert');
$GLOBALS['native452Hooks']=new class{public array$trace=[];public function do_action($name,...$args){$this->trace[]=$name;}};
$ci=(object)['db'=>$db];$GLOBALS['native452CI']=$ci;$ci->config=new class($config){public function __construct(private array$values){}public function item($name){return$this->values[$name]??null;}};$ci->load=new class{public function config($name){}public function library($name,$options){}};
$reflection=new ReflectionClass(App_Migration::class);$migration=$reflection->newInstanceWithoutConstructor();foreach(['_migration_type'=>'sequential','_migration_path'=>APPPATH.'migrations/','_migration_version'=>452,'_migration_table'=>$p.'migrations','_migration_regex'=>'/^\\d{3}_(\\w+)$/']as$key=>$value)$reflection->getProperty($key)->setValue($migration,$value);$ci->migration=$migration;
$reflection=new ReflectionClass(App::class);$app=$reflection->newInstanceWithoutConstructor();$reflection->getProperty('ci')->setValue($app,$ci);$run=$reflection->getMethod('upgrade_database_silent');
$marker=static fn()=>(int)$app->get_current_db_version();$completed=static fn()=>count(array_filter(hooks()->trace,static fn($name)=>$name==='database_updated'));
native452Check(IssuerCore::ready($db,$p)&&$marker()===451,'actual previous451 history has persisted starting authority and marker');
// The old native436 fixture creates the shipped mail_queue after initial core reconciliation.
// Run the existing historical reconciler BEFORE capture, as native441_tracking does.
// This may repair its old nullable issuer_id; it is not schema work performed by NOOP452.
$preparationRows=[];foreach(['issuers_registry','company_projects','company_tasks','company_standalone_tasks','company_project_requests']as$table){$captured=array_map('serialize',$db->query('SELECT * FROM `'.$p.$table.'`')->result_array());sort($captured,SORT_STRING);$preparationRows[$table]=$captured;}
IssuerCore::reconcile($db,$p);
native452Check(IssuerCore::ready($db,$p)&&$marker()===451,'historical reconciliation precedes452 capture and retains451');
foreach($preparationRows as$table=>$captured){$actual=array_map('serialize',$db->query('SELECT * FROM `'.$p.$table.'`')->result_array());sort($actual,SORT_STRING);native452Check($actual===$captured,'historical reconciler preserves own preferences tasks and receipts before452 capture '.$table);}

$fixtures=$db->query('SELECT id,settings_json FROM '.$p.'issuers_registry WHERE active=1 ORDER BY id')->result_array();$preferenceCount=0;foreach($fixtures as$row){$doc=json_decode($row['settings_json'],true,32,JSON_THROW_ON_ERROR);if(isset($doc['task_creation_preferences'])){$v=$doc['task_creation_preferences'];native452Check(array_keys($v)===['format','revision','default_task_status','new_task_auto_assign_current_member']&&$v['format']===1&&$v['revision']===1&&in_array($v['default_task_status'],['todo','in_progress'],true)&&in_array($v['new_task_auto_assign_current_member'],['0','1'],true),'canonical own task preference exists before tracking452');$preferenceCount++;}}
native452Check($preferenceCount===3,'A B and principal own task defaults predate capture');
foreach(['task_created'=>'company_tasks','standalone_task_created'=>'company_standalone_tasks']as$operation=>$table){$found=0;foreach($fixtures as$row){$issuer=(int)$row['id'];$key=hash('sha256','native446-'.$operation.'-'.$issuer);$receipt=$db->query('SELECT * FROM '.$p.'company_project_requests WHERE request_key=?',[$key])->row_array();if(!$receipt)continue;$task=$db->query('SELECT * FROM '.$p.$table.' WHERE issuer_id=? AND id=?',[$issuer,$receipt['result_id']])->row_array();native452Check((int)$receipt['actor_id']===7&&$receipt['operation']===$operation&&preg_match('/^[a-f0-9]{64}$/D',$receipt['input_hash'])===1&&str_starts_with($task['name']??'','Native446 retained task '),'actual current writer task and receipt predate capture');$doc=json_decode($row['settings_json'],true,32,JSON_THROW_ON_ERROR);native452Check($task['status']===$doc['task_creation_preferences']['default_task_status']&&(int)$task['assignee_id']===7,'pre-capture task uses own stored policy');$found++;}native452Check($found===3,'three current writer tasks/receipts predate452 capture for '.$operation);}
$timerPolicies=0;foreach($fixtures as$row){$doc=json_decode($row['settings_json'],true,32,JSON_THROW_ON_ERROR);if(isset($doc['task_timer_preferences'])){native452Check($doc['task_timer_preferences']===['format'=>1,'revision'=>1,'timer_started_change_status_in_progress'=>'1'],'own timer policy is canonical before452 NOOP capture');$timerPolicies++;}}native452Check($timerPolicies===1,'only explicitly configured primary timer policy predates452; no backfill into A/B');
$tables=static function()use($db,$p):array{$names=array_column($db->query('SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND LEFT(TABLE_NAME,?)=?',[strlen($p),$p])->result_array(),'TABLE_NAME');sort($names,SORT_STRING);return$names;};
$rows=static function(string$table)use($db):array{$out=array_map('serialize',$db->query('SELECT * FROM `'.$table.'`')->result_array());sort($out,SORT_STRING);return$out;};
$metadata=static fn(string$table)=>(string)$db->query('SHOW CREATE TABLE `'.$table.'`')->row_array()['Create Table'];
$oldTables=$tables();$before=[];foreach($oldTables as$table)$before[$table]=['metadata'=>$metadata($table),'rows'=>$rows($table)];$ddl=count($db->ddl);
$confirmHistory=static function()use($db,$p,$tables,$oldTables,$before,$rows,$metadata):void{
 native452Check($tables()===$oldTables,'NOOP452 actual executor keeps exact table set');
 foreach($before as$table=>$captured){native452Check($metadata($table)===$captured['metadata'],'executor452 preserves full historical metadata '.$table);if($table!==$p.'migrations')native452Check($rows($table)===$captured['rows'],'executor452 preserves every historical row byte '.$table);}
};
$db->failMarker=true;$result=$run->invoke($app);native452Check(!$result['success']&&$marker()===451&&$completed()===0&&$db->markerFailures>0,'failed marker cannot claim452 or completion');$confirmHistory();$db->failMarker=false;
$db->ignoreMarkerSql=true;$result=$run->invoke($app);native452Check(!$result['success']&&$marker()===451&&$completed()===0&&$db->ignoredMarkerWrites>0,'ignored UPDATE returning true fails persisted452 readback');$confirmHistory();$db->ignoreMarkerSql=false;
$result=$run->invoke($app);native452Check($result['success']&&$marker()===452&&$completed()===1,'retry confirms persisted452 and one completion');$confirmHistory();
$result=$run->invoke($app);native452Check($result['success']&&$marker()===452&&$completed()===2,'confirmed452 replay preserves marker and completed runner contract');$confirmHistory();
foreach(array_slice($db->ddl,$ddl)as$sql)native452Check(preg_match('/^CREATE TABLE IF NOT EXISTS `?([A-Za-z0-9_]+)`?/',$sql,$match)===1&&in_array($match[1],$oldTables,true),'runner reconciliation only ensures existing tables; NOOP452 adds/modifies no metadata');
echo 'Native452 actual executor confirmed: '.$GLOBALS['native452Checks'].' checks; official NOOP, failed/ignored marker SQL readback, retry/replay and exact historical metadata/task-settings/rows/receipts.'.PHP_EOL;

require_once APPPATH.'services/CompanyTaskTimerStopPreferences.php';
$stopSettings=new CompanyTaskTimerStopPreferences($db,$p);foreach($fixtures as$row){native452Check($stopSettings->page((int)$row['id'],7)===['revision'=>0,'auto_stop_tasks_timers_on_new_timer'=>'0'],'new own stop preference remains absent/OFF without published-data backfill');}
$confirmHistory();
echo 'Native452 own auto-stop missing-OFF proof: '.$GLOBALS['native452Checks'].' checks, no schema/JSON backfill.'.PHP_EOL;
