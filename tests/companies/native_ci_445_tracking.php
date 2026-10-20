<?php
/** Real App runner: NOOP445 marker failures/ignored SQL/readback, retry/replay, no schema changes. */
declare(strict_types=1);
if(PHP_SAPI!=='cli'||!preg_match('/^essential_[a-f0-9]{10}_$/D',$argv[1]??'')||!str_starts_with(getenv('ISSUERS_TEST_DSN')?:'','mysql:'))exit(2);
define('BASEPATH',dirname(__DIR__,2).'/system/');define('APPPATH',dirname(__DIR__,2).'/application/');
require dirname(__DIR__).'/support/BbPdoDatabase.php';require BASEPATH.'libraries/Migration.php';require APPPATH.'libraries/App_Migration.php';require APPPATH.'libraries/App.php';require APPPATH.'services/issuers/IssuerCore.php';
function db_prefix(){return$GLOBALS['native445Prefix'];}function &get_instance(){return$GLOBALS['native445CI'];}function config_item($name){return$name==='subclass_prefix'?'App_':null;}function log_message($level,$message){$GLOBALS['native445Logs'][]=[$level,$message];}function hooks(){return$GLOBALS['native445Hooks'];}function delete_option($name){}function add_option($name,$value){}
function native445Check(bool$ok,string$message):void{++$GLOBALS['native445Checks'];if(!$ok)throw new RuntimeException($message);}
final class Native445Database extends BbPdoDatabase{
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
$GLOBALS['native445Prefix']=$argv[1];$GLOBALS['native445Checks']=0;$p=db_prefix();
$db=new Native445Database(new PDO(getenv('ISSUERS_TEST_DSN'),getenv('ISSUERS_TEST_USER')?:null,getenv('ISSUERS_TEST_PASSWORD')?:null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]));
$config=[];require APPPATH.'config/migration.php';$shippedVersion=trim(file_get_contents(dirname(APPPATH).'/VERSION'));
native445Check(preg_match('/^(\d)\.(\d)\.(\d)$/D',$shippedVersion,$shippedParts)===1,'canonical shipped native version shape');$shippedMarker=(int)($shippedParts[1].$shippedParts[2].$shippedParts[3]);
native445Check(($config['migration_version']??null)===$shippedMarker&&$shippedMarker>=445,'shipped configuration matches canonical VERSION and supports historical445');
$config['migration_version']=445; // Explicit historical target, also valid when shipped by a later source tree.
native445Check($db->query('CREATE TABLE `'.$p.'migrations` (version bigint NOT NULL) ENGINE=InnoDB')!==false,'actual tracking fixture table');native445Check($db->insert($p.'migrations',['version'=>444]),'actual persisted starting marker444');
$GLOBALS['native445Hooks']=new class{public array$trace=[];public function do_action($name,...$args){$this->trace[]=$name;}};
$ci=(object)['db'=>$db];$GLOBALS['native445CI']=$ci;$ci->config=new class($config){public function __construct(private array$values){}public function item($name){return$this->values[$name]??null;}};$ci->load=new class{public function config($name){}public function library($name,$options){}};
$reflection=new ReflectionClass(App_Migration::class);$migration=$reflection->newInstanceWithoutConstructor();foreach(['_migration_type'=>'sequential','_migration_path'=>APPPATH.'migrations/','_migration_version'=>445,'_migration_table'=>$p.'migrations','_migration_regex'=>'/^\\d{3}_(\\w+)$/']as$key=>$value)$reflection->getProperty($key)->setValue($migration,$value);$ci->migration=$migration;
$reflection=new ReflectionClass(App::class);$app=$reflection->newInstanceWithoutConstructor();$reflection->getProperty('ci')->setValue($app,$ci);$run=$reflection->getMethod('upgrade_database_silent');
$marker=static fn()=>(int)$app->get_current_db_version();$completed=static fn()=>count(array_filter(hooks()->trace,static fn($name)=>$name==='database_updated'));
native445Check(IssuerCore::ready($db,$p)&&$marker()===444,'actual previous444 history has persisted starting authority and marker');
// The old native436 fixture creates the shipped mail_queue after initial core reconciliation.
// Run the existing historical reconciler BEFORE capture, as native441_tracking does.
// This may repair its old nullable issuer_id; it is not schema work performed by NOOP445.
$preparationRows=[];foreach(['issuers_registry','company_tickets','company_ticket_replies']as$table){$captured=array_map('serialize',$db->query('SELECT * FROM `'.$p.$table.'`')->result_array());sort($captured,SORT_STRING);$preparationRows[$table]=$captured;}
IssuerCore::reconcile($db,$p);
native445Check(IssuerCore::ready($db,$p)&&$marker()===444,'historical reconciliation precedes445 capture and retains444');
foreach($preparationRows as$table=>$captured){$actual=array_map('serialize',$db->query('SELECT * FROM `'.$p.$table.'`')->result_array());sort($actual,SORT_STRING);native445Check($actual===$captured,'historical reconciler preserves own preferences and replies before445 capture '.$table);}

