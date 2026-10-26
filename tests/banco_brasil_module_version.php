<?php
define('BASEPATH',__DIR__);define('APP_MODULES_PATH',dirname(__DIR__).'/modules/');
function db_prefix(){return 'test_';}function &get_instance(){return $GLOBALS['CI'];}
require dirname(__DIR__).'/application/libraries/App_module_migration.php';
require dirname(__DIR__).'/modules/banco_brasil/libraries/BancoBrasil/SchemaValidator.php';
class BbMigrationDb {
 public $version='1.0.1';public $badSchema='';public $failTracking=false;public $failDdl=false;public $statements=[];
 function where($k,$v){return $this;}function select($v){return $this;}
 function get($t){return new class($this->version){function __construct(private $v){}function row(){return(object)['installed_version'=>$this->v];}};}
 function update($t,$r){if($this->failTracking&&$r['installed_version']==='1.0.1')return false;$this->version=$r['installed_version'];return true;}
 function query($sql,$bind=[]){
  if(str_starts_with($sql,'CREATE')){$this->statements[]=$sql;return !$this->failDdl;}
  $table=substr($bind[0],5);$spec=\BancoBrasil\SchemaValidator::requirements()[$table];$rows=[];
  if(str_contains($sql,'information_schema.TABLES'))$rows=[['ENGINE'=>$this->badSchema==='engine'?'MyISAM':'InnoDB','TABLE_COLLATION'=>'utf8mb4_bin']];
  elseif(str_contains($sql,'information_schema.COLUMNS')){
   foreach($spec['columns'] as $name=>$type){$type=rtrim($type,'?');$text=preg_match('/^(?:var)?char\(|^text$/',$type);$rows[]=['COLUMN_NAME'=>$name,'COLUMN_TYPE'=>$type,'IS_NULLABLE'=>str_ends_with($spec['columns'][$name],'?')?'YES':'NO','EXTRA'=>$name==='id'?'auto_increment':'','COLUMN_DEFAULT'=>null,'CHARACTER_SET_NAME'=>$text?'utf8mb4':null,'COLLATION_NAME'=>$text?'utf8mb4_bin':null];}
   if($this->badSchema==='missing_column')array_pop($rows);
   if($this->badSchema==='type')$rows[0]['COLUMN_TYPE']='int unsigned';
   if($this->badSchema==='null')$rows[0]['IS_NULLABLE']='YES';
   if($this->badSchema==='auto_increment')$rows[0]['EXTRA']='';
   if($this->badSchema==='collation')foreach($rows as &$r)if($r['COLLATION_NAME'])$r['COLLATION_NAME']='utf8mb4_general_ci';
  }else{
   foreach(array_merge([['id']],$spec['unique']) as $key=>$cols)foreach($cols as $i=>$col)$rows[]=['INDEX_NAME'=>$key===0?'PRIMARY':'unique_'.$key,'NON_UNIQUE'=>0,'SEQ_IN_INDEX'=>$i+1,'COLUMN_NAME'=>$col,'SUB_PART'=>null];
   if($this->badSchema==='pk')$rows[0]['COLUMN_NAME']='issuer_id';
   if($table==='bb_return_events'){
    if($this->badSchema==='missing_unique')$rows=array_slice($rows,0,1);
    if($this->badSchema==='prefix_unique')$rows[2]['SUB_PART']=8;
    if($this->badSchema==='non_unique')$rows[2]['NON_UNIQUE']=1;
    if($this->badSchema==='partial_unique')array_pop($rows);
   }
  }
  return new class($rows){function __construct(private $r){}function row_array(){return $this->r[0]??[];}function result_array(){return $this->r;}};
 }
}
$GLOBALS['CI']=(object)['db'=>new BbMigrationDb(),'load'=>new class{function dbforge(){}},'lang'=>new class{function load($v){}function line($v){return $v;}},'app_modules'=>new class{function get($v){return ['headers'=>['version'=>'1.0.1']];}}];
$n=0;function check($v,$m){global $n;$n++;if(!$v)throw new RuntimeException($m);}
require APP_MODULES_PATH.'banco_brasil/install.php';check($CI->db->version==='1.0.1','fresh activation tracked');check(count($CI->db->statements)===7,'all tables migrated natively');
check((new App_module_migration('banco_brasil'))->to_latest()===true,'repeat current no-op');
$CI->db->version='1.0.0';check((string)(new App_module_migration('banco_brasil'))->to_latest()==='101','native upgrade');check($CI->db->version==='1.0.1','upgrade tracked');
$CI->db->failDdl=true;try{require APP_MODULES_PATH.'banco_brasil/install.php';throw new LogicException('must fail');}catch(RuntimeException $e){check($CI->db->version==='1.0.0','DDL failure leaves baseline');}
$CI->db->failDdl=false;require APP_MODULES_PATH.'banco_brasil/install.php';check($CI->db->version==='1.0.1','safe retry');
$CI->db->failTracking=true;$trackingFailure=null;try{require APP_MODULES_PATH.'banco_brasil/install.php';throw new LogicException('must fail');}catch(RuntimeException $e){$trackingFailure=$e->getMessage();}
check(in_array($trackingFailure,['bb_migration_failed','bb_migration_version_unconfirmed'],true),'tracking failure surfaced by either native runner');check($CI->db->version==='1.0.0','failed tracking preserves baseline');
$CI->db->failTracking=false;$CI->db->version='1.0.2';try{require APP_MODULES_PATH.'banco_brasil/install.php';throw new LogicException('must fail');}catch(RuntimeException $e){check($CI->db->version==='1.0.2','no downgrade');}
$ddl=implode("\n",\BancoBrasil\ModuleSchema::statements('test_'));
check(substr_count($ddl,'ENGINE=InnoDB')===7,'all transactional tables');
check(!preg_match('/DROP|TRUNCATE|DELETE FROM/i',$ddl),'non destructive schema');
check(str_contains(file_get_contents(APP_MODULES_PATH.'banco_brasil/banco_brasil.php'),'Version: 1.2.8'),'header advances to new native migration without rewriting earlier history');
$source114=file_get_contents(APP_MODULES_PATH.'banco_brasil/migrations/114_version_114.php');
check(str_contains($source114,'class Migration_Version_114 extends App_module_migration')&&str_contains($source114,'public function up()'),'114 is a new native NOOP preserving prior history');
$source104=file_get_contents(APP_MODULES_PATH.'banco_brasil/migrations/104_version_104.php');
check(str_contains($source104,'class Migration_Version_104 extends App_module_migration')&&str_contains($source104,'public function up()'),'104 is a native NOOP migration');
$source103=file_get_contents(APP_MODULES_PATH.'banco_brasil/migrations/103_version_103.php');
check(str_contains($source103,'class Migration_Version_103 extends App_module_migration')&&str_contains($source103,'public function up()'),'103 is a native NOOP migration');
foreach(['engine','missing_column','type','null','auto_increment','collation','pk','missing_unique','prefix_unique','non_unique','partial_unique'] as $bad){
 $CI->db->version='1.0.0';$CI->db->badSchema=$bad;
 try{(new App_module_migration('banco_brasil'))->to_latest();throw new LogicException('bad schema accepted');}
 catch(RuntimeException $e){check($e->getMessage()==='bb_schema_incompatible','incompatible schema rejected '.$bad);check($CI->db->version==='1.0.0','incompatible schema does not advance tracking '.$bad);}
}
$CI->db->badSchema='';check((string)(new App_module_migration('banco_brasil'))->to_latest()==='101','compatible schema can retry');
check(!preg_match('/ALTER|DROP|TRUNCATE|DELETE FROM|UPDATE /i',implode('\n',$CI->db->statements)),'validator does not modify existing data or constraints');


