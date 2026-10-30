<?php
/** Actual App/CI migration runner, synthetic SQL. Never forges a452 starting marker. */
declare(strict_types=1);
if(PHP_SAPI!=='cli'||!preg_match('/^essential_[a-f0-9]{10}_$/D',$argv[1]??'')||!str_starts_with(getenv('ISSUERS_TEST_DSN')?:'','mysql:'))exit(2);
define('BASEPATH',dirname(__DIR__,2).'/system/');define('APPPATH',dirname(__DIR__,2).'/application/');
require dirname(__DIR__).'/support/BbPdoDatabase.php';
require BASEPATH.'libraries/Migration.php';require APPPATH.'libraries/App_Migration.php';require APPPATH.'libraries/App.php';
require APPPATH.'services/issuers/IssuerCore.php';require APPPATH.'services/CompanyStaffSchema.php';require APPPATH.'services/CompanyTaskRecurringSchema.php';
function db_prefix(){return $GLOBALS['native453Prefix'];}function &get_instance(){return $GLOBALS['native453CI'];}
function config_item($name){return $name==='subclass_prefix'?'App_':null;}function log_message($level,$message){$GLOBALS['native453Logs'][]=[$level,$message];}
function hooks(){return $GLOBALS['native453Hooks'];}function delete_option($name){}function add_option($name,$value){}
function native453Check(bool $ok,string $message):void{++$GLOBALS['native453Checks'];if(!$ok)throw new RuntimeException($message);}
final class Native453Database extends BbPdoDatabase
{
    public bool $failMarker=false;public bool $ignoreMarker=false;public bool $failSchema=false;
    public int $markerFailures=0;public int $ignoredWrites=0;public int $schemaFailures=0;
    public function query($sql,$bind=[]){
        if($this->failSchema&&str_starts_with($sql,'CREATE TABLE IF NOT EXISTS `'.db_prefix().'company_task_schedules`')){++$this->schemaFailures;return false;}
        if(preg_match('/^UPDATE `?'.preg_quote(db_prefix().'migrations','/').'`? SET /i',$sql)){
            if($this->failMarker){++$this->markerFailures;return false;}
            if($this->ignoreMarker){++$this->ignoredWrites;return true;}
        }
        return parent::query($sql,$bind);
    }
}
$GLOBALS['native453Prefix']=$argv[1];$GLOBALS['native453Checks']=0;$p=db_prefix();
$db=new Native453Database(new PDO(getenv('ISSUERS_TEST_DSN'),getenv('ISSUERS_TEST_USER')?:null,getenv('ISSUERS_TEST_PASSWORD')?:null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]));
$config=[];require APPPATH.'config/migration.php';
$version=trim(file_get_contents(dirname(APPPATH).'/VERSION'));
native453Check(preg_match('/^(\d)\.(\d)\.(\d)$/D',$version,$parts)===1,'canonical core version');
$shipped=(int)($parts[1].$parts[2].$parts[3]);native453Check($config['migration_version']===$shipped&&$shipped>=453,'shipped version supports historical453');
$config['migration_version']=453;
$GLOBALS['native453Hooks']=new class{public array $trace=[];public function do_action($name,...$args){$this->trace[]=$name;}};
$ci=(object)['db'=>$db];$GLOBALS['native453CI']=$ci;
$ci->config=new class($config){public function __construct(private array $values){}public function item($name){return $this->values[$name]??null;}};
$ci->load=new class{public function config($name){}public function library($name,$options){}};
$reflection=new ReflectionClass(App_Migration::class);$migration=$reflection->newInstanceWithoutConstructor();
foreach(['_migration_type'=>'sequential','_migration_path'=>APPPATH.'migrations/','_migration_version'=>453,'_migration_table'=>$p.'migrations','_migration_regex'=>'/^\\d{3}_(\\w+)$/'] as $key=>$value)$reflection->getProperty($key)->setValue($migration,$value);
$ci->migration=$migration;$reflection=new ReflectionClass(App::class);$app=$reflection->newInstanceWithoutConstructor();$reflection->getProperty('ci')->setValue($app,$ci);$run=$reflection->getMethod('upgrade_database_silent');
$marker=static fn()=>(int)$app->get_current_db_version();
$completed=static fn()=>count(array_filter(hooks()->trace,static fn($name)=>$name==='database_updated'));
native453Check(IssuerCore::ready($db,$p)&&$marker()===452,'actual previous native runner persisted452');
$tables=static function()use($db,$p):array{$names=array_column($db->query('SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND LEFT(TABLE_NAME,?)=?',[strlen($p),$p])->result_array(),'TABLE_NAME');sort($names,SORT_STRING);return $names;};
$rows=static function(string $table)use($db):array{$out=array_map('serialize',$db->query('SELECT * FROM `'.$table.'`')->result_array());sort($out,SORT_STRING);return $out;};
$metadata=static fn(string $table)=>(string)$db->query('SHOW CREATE TABLE `'.$table.'`')->row_array()['Create Table'];
$oldTables=$tables();$before=[];
foreach($oldTables as $table)$before[$table]=['metadata'=>$metadata($table),'rows'=>$rows($table)];
$newNames=['company_staff_commands','company_task_schedules','company_task_schedule_runs','company_task_schedule_requests','company_task_schedule_cursor'];
$newTables=array_map(static fn($name)=>$p.$name,$newNames);sort($newTables,SORT_STRING);
native453Check(!array_intersect($oldTables,$newTables),'new453 structures do not preexist in the genuine452 database');
$confirmHistory=static function()use($tables,$oldTables,$before,$rows,$metadata,$newTables,$p):void{
    native453Check(!array_diff($oldTables,$tables())&&!array_diff($tables(),array_merge($oldTables,$newTables)),'only finite additive453 tables admitted');
    foreach($before as $table=>$captured){
        native453Check($metadata($table)===$captured['metadata'],'historical metadata preserved '.$table);
        if($table!==$p.'migrations')native453Check($rows($table)===$captured['rows'],'historical rows preserved '.$table);
    }
};
$confirmNew=static function()use($db,$p,$tables,$oldTables,$newTables,$newNames):void{
    $all=array_merge($oldTables,$newTables);sort($all,SORT_STRING);native453Check($tables()===$all,'exact five453 tables created');
    CompanyStaffSchema::assertValid($db,$p);CompanyTaskRecurringSchema::assertValid($db,$p);
    foreach($newNames as $name){
        if($name==='company_task_schedule_cursor'){
            $value=$db->query('SELECT * FROM '.$p.$name)->result_array();
            native453Check(count($value)===1&&array_map('intval',$value[0])===['id'=>1,'last_id'=>0,'upper_id'=>0,'revision'=>1],'discovery cursor initialized without adopting tasks');
        }else native453Check((int)$db->query('SELECT COUNT(*) n FROM '.$p.$name)->row_array()['n']===0,'no history authorization or task backfill '.$name);
    }
};
$db->failSchema=true;$result=$run->invoke($app);
native453Check(!$result['success']&&$marker()===452&&$completed()===0&&$db->schemaFailures>0,'DDL failure never advances the native marker');
$confirmHistory();$db->failSchema=false;
$db->failMarker=true;$result=$run->invoke($app);
native453Check(!$result['success']&&$marker()===452&&$completed()===0&&$db->markerFailures>0,'failed marker never reports453 completion');
$confirmHistory();$confirmNew();$db->failMarker=false;
$db->ignoreMarker=true;$result=$run->invoke($app);
native453Check(!$result['success']&&$marker()===452&&$completed()===0&&$db->ignoredWrites>0,'ignored SQL fails the persisted marker readback');
$confirmHistory();$confirmNew();$db->ignoreMarker=false;
$result=$run->invoke($app);native453Check($result['success']&&$marker()===453&&$completed()===1,'retry advances to453 once confirmed');
$confirmHistory();$confirmNew();
$result=$run->invoke($app);native453Check($result['success']&&$marker()===453&&$completed()===2,'native replay is successful and preserves state');
$confirmHistory();$confirmNew();
echo 'Native453 runner: '.$GLOBALS['native453Checks'].' checks; five additive tables, genuine452, DDL/marker failure, ignored SQL, retry/replay, full historical preservation.'.PHP_EOL;
