<?php
/** Actual App/CI migration runner, synthetic SQL. Never forges a455 starting marker. */
declare(strict_types=1);
if(PHP_SAPI!=='cli'||!preg_match('/^essential_[a-f0-9]{10}_$/D',$argv[1]??'')||!str_starts_with(getenv('ISSUERS_TEST_DSN')?:'','mysql:'))exit(2);
define('BASEPATH',dirname(__DIR__,2).'/system/');define('APPPATH',dirname(__DIR__,2).'/application/');
require dirname(__DIR__).'/support/BbPdoDatabase.php';
require BASEPATH.'libraries/Migration.php';require APPPATH.'libraries/App_Migration.php';require APPPATH.'libraries/App.php';
require APPPATH.'services/issuers/IssuerCore.php';require APPPATH.'services/CompanyHrSchema.php';
function db_prefix(){return $GLOBALS['native456Prefix'];}function &get_instance(){return $GLOBALS['native456CI'];}
function config_item($name){return $name==='subclass_prefix'?'App_':null;}function log_message($level,$message){$GLOBALS['native456Logs'][]=[$level,$message];}
function hooks(){return $GLOBALS['native456Hooks'];}function delete_option($name){}function add_option($name,$value){}
function native456Check(bool $ok,string $message):void{++$GLOBALS['native456Checks'];if(!$ok)throw new RuntimeException($message);}
final class Native456Database extends BbPdoDatabase
{
    public bool $failMarker=false;public bool $ignoreMarker=false;public ?string $failSchemaTable=null;
    public int $markerFailures=0;public int $ignoredWrites=0;public int $schemaFailures=0;
    public bool $failSeed=false;public bool $ignoreSeed=false;public int $seedFailures=0;public int $ignoredSeeds=0;
    public function query($sql,$bind=[]){
        if($this->failSchemaTable!==null&&str_starts_with($sql,'CREATE TABLE IF NOT EXISTS `'.$this->failSchemaTable.'`')){++$this->schemaFailures;return false;}
        if(str_starts_with($sql,'INSERT INTO '.db_prefix().'company_hr_cursor(')){if($this->failSeed){++$this->seedFailures;return false;}if($this->ignoreSeed){++$this->ignoredSeeds;return true;}}
        if(preg_match('/^UPDATE `?'.preg_quote(db_prefix().'migrations','/').'`? SET /i',$sql)){
            if($this->failMarker){++$this->markerFailures;return false;}
            if($this->ignoreMarker){++$this->ignoredWrites;return true;}
        }
        return parent::query($sql,$bind);
    }
}
$GLOBALS['native456Prefix']=$argv[1];$GLOBALS['native456Checks']=0;$p=db_prefix();
$db=new Native456Database(new PDO(getenv('ISSUERS_TEST_DSN'),getenv('ISSUERS_TEST_USER')?:null,getenv('ISSUERS_TEST_PASSWORD')?:null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]));
$config=[];require APPPATH.'config/migration.php';
$version=trim(file_get_contents(dirname(APPPATH).'/VERSION'));
native456Check(preg_match('/^(\d)\.(\d)\.(\d)$/D',$version,$parts)===1,'canonical core version');
$shipped=(int)($parts[1].$parts[2].$parts[3]);native456Check($config['migration_version']===$shipped&&$shipped>=456,'shipped version supports historical456');
$config['migration_version']=456;
$GLOBALS['native456Hooks']=new class{public array $trace=[];public function do_action($name,...$args){$this->trace[]=$name;}};
$ci=(object)['db'=>$db];$GLOBALS['native456CI']=$ci;
$ci->config=new class($config){public function __construct(private array $values){}public function item($name){return $this->values[$name]??null;}};
$ci->load=new class{public function config($name){}public function library($name,$options){}};
$reflection=new ReflectionClass(App_Migration::class);$migration=$reflection->newInstanceWithoutConstructor();
foreach(['_migration_type'=>'sequential','_migration_path'=>APPPATH.'migrations/','_migration_version'=>456,'_migration_table'=>$p.'migrations','_migration_regex'=>'/^\\d{3}_(\\w+)$/'] as $key=>$value)$reflection->getProperty($key)->setValue($migration,$value);
$ci->migration=$migration;$reflection=new ReflectionClass(App::class);$app=$reflection->newInstanceWithoutConstructor();$reflection->getProperty('ci')->setValue($app,$ci);$run=$reflection->getMethod('upgrade_database_silent');
$marker=static fn()=>(int)$app->get_current_db_version();
$completed=static fn()=>count(array_filter(hooks()->trace,static fn($name)=>$name==='database_updated'));
native456Check(IssuerCore::ready($db,$p)&&$marker()===455,'actual previous native runner persisted455');
$tables=static function()use($db,$p):array{$names=array_column($db->query('SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND LEFT(TABLE_NAME,?)=?',[strlen($p),$p])->result_array(),'TABLE_NAME');sort($names,SORT_STRING);return $names;};
$rows=static function(string $table)use($db):array{$out=array_map('serialize',$db->query('SELECT * FROM `'.$table.'`')->result_array());sort($out,SORT_STRING);return $out;};
$metadata=static fn(string $table)=>(string)$db->query('SHOW CREATE TABLE `'.$table.'`')->row_array()['Create Table'];
$oldTables=$tables();$before=[];
foreach($oldTables as $table)$before[$table]=['metadata'=>$metadata($table),'rows'=>$rows($table)];
$newNames=array_keys(CompanyHrSchema::definitions($p));
$newTables=array_map(static fn($name)=>$p.$name,$newNames);sort($newTables,SORT_STRING);
native456Check(!array_intersect($oldTables,$newTables),'new456 structures do not preexist in the genuine455 database');
$confirmHistory=static function()use($tables,$oldTables,$before,$rows,$metadata,$newTables,$p):void{
    native456Check(!array_diff($oldTables,$tables())&&!array_diff($tables(),array_merge($oldTables,$newTables)),'only finite additive456 tables admitted');
    foreach($before as $table=>$captured){
        native456Check($metadata($table)===$captured['metadata'],'historical metadata preserved '.$table);
        if($table!==$p.'migrations')native456Check($rows($table)===$captured['rows'],'historical rows preserved '.$table);
    }
};
$confirmNew=static function(bool $seeded=true)use($db,$p,$tables,$oldTables,$newTables,$newNames):void{
    $all=array_merge($oldTables,$newTables);sort($all,SORT_STRING);native456Check($tables()===$all,'exact eighteen456 tables created');
    CompanyHrSchema::assertValid($db,$p);
    foreach($newNames as $name){
        if($name==='company_hr_cursor'){
            $cursor=$db->query('SELECT id,last_id,upper_id,revision FROM '.$p.$name.' ORDER BY id')->result_array();
            $normalized=array_map(static fn($row)=>array_map('intval',$row),$cursor);
            native456Check($normalized===($seeded?[['id'=>1,'last_id'=>0,'upper_id'=>0,'revision'=>1]]:[]),'one exact infrastructure cursor only after confirmed seed');
        }else native456Check((int)$db->query('SELECT COUNT(*) n FROM '.$p.$name)->row_array()['n']===0,'no employment, contract, training or access backfill '.$name);
    }
};
foreach($newNames as $name){
    $previousFailures=$db->schemaFailures;$db->failSchemaTable=$p.$name;$result=$run->invoke($app);
    native456Check(!$result['success']&&$marker()===455&&$completed()===0&&$db->schemaFailures===$previousFailures+1,'Failure at each additive DDL never advances marker or completion: '.$name);
    $confirmHistory();
}
$db->failSchemaTable=null;
$db->failSeed=true;$result=$run->invoke($app);
native456Check(!$result['success']&&$marker()===455&&$completed()===0&&$db->seedFailures>0,'failed cursor seed never advances the native marker');
$confirmHistory();$confirmNew(false);$db->failSeed=false;
$db->ignoreSeed=true;$result=$run->invoke($app);
native456Check(!$result['success']&&$marker()===455&&$completed()===0&&$db->ignoredSeeds>0,'ignored cursor seed fails persisted readback before native completion');
$confirmHistory();$confirmNew(false);$db->ignoreSeed=false;
$db->failMarker=true;$result=$run->invoke($app);
native456Check(!$result['success']&&$marker()===455&&$completed()===0&&$db->markerFailures>0,'failed marker never reports456 completion');
$confirmHistory();$confirmNew();$db->failMarker=false;
$db->ignoreMarker=true;$result=$run->invoke($app);
native456Check(!$result['success']&&$marker()===455&&$completed()===0&&$db->ignoredWrites>0,'ignored SQL fails the persisted marker readback');
$confirmHistory();$confirmNew();$db->ignoreMarker=false;
$result=$run->invoke($app);native456Check($result['success']&&$marker()===456&&$completed()===1,'retry advances to456 once confirmed');
$confirmHistory();$confirmNew();
$result=$run->invoke($app);native456Check($result['success']&&$marker()===456&&$completed()===2,'native replay is successful and preserves state');
$confirmHistory();$confirmNew();
echo 'Native456 runner: '.$GLOBALS['native456Checks'].' checks; eighteen additive tables, genuine455, DDL/marker failure, ignored SQL, retry/replay, full historical preservation.'.PHP_EOL;