$source115=file_get_contents(APP_MODULES_PATH.'banco_brasil/migrations/115_version_115.php');
check(str_contains($source115,'class Migration_Version_115 extends App_module_migration')&&str_contains($source115,'public function up()')&&str_contains($source115,'NOOP'),'115 is a new native profile NOOP preserving earlier migration history');
check(str_contains(file_get_contents(APP_MODULES_PATH.'banco_brasil/banco_brasil.php'),'Requires at least: 4.7.3'),'new profile runtime requires its composed native core version');
require_once APP_MODULES_PATH.'banco_brasil/libraries/BancoBrasil/ModuleRelease.php';
check(\BancoBrasil\ModuleRelease::VERSION==='1.2.8'&&\BancoBrasil\ModuleRelease::MIGRATION===128&&\BancoBrasil\ModuleRelease::MINIMUM_CORE==='4.7.3','strict runtime release agrees with module header and latest native migration');
$source116=file_get_contents(APP_MODULES_PATH.'banco_brasil/migrations/116_version_116.php');
check(str_contains($source116,'class Migration_Version_116 extends App_module_migration')&&str_contains($source116,'ApiCnabContractSchema::ensure'),'116 creates an additive native contract schema without rewriting historical declarations');
foreach([117,118,119] as $step439){
    $source439=file_get_contents(APP_MODULES_PATH.'banco_brasil/migrations/'.$step439.'_version_'.$step439.'.php');
    check(str_contains($source439,'class Migration_Version_'.$step439.' extends App_module_migration')&&str_contains($source439,'public function up()')&&str_contains($source439,'OriginationSchema::ensure')&&str_contains($source439,'ApiCnabContractSchema::ensure'),'new native'.$step439.' preserves and validates original schema participants');
}
$source120=file_get_contents(APP_MODULES_PATH.'banco_brasil/migrations/120_version_120.php');
check(str_contains($source120,'class Migration_Version_120 extends App_module_migration')&&str_contains($source120,'public function up()')&&str_contains($source120,'ApiSchema::ensure')&&str_contains($source120,'ApiCnabContractSchema::ensure'),'120 validates current native schema participants without rewriting117-119');
$source121=file_get_contents(APP_MODULES_PATH.'banco_brasil/migrations/121_version_121.php');
check(str_contains($source121,'class Migration_Version_121 extends App_module_migration')&&str_contains($source121,'public function up()')&&str_contains($source121,'OriginationSchema::ensure')&&str_contains($source121,'ReconciliationSchema::ensure')&&str_contains($source121,'ApiSchema::ensure')&&str_contains($source121,'ApiCnabContractSchema::ensure'),'121 validates existing participants without rewriting117-120');
$source122=file_get_contents(APP_MODULES_PATH.'banco_brasil/migrations/122_version_122.php');
check(str_contains($source122,'class Migration_Version_122 extends App_module_migration')&&str_contains($source122,'OriginationSchema::ensure')&&str_contains($source122,'ReconciliationSchema::ensure')&&str_contains($source122,'ApiSchema::ensure')&&str_contains($source122,'ApiCnabContractSchema::ensure'),'122 validates existing participants without rewriting117-121');
$source123=file_get_contents(APP_MODULES_PATH.'banco_brasil/migrations/123_version_123.php');
check(str_contains($source123,'class Migration_Version_123 extends App_module_migration')&&str_contains($source123,'NOOP')&&str_contains($source123,'OriginationSchema::ensure')&&str_contains($source123,'ReconciliationSchema::ensure')&&str_contains($source123,'ApiSchema::ensure')&&str_contains($source123,'ApiCnabContractSchema::ensure'),'123 validates existing participants; tariff/LOCAL adapters do not rewrite117-122');
$source124=file_get_contents(APP_MODULES_PATH.'banco_brasil/migrations/124_version_124.php');
check(str_contains($source124,'class Migration_Version_124 extends App_module_migration')&&str_contains($source124,'CheckoutSchema::ensure'),'historical124 checkout remains a distinct native step');
$source125=file_get_contents(APP_MODULES_PATH.'banco_brasil/migrations/125_version_125.php');
check(str_contains($source125,'class Migration_Version_125 extends App_module_migration')&&str_contains($source125,'ApiOriginPolicy::ensure'),'historical125 adds persistent bridge policy without rewriting124');
$source126=file_get_contents(APP_MODULES_PATH.'banco_brasil/migrations/126_version_126.php');
check(str_contains($source126,'class Migration_Version_126 extends App_module_migration')&&str_contains($source126,'NOOP'),'126 adds cross-provider fence capability without modifying historical125');
echo "OK {$n} Banco do Brasil native migration checks\n";

$source127=file_get_contents(APP_MODULES_PATH.'banco_brasil/migrations/127_version_127.php');
check(str_contains($source127,'class Migration_Version_127 extends App_module_migration')&&str_contains($source127,'NOOP'),'127 is readonly-catalog release, preserving126 schema/data');

$source128=file_get_contents(APP_MODULES_PATH.'banco_brasil/migrations/128_version_128.php');
check(str_contains($source128,'class Migration_Version_128 extends App_module_migration')&&str_contains($source128,'NOOP'),'128 preserves configuration and origins; activation is never inferred');