$fixtures=$db->query('SELECT settings_json FROM '.$p.'issuers_registry WHERE active=1 ORDER BY id')->result_array();$preferenceCount=0;foreach($fixtures as$row){$doc=json_decode($row['settings_json'],true,32,JSON_THROW_ON_ERROR);if(isset($doc['ticket_preferences'])){native445Check($doc['ticket_preferences']['format']===1&&$doc['ticket_preferences']['revision']===1&&in_array($doc['ticket_preferences']['ticket_replies_order'],['asc','desc'],true),'canonical own ticket preference exists before tracking445');$preferenceCount++;}}
native445Check($preferenceCount===3,'A B and principal own preferences predate capture');native445Check((int)$db->query("SELECT COUNT(*) n FROM {$p}company_ticket_replies r JOIN {$p}company_tickets t ON t.issuer_id=r.issuer_id AND t.id=r.ticket_id WHERE t.subject LIKE 'Native444 retained preference %'")->row_array()['n']===6,'six real own private replies predate capture');
$tables=static function()use($db,$p):array{$names=array_column($db->query('SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND LEFT(TABLE_NAME,?)=?',[strlen($p),$p])->result_array(),'TABLE_NAME');sort($names,SORT_STRING);return$names;};
$rows=static function(string$table)use($db):array{$out=array_map('serialize',$db->query('SELECT * FROM `'.$table.'`')->result_array());sort($out,SORT_STRING);return$out;};
$metadata=static fn(string$table)=>(string)$db->query('SHOW CREATE TABLE `'.$table.'`')->row_array()['Create Table'];
$oldTables=$tables();$before=[];foreach($oldTables as$table)$before[$table]=['metadata'=>$metadata($table),'rows'=>$rows($table)];$ddl=count($db->ddl);
$confirmHistory=static function()use($db,$p,$tables,$oldTables,$before,$rows,$metadata):void{
 native445Check($tables()===$oldTables,'NOOP445 actual executor keeps exact table set');
 foreach($before as$table=>$captured){native445Check($metadata($table)===$captured['metadata'],'executor445 preserves full historical metadata '.$table);if($table!==$p.'migrations')native445Check($rows($table)===$captured['rows'],'executor445 preserves every historical row byte '.$table);}
};
$db->failMarker=true;$result=$run->invoke($app);native445Check(!$result['success']&&$marker()===444&&$completed()===0&&$db->markerFailures>0,'failed marker cannot claim445 or completion');$confirmHistory();$db->failMarker=false;
$db->ignoreMarkerSql=true;$result=$run->invoke($app);native445Check(!$result['success']&&$marker()===444&&$completed()===0&&$db->ignoredMarkerWrites>0,'ignored UPDATE returning true fails persisted445 readback');$confirmHistory();$db->ignoreMarkerSql=false;
$result=$run->invoke($app);native445Check($result['success']&&$marker()===445&&$completed()===1,'retry confirms persisted445 and one completion');$confirmHistory();
$result=$run->invoke($app);native445Check($result['success']&&$marker()===445&&$completed()===2,'confirmed445 replay preserves marker and completed runner contract');$confirmHistory();
foreach(array_slice($db->ddl,$ddl)as$sql)native445Check(preg_match('/^CREATE TABLE IF NOT EXISTS `?([A-Za-z0-9_]+)`?/',$sql,$match)===1&&in_array($match[1],$oldTables,true),'runner reconciliation only ensures existing tables; NOOP445 adds/modifies no metadata');
echo 'Native445 actual executor confirmed: '.$GLOBALS['native445Checks'].' checks; official NOOP, failed/ignored marker SQL readback, retry/replay and exact historical metadata/settings/replies.'.PHP_EOL;
