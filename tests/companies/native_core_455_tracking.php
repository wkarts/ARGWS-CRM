<?php
/** Actual App/CI migration runner, synthetic SQL. Never forges a454 starting marker. */
declare(strict_types=1);
if(PHP_SAPI!=='cli'||!preg_match('/^essential_[a-f0-9]{10}_$/D',$argv[1]??'')||!str_starts_with(getenv('ISSUERS_TEST_DSN')?:'','mysql:'))exit(2);
define('BASEPATH',dirname(__DIR__,2).'/system/');define('APPPATH',dirname(__DIR__,2).'/application/');
require dirname(__DIR__).'/support/BbPdoDatabase.php';
require BASEPATH.'libraries/Migration.php';require APPPATH.'libraries/App_Migration.php';require APPPATH.'libraries/App.php';
require APPPATH.'services/issuers/IssuerCore.php';require APPPATH.'services/CompanyPurchasesSchema.php';
function db_prefix(){return $GLOBALS['native455Prefix'];}function &get_instance(){return $GLOBALS['native455CI'];}
function config_item($name){return $name==='subclass_prefix'?'App_':null;}function log_message($level,$message){$GLOBALS['native455Logs'][]=[$level,$message];}
function hooks(){return $GLOBALS['native455Hooks'];}function delete_option($name){}function add_option($name,$value){}
function native455Check(bool $ok,string $message):void{++$GLOBALS['native455Checks'];if(!$ok)throw new RuntimeException($message);}
final class Native455Database extends BbPdoDatabase
{
    public bool $failMarker=false;public bool $ignoreMarker=false;public ?string $failSchemaTable=null;
    public int $markerFailures=0;public int $ignoredWrites=0;public int $schemaFailures=0;
    public function query($sql,$bind=[]){
        if($this->failSchemaTable!==null&&str_starts_with($sql,'CREATE TABLE IF NOT EXISTS `'.$this->failSchemaTable.'`')){++$this->schemaFailures;return false;}
        if(preg_match('/^UPDATE `?'.preg_quote(db_prefix().'migrations','/').'`? SET /i',$sql)){
            if($this->failMarker){++$this->markerFailures;return false;}
            if($this->ignoreMarker){++$this->ignoredWrites;return true;}
        }
        return parent::query($sql,$bind);
    }
}
$GLOBALS['native455Prefix']=$argv[1];$GLOBALS['native455Checks']=0;$p=db_prefix();
$db=new Native455Database(new PDO(getenv('ISSUERS_TEST_DSN'),getenv('ISSUERS_TEST_USER')?:null,getenv('ISSUERS_TEST_PASSWORD')?:null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]));
$config=[];require APPPATH.'config/migration.php';
$version=trim(file_get_contents(dirname(APPPATH).'/VERSION'));
native455Check(preg_match('/^(\d)\.(\d)\.(\d)$/D',$version,$parts)===1,'canonical core version');
$shipped=(int)($parts[1].$parts[2].$parts[3]);native455Check($config['migration_version']===$shipped&&$shipped>=455,'shipped version supports historical455');
$config['migration_version']=455;
$GLOBALS['native455Hooks']=new class{public array $trace=[];public function do_action($name,...$args){$this->trace[]=$name;}};
$ci=(object)['db'=>$db];$GLOBALS['native455CI']=$ci;
$ci->config=new class($config){public function __construct(private array $values){}public function item($name){return $this->values[$name]??null;}};
$ci->load=new class{public function config($name){}public function library($name,$options){}};
$reflection=new ReflectionClass(App_Migration::class);$migration=$reflection->newInstanceWithoutConstructor();
foreach(['_migration_type'=>'sequential','_migration_path'=>APPPATH.'migrations/','_migration_version'=>455,'_migration_table'=>$p.'migrations','_migration_regex'=>'/^\\d{3}_(\\w+)$/'] as $key=>$value)$reflection->getProperty($key)->setValue($migration,$value);
$ci->migration=$migration;$reflection=new ReflectionClass(App::class);$app=$reflection->newInstanceWithoutConstructor();$reflection->getProperty('ci')->setValue($app,$ci);$run=$reflection->getMethod('upgrade_database_silent');
$marker=static fn()=>(int)$app->get_current_db_version();
$completed=static fn()=>count(array_filter(hooks()->trace,static fn($name)=>$name==='database_updated'));
native455Check(IssuerCore::ready($db,$p)&&$marker()===454,'actual previous native runner persisted454');
$tables=static function()use($db,$p):array{$names=array_column($db->query('SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND LEFT(TABLE_NAME,?)=?',[strlen($p),$p])->result_array(),'TABLE_NAME');sort($names,SORT_STRING);return $names;};
$rows=static function(string $table)use($db):array{$out=array_map('serialize',$db->query('SELECT * FROM `'.$table.'`')->result_array());sort($out,SORT_STRING);return $out;};
$metadata=static fn(string $table)=>(string)$db->query('SHOW CREATE TABLE `'.$table.'`')->row_array()['Create Table'];
$oldTables=$tables();$before=[];
foreach($oldTables as $table)$before[$table]=['metadata'=>$metadata($table),'rows'=>$rows($table)];
$newNames=array_keys(CompanyPurchasesSchema::definitions($p));
$newTables=array_map(static fn($name)=>$p.$name,$newNames);sort($newTables,SORT_STRING);
native455Check(!array_intersect($oldTables,$newTables),'new455 structures do not preexist in the genuine454 database');
$confirmHistory=static function()use($tables,$oldTables,$before,$rows,$metadata,$newTables,$p):void{
    native455Check(!array_diff($oldTables,$tables())&&!array_diff($tables(),array_merge($oldTables,$newTables)),'only finite additive455 tables admitted');
    foreach($before as $table=>$captured){
        native455Check($metadata($table)===$captured['metadata'],'historical metadata preserved '.$table);
        if($table!==$p.'migrations')native455Check($rows($table)===$captured['rows'],'historical rows preserved '.$table);
    }
};
$confirmNew=static function()use($db,$p,$tables,$oldTables,$newTables,$newNames):void{
    $all=array_merge($oldTables,$newTables);sort($all,SORT_STRING);native455Check($tables()===$all,'exact thirteen455 tables created');
    CompanyPurchasesSchema::assertValid($db,$p);
    foreach($newNames as $name){
        native455Check((int)$db->query('SELECT COUNT(*) n FROM '.$p.$name)->row_array()['n']===0,'no supplier, settings, authorization, expense or payable backfill '.$name);
    }
};
foreach($newNames as $name){
    $previousFailures=$db->schemaFailures;$db->failSchemaTable=$p.$name;$result=$run->invoke($app);
    native455Check(!$result['success']&&$marker()===454&&$completed()===0&&$db->schemaFailures===$previousFailures+1,'Failure at each additive DDL never advances marker or completion: '.$name);
    $confirmHistory();
}
$db->failSchemaTable=null;
$db->failMarker=true;$result=$run->invoke($app);
native455Check(!$result['success']&&$marker()===454&&$completed()===0&&$db->markerFailures>0,'failed marker never reports455 completion');
$confirmHistory();$confirmNew();$db->failMarker=false;
$db->ignoreMarker=true;$result=$run->invoke($app);
native455Check(!$result['success']&&$marker()===454&&$completed()===0&&$db->ignoredWrites>0,'ignored SQL fails the persisted marker readback');
$confirmHistory();$confirmNew();$db->ignoreMarker=false;
$result=$run->invoke($app);native455Check($result['success']&&$marker()===455&&$completed()===1,'retry advances to455 once confirmed');
$confirmHistory();$confirmNew();
$result=$run->invoke($app);native455Check($result['success']&&$marker()===455&&$completed()===2,'native replay is successful and preserves state');
$confirmHistory();$confirmNew();
echo 'Native455 runner: '.$GLOBALS['native455Checks'].' checks; thirteen additive tables, genuine454, DDL/marker failure, ignored SQL, retry/replay, full historical preservation.'.PHP_EOL;
