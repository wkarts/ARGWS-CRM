<?php
/** Real production executor for additive443 with failing and unconfirmed marker writes. */
declare(strict_types=1);
if(PHP_SAPI!=='cli'||!preg_match('/^essential_[a-f0-9]{10}_$/D',$argv[1]??'')||!str_starts_with(getenv('ISSUERS_TEST_DSN')?:'','mysql:'))exit(2);
define('BASEPATH',dirname(__DIR__,2).'/system/');define('APPPATH',dirname(__DIR__,2).'/application/');
require dirname(__DIR__).'/support/BbPdoDatabase.php';require BASEPATH.'libraries/Migration.php';require APPPATH.'libraries/App_Migration.php';require APPPATH.'libraries/App.php';require APPPATH.'services/issuers/IssuerCore.php';
function db_prefix(){return$GLOBALS['native443Prefix'];}function &get_instance(){return$GLOBALS['native443CI'];}function config_item($name){return$name==='subclass_prefix'?'App_':null;}function log_message($level,$message){$GLOBALS['native443Logs'][]=[$level,$message];}function hooks(){return$GLOBALS['native443Hooks'];}function delete_option($name){}function add_option($name,$value){}
function native443Check(bool$ok,string$message):void{++$GLOBALS['native443Checks'];if(!$ok)throw new RuntimeException($message);}
final class Native443Database extends BbPdoDatabase{
    public bool$failMarker=false;public bool$pretendMarker=false;public ?string$failDdl=null;public int$ddlFailures=0;public array$ddl=[];
    public function query($sql,$bind=[]){
        if(preg_match('/^(?:CREATE|ALTER|DROP) /',$sql))$this->ddl[]=$sql;
        if($this->failDdl!==null&&str_contains($sql,$this->failDdl)){++$this->ddlFailures;return false;}
        return parent::query($sql,$bind);
    }
    public function update($table,$values){if($table===db_prefix().'migrations'){if($this->failMarker)return false;if($this->pretendMarker)return true;}return parent::update($table,$values);}
}
$GLOBALS['native443Prefix']=$argv[1];$GLOBALS['native443Checks']=0;$p=db_prefix();
$db=new Native443Database(new PDO(getenv('ISSUERS_TEST_DSN'),getenv('ISSUERS_TEST_USER')?:null,getenv('ISSUERS_TEST_PASSWORD')?:null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]));
$config=[];require APPPATH.'config/migration.php';$shippedVersion=trim(file_get_contents(dirname(APPPATH).'/VERSION'));
native443Check(preg_match('/^(\d)\.(\d)\.(\d)$/D',$shippedVersion,$shippedParts)===1,'canonical native version shape');
$shippedMarker=(int)($shippedParts[1].$shippedParts[2].$shippedParts[3]);
native443Check(($config['migration_version']??null)===$shippedMarker&&$shippedMarker>=443,'shipped configuration exactly matches canonical source and supports historical443');
$config['migration_version']=443; // Explicit historical boundary, using the real production executor.

