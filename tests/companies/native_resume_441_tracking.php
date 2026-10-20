<?php
/** Real production executor for NOOP441 with failing and unconfirmed marker writes. */
declare(strict_types=1);
if(PHP_SAPI!=='cli'||!preg_match('/^essential_[a-f0-9]{10}_$/D',$argv[1]??'')||!str_starts_with(getenv('ISSUERS_TEST_DSN')?:'','mysql:'))exit(2);
define('BASEPATH',dirname(__DIR__,2).'/system/');define('APPPATH',dirname(__DIR__,2).'/application/');
require dirname(__DIR__).'/support/BbPdoDatabase.php';require BASEPATH.'libraries/Migration.php';require APPPATH.'libraries/App_Migration.php';require APPPATH.'libraries/App.php';require APPPATH.'services/issuers/IssuerCore.php';
function db_prefix(){return$GLOBALS['native441Prefix'];}function &get_instance(){return$GLOBALS['native441CI'];}function config_item($name){return$name==='subclass_prefix'?'App_':null;}function log_message($level,$message){$GLOBALS['native441Logs'][]=[$level,$message];}function hooks(){return$GLOBALS['native441Hooks'];}function delete_option($name){}function add_option($name,$value){}
function native441Check(bool$ok,string$message):void{++$GLOBALS['native441Checks'];if(!$ok)throw new RuntimeException($message);}
final class Native441Database extends BbPdoDatabase{
    public bool$failMarker=false;public bool$pretendMarker=false;public array$ddl=[];
    public function query($sql,$bind=[]){if(preg_match('/^(?:CREATE|ALTER|DROP) /',$sql))$this->ddl[]=$sql;return parent::query($sql,$bind);}
    public function update($table,$values){if($table===db_prefix().'migrations'){if($this->failMarker)return false;if($this->pretendMarker)return true;}return parent::update($table,$values);}
}
$GLOBALS['native441Prefix']=$argv[1];$GLOBALS['native441Checks']=0;$p=db_prefix();
$db=new Native441Database(new PDO(getenv('ISSUERS_TEST_DSN'),getenv('ISSUERS_TEST_USER')?:null,getenv('ISSUERS_TEST_PASSWORD')?:null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]));
$config=[];require APPPATH.'config/migration.php';$shippedVersion=trim(file_get_contents(dirname(APPPATH).'/VERSION'));
native441Check(preg_match('/^(\d)\.(\d)\.(\d)$/D',$shippedVersion,$shippedParts)===1,'canonical native version shape');
$shippedMarker=(int)($shippedParts[1].$shippedParts[2].$shippedParts[3]);
native441Check(($config['migration_version']??null)===$shippedMarker&&$shippedMarker>=441,'shipped configuration exactly matches canonical source and supports historical441');
$config['migration_version']=441; // Explicit historical boundary, using the real production executor.

native441Check($db->query('CREATE TABLE `'.$p.'migrations` (version bigint NOT NULL) ENGINE=InnoDB')!==false,'actual tracking table');$db->insert($p.'migrations',['version'=>440]);
$GLOBALS['native441Hooks']=new class{public array$trace=[];public function do_action($name,...$args){$this->trace[]=$name;}};
$ci=(object)['db'=>$db];$GLOBALS['native441CI']=$ci;$ci->config=new class($config){public function __construct(private array$values){}public function item($name){return$this->values[$name]??null;}};$ci->load=new class{public function config($name){}public function library($name,$options){}};
$reflection=new ReflectionClass(App_Migration::class);$migration=$reflection->newInstanceWithoutConstructor();foreach(['_migration_type'=>'sequential','_migration_path'=>APPPATH.'migrations/','_migration_version'=>441,'_migration_table'=>$p.'migrations','_migration_regex'=>'/^\\d{3}_(\\w+)$/']as$key=>$value)$reflection->getProperty($key)->setValue($migration,$value);$ci->migration=$migration;
$reflection=new ReflectionClass(App::class);$app=$reflection->newInstanceWithoutConstructor();$reflection->getProperty('ci')->setValue($app,$ci);$run=$reflection->getMethod('upgrade_database_silent');
$marker=static fn()=>(int)$app->get_current_db_version();$completed=static fn()=>count(array_filter(hooks()->trace,static fn($name)=>$name==='database_updated'));
IssuerCore::reconcile($db,$p);native441Check(IssuerCore::ready($db,$p)&&$marker()===440,'synthetic440 fixture has already reconciled its native company foundation before capture');
$tables=array_column($db->query('SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND LEFT(TABLE_NAME,?)=?',[strlen($p),$p])->result_array(),'TABLE_NAME');$before=[];foreach($tables as$table)if($table!==$p.'migrations'){$rows=array_map('serialize',$db->query('SELECT * FROM `'.$table.'`')->result_array());sort($rows,SORT_STRING);$before[$table]=$rows;}
$ddl=count($db->ddl);$db->failMarker=true;$result=$run->invoke($app);native441Check(!$result['success']&&$marker()===440&&$completed()===0,'failed441 marker cannot confirm completion');
$db->failMarker=false;$db->pretendMarker=true;$result=$run->invoke($app);native441Check(!$result['success']&&$marker()===440&&$completed()===0,'successful write return without persisted441 readback is rejected');
$db->pretendMarker=false;$result=$run->invoke($app);native441Check($result['success']&&$marker()===441&&$completed()===1,'retry confirms persisted441 only');
$result=$run->invoke($app);native441Check($result['success']&&$marker()===441,'replay441 keeps confirmed441');
foreach(array_slice($db->ddl,$ddl)as$sql){native441Check(preg_match('/^CREATE TABLE IF NOT EXISTS `?([A-Za-z0-9_]+)`?/',$sql,$match)===1&&in_array($match[1],$tables,true),'executor reconciliation only ensures an existing table; no441 schema change: '.substr($sql,0,160));}
foreach($before as$table=>$rows){$after=array_map('serialize',$db->query('SELECT * FROM `'.$table.'`')->result_array());sort($after,SORT_STRING);native441Check($rows===$after,'executor441 preserves historical rows '.$table);}
echo 'Native441 actual executor confirmed: '.$GLOBALS['native441Checks'].' checks; failed marker, false-positive write readback, retry, replay and historical bytes.'.PHP_EOL;
