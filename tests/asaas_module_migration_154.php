<?php
declare(strict_types=1);
// Actual native runner/154 migration, disposable SQLite or explicitly selected CI MySQL.
define('BASEPATH', __DIR__);
define('APPPATH', dirname(__DIR__) . '/application/');
define('APP_MODULES_PATH', dirname(__DIR__) . '/modules/');
function db_prefix(){return $GLOBALS['migration154Prefix'];}
function &get_instance(){return $GLOBALS['migration154Ci'];}
function get_option($name){$ci=&get_instance();return $ci->db->get_where(db_prefix().'options',['name'=>$name])->row_array()['value']??'';}
function show_error($message){throw new RuntimeException((string)$message);}
require APPPATH . 'libraries/App_module_migration.php';

require dirname(__DIR__) . '/system/database/DB_result.php';
class Migration154Result extends CI_DB_result
{
    public function __construct(PDOStatement $statement){$this->result_id=$statement;$this->result_array=$statement->columnCount()?$statement->fetchAll(PDO::FETCH_ASSOC):[];$this->num_rows=count($this->result_array);}
}
class Migration154Db
{
    public bool $db_debug=true, $save_queries=true;
    public array $data_cache=[];
    public ?string $failQuery=null;
    public ?int $failInsertAfter=null;
    public bool $failVersionWrite=false, $ignoreVersionWrite=false;
    private array $where=[];
    public function __construct(public PDO $pdo){}
    public function query($sql,$params=[]){if($this->failQuery!==null&&str_contains($sql,$this->failQuery))return false;$s=$this->pdo->prepare($sql);$s->execute($params);return new Migration154Result($s);}
    public function table_exists($table){return $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='sqlite'?(bool)$this->query("SELECT name FROM sqlite_master WHERE type='table' AND name=?",[$table])->row_array():(bool)$this->query('SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?',[$table])->row_array();}
    public function field_exists($field,$table){return $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='sqlite'?in_array($field,array_column($this->query('PRAGMA table_info(`'.$table.'`)')->result_array(),'name'),true):(bool)$this->query('SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=?',[$table,$field])->row_array();}
    public function where($key,$value){$this->where[$key]=$value;return $this;}
    public function select($columns){return $this;}
    public function get($table){$where=$this->where;$this->where=[];return $this->query('SELECT * FROM `'.$table.'`'.($where?' WHERE '.implode(' AND ',array_map(fn($key)=>'`'.$key.'`=?',array_keys($where))):''),array_values($where));}
    public function get_where($table,$where){foreach($where as $key=>$value)$this->where($key,$value);return $this->get($table);}
    public function insert($table,$row){if($this->failInsertAfter!==null&&$this->failInsertAfter--===0)return false;return $this->query('INSERT INTO `'.$table.'` (`'.implode('`,`',array_keys($row)).'`) VALUES ('.implode(',',array_fill(0,count($row),'?')).')',array_values($row))!==false;}
    public function update($table,$row){$where=$this->where;$this->where=[];if($table===db_prefix().'modules'&&isset($row['installed_version'])){if($this->failVersionWrite)return false;if($this->ignoreVersionWrite)return true;}return $this->query('UPDATE `'.$table.'` SET '.implode(',',array_map(fn($key)=>'`'.$key.'`=?',array_keys($row))).' WHERE '.implode(' AND ',array_map(fn($key)=>'`'.$key.'`=?',array_keys($where))),array_merge(array_values($row),array_values($where)))!==false;}
}
$pdo=new PDO(getenv('ASAAS_TEST_MYSQL_DSN')?:'sqlite::memory:',getenv('ASAAS_TEST_MYSQL_USER')?:null,getenv('ASAAS_TEST_MYSQL_PASSWORD')?:null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$mysql=$pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql';
if($mysql&&!in_array((string)$pdo->query('SELECT DATABASE()')->fetchColumn(),['asaas_test','callback_test','crm_tests'],true))throw new RuntimeException('Disposable CI database required');
$GLOBALS['migration154Prefix']='migration154_'.bin2hex(random_bytes(6)).'_';$p=db_prefix();$db=new Migration154Db($pdo);
$header=file_get_contents(APP_MODULES_PATH.'asaas/asaas.php');if(!preg_match('/^Version:\s*(\S+)/m',$header,$match)||$match[1]!=='1.5.4')throw new RuntimeException('Actual154 module header required');
$GLOBALS['migration154Ci']=(object)['db'=>$db,'load'=>new class{function dbforge(){}},'lang'=>new class{function load($value){}function line($value){return $value.' %s';}},'app_modules'=>new class($match[1]){function __construct(private string $version){}function get($name){return ['headers'=>['version'=>$this->version]];}}];
$checks=0;
function migration154Check($condition,$message){global $checks;if(!$condition)throw new RuntimeException($message);$checks++;}
function migration154Throws(callable $operation,string $expected){try{$operation();}catch(Throwable $error){migration154Check($error->getMessage()===$expected,'expected sanitized native failure: '.$expected);return;}throw new RuntimeException('Native migration failure expected');}
require APPPATH.'libraries/App_modules.php';
preg_match('/^Requires at least:\s*(\S+)/m',$header,$requirement);
$minimum=(new ReflectionClass(App_modules::class))->newInstanceWithoutConstructor();
$config154=new class{public string $version='457';public function load($name){}public function item($name){return $this->version;}};
$registry154=new ReflectionClass($minimum);$registry154->getProperty('ci')->setValue($minimum,(object)['config'=>$config154]);
$registry154->getProperty('modules')->setValue($minimum,['asaas'=>['headers'=>['requires_at_least'=>$requirement[1]??'']]]);
migration154Check(($requirement[1]??null)==='4.5.8'&&!$minimum->is_minimum_version_requirement_met('asaas'),'native module compatibility rejects core457 without tab-parent integration');
$config154->version='458';migration154Check($minimum->is_minimum_version_requirement_met('asaas'),'native module compatibility accepts core458');
$tables=['modules','options','asaas_webhook_integrations','asaas_company_accounts'];
$version=fn()=>$db->get_where($p.'modules',['module_name'=>'asaas'])->row_array()['installed_version'];
$option=fn($name)=>$db->get_where($p.'options',['name'=>$name])->row_array()['value']??null;
try{
    $engine=$mysql?' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4':'';
    $db->query('CREATE TABLE `'.$p.'modules` (module_name VARCHAR(50) PRIMARY KEY,installed_version VARCHAR(20))'.$engine);
    $db->query('CREATE TABLE `'.$p.'options` (name VARCHAR(191) PRIMARY KEY,value TEXT NOT NULL,autoload INT NOT NULL DEFAULT 1)'.$engine);
    $db->insert($p.'modules',['module_name'=>'asaas','installed_version'=>'1.5.3']);
    foreach(['asaas_webhook_integrations','asaas_company_accounts'] as $table){$db->query('CREATE TABLE `'.$p.$table.'` (id INT PRIMARY KEY,token_hash CHAR(64) NULL,account_id VARCHAR(128),environment VARCHAR(16))'.$engine);$db->insert($p.$table,['id'=>1,'token_hash'=>str_repeat('a',64),'account_id'=>'synthetic-wallet','environment'=>'sandbox']);}
    foreach(['paymentmethod_asaas_active'=>'1','paymentmethod_asaas_description'=>'','paymentmethod_asaas_interest_value'=>'2.37','paymentmethod_asaas_api_key'=>'synthetic-encrypted-unchanged','paymentmethod_asaas_profile_production_label'=>'Custom production'] as $name=>$value)$db->insert($p.'options',['name'=>$name,'value'=>$value]);
    migration154Check($db->get_where($p.'options',['name'=>'not-present'])->row_array()===null,'actual CI_DB_result returns NULL for absent native option');
    $originalOptions=$db->query('SELECT * FROM `'.$p.'options` ORDER BY name')->result_array();
    $before=[];foreach(['asaas_webhook_integrations','asaas_company_accounts'] as $table)$before[$table]=$db->get($p.$table)->result_array();
    migration154Check((new App_module_migration('asaas'))->version('153')===true,'historical153 remains an explicit native checkpoint');
    migration154Check(!$db->field_exists('token_cipher',$p.'asaas_webhook_integrations'),'historical153 checkpoint does not execute154');

    $db->failQuery='ALTER TABLE `'.$p.'asaas_company_accounts` ADD `token_cipher`';
    migration154Throws(fn()=>(new App_module_migration('asaas'))->to_latest(),'issuer_storage_failed');
    migration154Check($version()==='1.5.3','partial token DDL never advances native153 tracking');
    migration154Check($db->field_exists('token_cipher',$p.'asaas_webhook_integrations')&&!$db->field_exists('token_cipher',$p.'asaas_company_accounts'),'late DDL failure leaves only first additive column');
    migration154Check($db->query('SELECT * FROM `'.$p.'options` ORDER BY name')->result_array()===$originalOptions,'token schemas complete before any profile seed');
    $db->failQuery=null;$db->failInsertAfter=7;
    migration154Throws(fn()=>(new App_module_migration('asaas'))->to_latest(),'asaas_profile_initialization_unconfirmed');
    migration154Check($version()==='1.5.3'&&$option('paymentmethod_asaas_profile_schema')===null,'partial seed never writes completion or native version');
    migration154Check($db->field_exists('token_cipher',$p.'asaas_company_accounts'),'retry completes remaining token column before profile writes');
    $db->failInsertAfter=null;$db->failVersionWrite=true;
    $runner=new App_module_migration('asaas');migration154Check($runner->to_latest()===false&&$runner->error_string()==='module_migration_version_write_failed','native154 reports failed tracking write after complete schema/seed');
    migration154Check($version()==='1.5.3'&&$option('paymentmethod_asaas_profile_schema')==='1','structural readiness is distinct from confirmed native154 tracking');
    migration154Check(!\Asaas\EnvironmentSettings::ready(),'complete schema without native154 keeps operational profiles disabled');
    migration154Throws(fn()=>\Asaas\ModuleReadiness::require154($db,$p),'asaas_module_154_required');
    $db->failVersionWrite=false;$db->ignoreVersionWrite=true;
    $runner=new App_module_migration('asaas');migration154Check($runner->to_latest()===false&&$runner->error_string()==='module_migration_version_unconfirmed','successful update return without durable readback cannot report completion');
    migration154Check($version()==='1.5.3','unconfirmed readback keeps actual153 tracking');
    $db->ignoreVersionWrite=false;
    migration154Check((string)(new App_module_migration('asaas'))->to_latest()==='154'&&$version()==='1.5.4','retry confirms154 through native durable readback');
    \Asaas\WebhookTokenSchema::ensure($db,$p,false);
    migration154Check(\Asaas\EnvironmentSettings::ready(),'confirmed native154 enables initialized profiles');
    foreach(['production','sandbox'] as $environment){migration154Check($option('paymentmethod_asaas_profile_'.$environment.'_description')==='','empty legacy value preserved: '.$environment);migration154Check($option('paymentmethod_asaas_profile_'.$environment.'_active')==='1'&&$option('paymentmethod_asaas_profile_'.$environment.'_interest_value')==='2.37','legacy activation and decimal preserved: '.$environment);}
    migration154Check($option('paymentmethod_asaas_profile_production_label')==='Custom production','pre-existing profile is never overwritten');
    migration154Check($option('paymentmethod_asaas_api_key')==='synthetic-encrypted-unchanged','API credential storage untouched');
    foreach($before as $table=>$rows){$after=$db->get($p.$table)->result_array();migration154Check($after[0]['token_cipher']===null,'legacy hash-only receiver remains unrecoverable without explicit regeneration');foreach($after as &$row)unset($row['token_cipher']);unset($row);migration154Check($after===$rows,'154 never rotates tokens or rewrites receiver scope');}
    $saved=[];foreach($tables as $table)$saved[$table]=$db->get($p.$table)->result_array();
    migration154Check((new App_module_migration('asaas'))->to_latest()===true,'native154 replay is no-op');
    foreach($saved as $table=>$rows)migration154Check($db->get($p.$table)->result_array()===$rows,'native replay preserves '.$table.' bytes');
    $db->where('name','paymentmethod_asaas_profile_production_description')->update($p.'options',['value'=>'Edited production']);
    $db->where('id',1)->update($p.'asaas_company_accounts',['token_cipher'=>'synthetic-existing-cipher']);
    $updated=[];foreach(['options','asaas_webhook_integrations','asaas_company_accounts'] as $table)$updated[$table]=$db->get($p.$table)->result_array();
    migration154Check((string)(new App_module_migration('asaas'))->version('153')==='153','native down preserves data while recording explicit rollback checkpoint');
    migration154Check((string)(new App_module_migration('asaas'))->to_latest()==='154','native replay after rollback revalidates existing additive schema');
    foreach($updated as $table=>$rows)migration154Check($db->get($p.$table)->result_array()===$rows,'down/up preserves customized profiles and ciphertext in '.$table);
    foreach(['1.5.3','1.5.40','1.5.4-preview','',null] as $invalidVersion){$db->where('module_name','asaas')->update($p.'modules',['installed_version'=>$invalidVersion]);migration154Check(!\Asaas\ModuleReadiness::ready154($db,$p),'invalid or old native module marker never enables154');}
    $db->where('module_name','asaas')->update($p.'modules',['installed_version'=>'1.5.4']);
    migration154Check($db->db_debug&&$db->save_queries,'schema query boundary restores driver flags');
    echo 'Asaas native154 migration: '.$checks.' checks passed ('.$pdo->getAttribute(PDO::ATTR_DRIVER_NAME).'). Failure/retry/readback/replay; no provider calls.'.PHP_EOL;
}finally{foreach(array_reverse($tables) as $table)$db->query('DROP TABLE IF EXISTS `'.$p.$table.'`');}
