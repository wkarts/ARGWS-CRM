<?php
/** Real production executor for additive442 with failing and unconfirmed marker writes. */
declare(strict_types=1);
if(PHP_SAPI!=='cli'||!preg_match('/^essential_[a-f0-9]{10}_$/D',$argv[1]??'')||!str_starts_with(getenv('ISSUERS_TEST_DSN')?:'','mysql:'))exit(2);
define('BASEPATH',dirname(__DIR__,2).'/system/');define('APPPATH',dirname(__DIR__,2).'/application/');
require dirname(__DIR__).'/support/BbPdoDatabase.php';require BASEPATH.'libraries/Migration.php';require APPPATH.'libraries/App_Migration.php';require APPPATH.'libraries/App.php';require APPPATH.'services/issuers/IssuerCore.php';
function db_prefix(){return$GLOBALS['native442Prefix'];}function &get_instance(){return$GLOBALS['native442CI'];}function config_item($name){return$name==='subclass_prefix'?'App_':null;}function log_message($level,$message){$GLOBALS['native442Logs'][]=[$level,$message];}function hooks(){return$GLOBALS['native442Hooks'];}function delete_option($name){}function add_option($name,$value){}
function native442Check(bool$ok,string$message):void{++$GLOBALS['native442Checks'];if(!$ok)throw new RuntimeException($message);}
final class Native442Database extends BbPdoDatabase{
    public bool$failMarker=false;public bool$pretendMarker=false;public ?string$failDdl=null;public int$ddlFailures=0;public array$ddl=[];
    public function query($sql,$bind=[]){
        if(preg_match('/^(?:CREATE|ALTER|DROP) /',$sql))$this->ddl[]=$sql;
        if($this->failDdl!==null&&str_contains($sql,$this->failDdl)){++$this->ddlFailures;return false;}
        return parent::query($sql,$bind);
    }
    public function update($table,$values){if($table===db_prefix().'migrations'){if($this->failMarker)return false;if($this->pretendMarker)return true;}return parent::update($table,$values);}
}
$GLOBALS['native442Prefix']=$argv[1];$GLOBALS['native442Checks']=0;$p=db_prefix();
$db=new Native442Database(new PDO(getenv('ISSUERS_TEST_DSN'),getenv('ISSUERS_TEST_USER')?:null,getenv('ISSUERS_TEST_PASSWORD')?:null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]));
$config=[];require APPPATH.'config/migration.php';$shippedVersion=trim(file_get_contents(dirname(APPPATH).'/VERSION'));
native442Check(preg_match('/^(\d)\.(\d)\.(\d)$/D',$shippedVersion,$shippedParts)===1,'canonical native version shape');
$shippedMarker=(int)($shippedParts[1].$shippedParts[2].$shippedParts[3]);
native442Check(($config['migration_version']??null)===$shippedMarker&&$shippedMarker>=442,'shipped configuration exactly matches canonical source and supports historical442');
$config['migration_version']=442; // Explicit historical boundary, using the real production executor.

