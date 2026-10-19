<?php
declare(strict_types=1);
// Native framework runner + real disposable MySQL. No provider or user data.
if (!getenv('ASAAS_TEST_MYSQL_DSN') && !getenv('MYSQL_DSN')) {fwrite(STDERR,"ASAAS_TEST_MYSQL_DSN required.\n");exit(2);}
define('BASEPATH',__DIR__);define('APPPATH',dirname(__DIR__).'/application/');define('APP_MODULES_PATH',dirname(__DIR__).'/modules/');
function db_prefix(){return 'callback_module_test_';}
function &get_instance(){return $GLOBALS['migrationCi'];}
function show_error($message){throw new RuntimeException((string)$message);}
require __DIR__.'/../application/libraries/App_module_migration.php';
class ModuleMigrationResult {
    public function __construct(private PDOStatement $s){}
    public function row(){return $this->s->fetch(PDO::FETCH_OBJ)?:null;}
    public function row_array(){return $this->s->fetch(PDO::FETCH_ASSOC)?:[];}
    public function result_array(){return $this->s->fetchAll(PDO::FETCH_ASSOC);}
}
class ModuleMigrationDb {
    private array $where=[];
    public ?string $failQuery=null;
    public function __construct(public PDO $pdo){}
    public function table_exists($t){$s=$this->pdo->prepare('SHOW TABLES LIKE ?');$s->execute([$t]);return (bool)$s->fetch();}
    public function field_exists($f,$t){$s=$this->pdo->prepare('SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=?');$s->execute([$t,$f]);return (bool)$s->fetch();}
    public function query($q,$args=[]){if($this->failQuery!==null&&str_contains($q,$this->failQuery))return false;$s=$this->pdo->prepare($q);$s->execute($args);return new ModuleMigrationResult($s);}
    public function where($k,$v){$this->where[$k]=$v;return $this;}
    public function select($s){return $this;}
    public function get($t){$q='SELECT * FROM `'.$t.'` WHERE '.implode(' AND ',array_map(fn($k)=>'`'.$k.'`=?',array_keys($this->where)));$args=array_values($this->where);$this->where=[];return $this->query($q,$args);}
    public function update($t,$row){$q='UPDATE `'.$t.'` SET '.implode(',',array_map(fn($k)=>'`'.$k.'`=?',array_keys($row))).' WHERE '.implode(' AND ',array_map(fn($k)=>'`'.$k.'`=?',array_keys($this->where)));$args=array_merge(array_values($row),array_values($this->where));$this->where=[];return (bool)$this->query($q,$args);}
}
$pdo=new PDO(getenv('ASAAS_TEST_MYSQL_DSN')?:getenv('MYSQL_DSN'),getenv('ASAAS_TEST_MYSQL_USER')?:getenv('MYSQL_USER')?:'root',getenv('ASAAS_TEST_MYSQL_PASSWORD')?:getenv('MYSQL_PASSWORD')?:'',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
function cleanupAsaasNativeFixture(PDO $pdo):void
{
    // These are the three disposable databases configured by CI/local verification.
    if(!in_array((string)$pdo->query('SELECT DATABASE()')->fetchColumn(),['asaas_test','callback_test','crm_tests'],true)
        ||!in_array(db_prefix(),['module_test_','callback_module_test_'],true))throw new RuntimeException('Disposable native fixture database required');
    $tables=['asaas_company_fee_expenses','asaas_company_jobs','asaas_company_receipts','asaas_company_events','asaas_company_proofs','asaas_company_origin_meta','asaas_company_accounts',
        'asaas_settlement_outbox','asaas_payment_origin_events','asaas_payment_origins','asaas_webhook_admin_audit','asaas_webhook_integrations','asaas_webhook_events','asaas_payments_map','asaas_customers_map','asaas_logs',
        'company_expenses','invoicepaymentrecords','issuers_registry','clients','modules'];
    foreach($tables as $table)$pdo->exec('DROP TABLE IF EXISTS `'.db_prefix().$table.'`');
}
cleanupAsaasNativeFixture($pdo);
register_shutdown_function(static function()use($pdo){cleanupAsaasNativeFixture($pdo);});
$db=new ModuleMigrationDb($pdo);
$GLOBALS['migrationCi']=(object)['db'=>$db,'load'=>new class{function dbforge(){}},'lang'=>new class{function load($x){}function line($x){return $x.' %s';}},'app_modules'=>new class{function get($name){return ['headers'=>['version'=>'1.4.9']];}}];
$CI=&get_instance();
$pdo->exec('CREATE TABLE callback_module_test_modules(module_name VARCHAR(50) PRIMARY KEY,installed_version VARCHAR(20)) ENGINE=InnoDB');
$pdo->exec('CREATE TABLE callback_module_test_clients(userid INT PRIMARY KEY) ENGINE=InnoDB');
$pdo->exec("INSERT INTO callback_module_test_modules VALUES('asaas','1.4.9')");
$pdo->exec('CREATE TABLE callback_module_test_issuers_registry(id INT UNSIGNED PRIMARY KEY) ENGINE=InnoDB');
$pdo->exec('CREATE TABLE callback_module_test_invoicepaymentrecords(id INT PRIMARY KEY) ENGINE=InnoDB');
$pdo->exec('CREATE TABLE callback_module_test_company_expenses(id INT UNSIGNED PRIMARY KEY) ENGINE=InnoDB');
$n=0;function verify($v,$m){global $n;if(!$v)throw new RuntimeException($m);$n++;}
require __DIR__.'/../modules/asaas/install.php';
verify($pdo->query("SELECT installed_version FROM callback_module_test_modules WHERE module_name='asaas'")->fetchColumn()==='1.4.9','fresh install native tracker reaches149');
foreach(['asaas_payment_origins','asaas_payment_origin_events'] as $table)verify($db->table_exists(db_prefix().$table),'native migration created '.$table);
verify($db->field_exists('integration_id',db_prefix().'asaas_webhook_events'),'148 security schema follows147 noop');
$pdo->exec("INSERT INTO callback_module_test_asaas_webhook_events(event_id,payload,process_status) VALUES('legacy_fixture','{}','received')");
require __DIR__.'/../modules/asaas/install.php';
verify((int)$pdo->query('SELECT COUNT(*) FROM callback_module_test_asaas_webhook_events')->fetchColumn()===1,'reactivation preserves history without duplicate records');
$pdo->exec("UPDATE callback_module_test_modules SET installed_version='1.4.6' WHERE module_name='asaas'");

$pdo->exec('DROP TABLE callback_module_test_asaas_settlement_outbox');
$runner=new App_module_migration('asaas');
verify((string)$runner->version('147')==='147','route correction has native numbered noop');
verify(!$db->table_exists('callback_module_test_asaas_settlement_outbox'),'147 noop does not execute security schema early');
verify($pdo->query('SELECT installed_version FROM callback_module_test_modules')->fetchColumn()==='1.4.7','native noop version recorded');
verify((string)$runner->to_latest()==='149','latest149 follows historical147/148');
verify($db->table_exists('callback_module_test_asaas_settlement_outbox'),'148 creates settlement tables after147 noop');
verify($pdo->query('SELECT payload FROM callback_module_test_asaas_webhook_events')->fetchColumn()==='{}','upgrade preserves legacy payload');
$pdo->exec("UPDATE callback_module_test_modules SET installed_version='1.4.5' WHERE module_name='asaas'");
foreach(array_reverse(\Asaas\CompanySchema::tables()) as $suffix)$pdo->exec('DROP TABLE callback_module_test_'.$suffix);
$pdo->exec('DROP TABLE callback_module_test_asaas_payment_origin_events');
$pdo->exec('DROP TABLE callback_module_test_asaas_payment_origins');
$db->failQuery='CREATE TABLE `callback_module_test_asaas_payment_origins`';
try{(new App_module_migration('asaas'))->to_latest();verify(false,'schema failure incorrectly accepted');}catch(RuntimeException $e){verify($e->getMessage()==='payment_origin_schema_failed','migration failure propagates');}
verify($pdo->query('SELECT installed_version FROM callback_module_test_modules')->fetchColumn()==='1.4.5','failed 146 not recorded complete');
$db->failQuery=null;verify((string)(new App_module_migration('asaas'))->to_latest()==='149','retry resumes native sequence146 through149');
verify($pdo->query('SELECT installed_version FROM callback_module_test_modules')->fetchColumn()==='1.4.9','successful retry tracked149');
verify((new App_module_migration('asaas'))->to_latest()===true,'repeated native upgrade is a no-op');
foreach(['accounts','origin_meta','proofs','events','receipts','jobs','fee_expenses'] as $suffix)verify($db->table_exists('callback_module_test_asaas_company_'.$suffix),'149 creates native adapter ledger '.$suffix);
$pdo->exec("UPDATE callback_module_test_modules SET installed_version='1.4.8' WHERE module_name='asaas'");
$db->failQuery='CREATE TABLE IF NOT EXISTS `callback_module_test_asaas_company_accounts`';
try{(new App_module_migration('asaas'))->to_latest();verify(false,'149 DDL failure incorrectly accepted');}catch(RuntimeException $error){verify($error->getMessage()==='issuer_storage_failed','149 storage failure is sanitized');}
verify($pdo->query('SELECT installed_version FROM callback_module_test_modules')->fetchColumn()==='1.4.8','failed149 preserves confirmed148 tracking');
$db->failQuery=null;verify((string)(new App_module_migration('asaas'))->to_latest()==='149','retry149 verifies partially existing ledgers');
verify($pdo->query('SELECT installed_version FROM callback_module_test_modules')->fetchColumn()==='1.4.9','successful149 readback confirmed');
verify((new App_module_migration('asaas'))->to_latest()===true,'149 replay is native no-op');
$pdo->exec("ALTER TABLE callback_module_test_asaas_company_receipts ALTER COLUMN proof_id SET DEFAULT 0");
try{\Asaas\CompanySchema::ensure($db,db_prefix(),false);verify(false,'149 default0 silently accepted');}catch(RuntimeException $error){verify($error->getMessage()==='asaas_company_schema_invalid','149 rejects incompatible default metadata');}
$pdo->exec("ALTER TABLE callback_module_test_asaas_company_receipts ALTER COLUMN proof_id DROP DEFAULT");\Asaas\CompanySchema::ensure($db,db_prefix(),false);
echo "OK: {$n} callback native module migration MySQL assertions.\n";