native443Check($db->query('CREATE TABLE `'.$p.'migrations` (version bigint NOT NULL) ENGINE=InnoDB')!==false,'actual tracking table');$db->insert($p.'migrations',['version'=>442]);
$GLOBALS['native443Hooks']=new class{public array$trace=[];public function do_action($name,...$args){$this->trace[]=$name;}};
$ci=(object)['db'=>$db];$GLOBALS['native443CI']=$ci;$ci->config=new class($config){public function __construct(private array$values){}public function item($name){return$this->values[$name]??null;}};$ci->load=new class{public function config($name){}public function library($name,$options){}};
$reflection=new ReflectionClass(App_Migration::class);$migration=$reflection->newInstanceWithoutConstructor();foreach(['_migration_type'=>'sequential','_migration_path'=>APPPATH.'migrations/','_migration_version'=>443,'_migration_table'=>$p.'migrations','_migration_regex'=>'/^\\d{3}_(\\w+)$/']as$key=>$value)$reflection->getProperty($key)->setValue($migration,$value);$ci->migration=$migration;
$reflection=new ReflectionClass(App::class);$app=$reflection->newInstanceWithoutConstructor();$reflection->getProperty('ci')->setValue($app,$ci);$run=$reflection->getMethod('upgrade_database_silent');
$marker=static fn()=>(int)$app->get_current_db_version();$completed=static fn()=>count(array_filter(hooks()->trace,static fn($name)=>$name==='database_updated'));
IssuerCore::reconcile($db,$p);native443Check(IssuerCore::ready($db,$p)&&$marker()===442,'synthetic442 fixture has already reconciled its native company foundation before capture');
$tables=array_column($db->query('SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND LEFT(TABLE_NAME,?)=?',[strlen($p),$p])->result_array(),'TABLE_NAME');$before=[];foreach($tables as$table)if($table!==$p.'migrations'){$rows=array_map('serialize',$db->query('SELECT * FROM `'.$table.'`')->result_array());sort($rows,SORT_STRING);$before[$table]=$rows;}
$db->failDdl='CREATE TABLE IF NOT EXISTS `'.$p.'company_sales_copy_requests`';$result=$run->invoke($app);
native443Check(!$result['success']&&$marker()===442&&$completed()===0&&$db->ddlFailures===1,'partial443 DDL cannot advance442 or publish completion');
native443Check($db->table_exists($p.'company_sales_revisions')&&!$db->table_exists($p.'company_sales_copy_requests'),'real partial443 creates sales revisions before failed copy request table');
$db->failDdl=null;$db->failMarker=true;$result=$run->invoke($app);native443Check(!$result['success']&&$marker()===442&&$completed()===0,'failed443 tracking leaves previous marker after successful DDL');$db->failMarker=false;
foreach([['company_sales_revisions','reason','varchar(2000)'],['company_sales_copy_requests','source_json','longtext']]as[$table,$column,$type]){
 native443Check($db->query('ALTER TABLE `'.$p.$table.'` MODIFY `'.$column.'` '.$type.' CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL')!==false,'real metadata fault '.$table);
 $result=$run->invoke($app);native443Check(!$result['success']&&$marker()===442&&$completed()===0,'bad443 metadata cannot advance marker '.$table);
 native443Check($db->query('ALTER TABLE `'.$p.$table.'` MODIFY `'.$column.'` '.$type.' CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL')!==false,'metadata restored '.$table);
}
$fk=$db->query('SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=? AND REFERENCED_TABLE_NAME IS NOT NULL',[$p.'company_sales_copy_requests','result_document_id'])->row_array()['CONSTRAINT_NAME'];if(!preg_match('/^[A-Za-z0-9_]+$/D',$fk))throw new RuntimeException('unsafe_native443_tracking_fk');
native443Check($db->query('ALTER TABLE `'.$p.'company_sales_copy_requests` DROP FOREIGN KEY `'.$fk.'`')!==false,'original FK removed for metadata fault');native443Check($db->query('ALTER TABLE `'.$p.'company_sales_copy_requests` ADD CONSTRAINT `'.$fk.'` FOREIGN KEY(result_document_id) REFERENCES `'.$p.'company_sales_documents`(id) ON DELETE CASCADE')!==false,'real destructive FK fault');
$result=$run->invoke($app);native443Check(!$result['success']&&$marker()===442&&$completed()===0,'destructive443 FK cannot advance marker');
native443Check($db->query('ALTER TABLE `'.$p.'company_sales_copy_requests` DROP FOREIGN KEY `'.$fk.'`')!==false,'original FK removed for metadata fault');native443Check($db->query('ALTER TABLE `'.$p.'company_sales_copy_requests` ADD CONSTRAINT `'.$fk.'` FOREIGN KEY(result_document_id) REFERENCES `'.$p.'company_sales_documents`(id)')!==false,'restrictive FK restored');
$extra=$p.'sales_extra_actor';native443Check($db->query('ALTER TABLE `'.$p.'company_sales_revisions` ADD CONSTRAINT `'.$extra.'` FOREIGN KEY(actor_id) REFERENCES `'.$p.'staff`(staffid) ON DELETE CASCADE')!==false,'real extra destructive FK');
$result=$run->invoke($app);native443Check(!$result['success']&&$marker()===442&&$completed()===0,'extra443 FK cannot advance marker');
native443Check($db->query('ALTER TABLE `'.$p.'company_sales_revisions` DROP FOREIGN KEY `'.$extra.'`')!==false,'extra destructive FK removed');
$db->pretendMarker=true;$result=$run->invoke($app);native443Check(!$result['success']&&$marker()===442&&$completed()===0,'pretended443 write rejected by persisted readback');$db->pretendMarker=false;
$result=$run->invoke($app);native443Check($result['success']&&$marker()===443&&$completed()===1,'retry publishes443 only after complete valid schema and persisted marker');
$newDdl=static fn()=>count(array_filter($db->ddl,static fn($sql)=>str_contains($sql,$p.'company_sales_revisions')||str_contains($sql,$p.'company_sales_copy_requests')));$ddl=$newDdl();$result=$run->invoke($app);
native443Check($result['success']&&$marker()===443&&$completed()===2&&$newDdl()===$ddl,'confirmed443 replay never reruns new DDL');
$afterTables=array_column($db->query('SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND LEFT(TABLE_NAME,?)=?',[strlen($p),$p])->result_array(),'TABLE_NAME');$added=array_values(array_diff($afterTables,$tables));sort($added);$wanted=[$p.'company_sales_copy_requests',$p.'company_sales_revisions'];sort($wanted);native443Check($added===$wanted,'actual443 executor adds exactly its two tables');
foreach($wanted as$table)native443Check((int)$db->query('SELECT COUNT(*) n FROM `'.$table.'`')->row_array()['n']===0,'no automatic443 history adoption '.$table);
foreach($before as$table=>$rows){$after=array_map('serialize',$db->query('SELECT * FROM `'.$table.'`')->result_array());sort($after,SORT_STRING);native443Check($rows===$after,'executor443 preserves complete historical rows '.$table);}
echo 'Native443 actual executor confirmed: '.$GLOBALS['native443Checks'].' checks; partial DDL, metadata/FK, failed and false marker readback, retry/replay and historical bytes.'.PHP_EOL;