// Isolated historical preparation, before snapshots and fault injection; not a production downgrade.
require_once dirname(__DIR__).'/support/NativeHistoricalTrackingFixture.php';
NativeHistoricalTrackingFixture::prepare($db,$p,441,'native442Check');
$GLOBALS['native442Hooks']=new class{public array$trace=[];public function do_action($name,...$args){$this->trace[]=$name;}};
$ci=(object)['db'=>$db];$GLOBALS['native442CI']=$ci;$ci->config=new class($config){public function __construct(private array$values){}public function item($name){return$this->values[$name]??null;}};$ci->load=new class{public function config($name){}public function library($name,$options){}};
$reflection=new ReflectionClass(App_Migration::class);$migration=$reflection->newInstanceWithoutConstructor();foreach(['_migration_type'=>'sequential','_migration_path'=>APPPATH.'migrations/','_migration_version'=>442,'_migration_table'=>$p.'migrations','_migration_regex'=>'/^\\d{3}_(\\w+)$/']as$key=>$value)$reflection->getProperty($key)->setValue($migration,$value);$ci->migration=$migration;
$reflection=new ReflectionClass(App::class);$app=$reflection->newInstanceWithoutConstructor();$reflection->getProperty('ci')->setValue($app,$ci);$run=$reflection->getMethod('upgrade_database_silent');
$marker=static fn()=>(int)$app->get_current_db_version();$completed=static fn()=>count(array_filter(hooks()->trace,static fn($name)=>$name==='database_updated'));
IssuerCore::reconcile($db,$p);native442Check(IssuerCore::ready($db,$p)&&$marker()===441,'synthetic441 fixture has already reconciled its native company foundation before capture');
$tables=array_column($db->query('SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND LEFT(TABLE_NAME,?)=?',[strlen($p),$p])->result_array(),'TABLE_NAME');$before=[];foreach($tables as$table)if($table!==$p.'migrations'){$rows=array_map('serialize',$db->query('SELECT * FROM `'.$table.'`')->result_array());sort($rows,SORT_STRING);$before[$table]=$rows;}
$db->failDdl='CREATE TABLE IF NOT EXISTS `'.$p.'company_ai_requests`';$result=$run->invoke($app);
native442Check(!$result['success']&&$marker()===441&&$completed()===0&&$db->ddlFailures===1,'partial442 DDL cannot advance441 or publish completion');
native442Check($db->table_exists($p.'company_expense_copies')&&!$db->table_exists($p.'company_ai_requests'),'real partial442 creates expense provenance before failed AI table');
$db->failDdl=null;$db->failMarker=true;$result=$run->invoke($app);native442Check(!$result['success']&&$marker()===441&&$completed()===0,'failed442 tracking leaves previous marker after successful DDL');$db->failMarker=false;
foreach([['company_expense_copies','review_json','mediumtext'],['company_ai_requests','response_cipher','longtext']]as[$table,$column,$type]){
 native442Check($db->query('ALTER TABLE `'.$p.$table.'` MODIFY `'.$column.'` '.$type.' CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL')!==false,'real metadata fault '.$table);
 $result=$run->invoke($app);native442Check(!$result['success']&&$marker()===441&&$completed()===0,'bad442 metadata cannot advance marker '.$table);
 native442Check($db->query('ALTER TABLE `'.$p.$table.'` MODIFY `'.$column.'` '.$type.' CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL')!==false,'metadata restored '.$table);
}
native442Check($db->query('ALTER TABLE `'.$p.'company_expenses` DROP INDEX `expense_copy_parent`, ADD KEY `expense_copy_parent` (`issuer_id`,`id`)')!==false,'real parent uniqueness fault');
$result=$run->invoke($app);native442Check(!$result['success']&&$marker()===441&&$completed()===0,'nonunique442 parent index cannot advance marker');
native442Check($db->query('ALTER TABLE `'.$p.'company_expenses` DROP INDEX `expense_copy_parent`, ADD UNIQUE KEY `expense_copy_parent` (`issuer_id`,`id`)')!==false,'parent uniqueness restored');
$db->pretendMarker=true;$result=$run->invoke($app);native442Check(!$result['success']&&$marker()===441&&$completed()===0,'pretended442 write rejected by persisted readback');$db->pretendMarker=false;
$result=$run->invoke($app);native442Check($result['success']&&$marker()===442&&$completed()===1,'retry publishes442 only after complete valid schema and persisted marker');
$newDdl=static fn()=>count(array_filter($db->ddl,static fn($sql)=>str_contains($sql,$p.'company_expense_copies')||str_contains($sql,$p.'company_ai_requests')||str_contains($sql,'expense_copy_parent')));$ddl=$newDdl();$result=$run->invoke($app);
native442Check($result['success']&&$marker()===442&&$completed()===2&&$newDdl()===$ddl,'confirmed442 replay never reruns new DDL');
$afterTables=array_column($db->query('SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND LEFT(TABLE_NAME,?)=?',[strlen($p),$p])->result_array(),'TABLE_NAME');$added=array_values(array_diff($afterTables,$tables));sort($added);$wanted=[$p.'company_ai_requests',$p.'company_expense_copies'];sort($wanted);native442Check($added===$wanted,'actual442 executor adds exactly its two tables');
foreach($wanted as$table)native442Check((int)$db->query('SELECT COUNT(*) n FROM `'.$table.'`')->row_array()['n']===0,'no automatic442 history adoption '.$table);
foreach($before as$table=>$rows){$after=array_map('serialize',$db->query('SELECT * FROM `'.$table.'`')->result_array());sort($after,SORT_STRING);native442Check($rows===$after,'executor442 preserves complete historical rows '.$table);}
echo 'Native442 actual executor confirmed: '.$GLOBALS['native442Checks'].' checks; partial DDL, metadata/index, failed and false marker readback, retry/replay and historical bytes.'.PHP_EOL;
