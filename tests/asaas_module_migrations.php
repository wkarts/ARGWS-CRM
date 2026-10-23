<?php
declare(strict_types=1);
// Native framework runner + real disposable MySQL. No provider or user data.
if (!getenv('ASAAS_TEST_MYSQL_DSN') && !getenv('MYSQL_DSN')) {fwrite(STDERR,"ASAAS_TEST_MYSQL_DSN required.\n");exit(2);}
define('BASEPATH',__DIR__);define('APPPATH',dirname(__DIR__).'/application/');define('APP_MODULES_PATH',dirname(__DIR__).'/modules/');
function db_prefix(){return 'module_test_';}
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
    public function get_where($t,$where){foreach($where as $key=>$value)$this->where($key,$value);return $this->get($t);}
    public function insert($t,$row){return $this->query('INSERT INTO `'.$t.'` (`'.implode('`,`',array_keys($row)).'`) VALUES ('.implode(',',array_fill(0,count($row),'?')).')',array_values($row))!==false;}
    public function update($t,$row){$q='UPDATE `'.$t.'` SET '.implode(',',array_map(fn($k)=>'`'.$k.'`=?',array_keys($row))).' WHERE '.implode(' AND ',array_map(fn($k)=>'`'.$k.'`=?',array_keys($this->where)));$args=array_merge(array_values($row),array_values($this->where));$this->where=[];return (bool)$this->query($q,$args);}
}
$pdo=new PDO(getenv('ASAAS_TEST_MYSQL_DSN')?:getenv('MYSQL_DSN'),getenv('ASAAS_TEST_MYSQL_USER')?:getenv('MYSQL_USER')?:'root',getenv('ASAAS_TEST_MYSQL_PASSWORD')?:getenv('MYSQL_PASSWORD')?:'',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
function cleanupAsaasNativeFixture(PDO $pdo):void
{
    // These are the three disposable databases configured by CI/local verification.
    if(!in_array((string)$pdo->query('SELECT DATABASE()')->fetchColumn(),['asaas_test','callback_test','crm_tests'],true)
        ||!in_array(db_prefix(),['module_test_','callback_module_test_'],true))throw new RuntimeException('Disposable native fixture database required');
    $tables=['asaas_company_origin_profiles','asaas_company_profiles','asaas_company_preferences','company_asaas_refund_postings','asaas_company_refund_observations','asaas_company_refund_requests','asaas_company_refund_grants','asaas_company_hosted_links','asaas_company_payers','asaas_company_remote_operations','asaas_company_fee_expenses','asaas_company_jobs','asaas_company_receipts','asaas_company_events','asaas_company_proofs','asaas_company_origin_meta','asaas_company_accounts',
        'asaas_settlement_outbox','asaas_payment_origin_events','asaas_payment_origins','asaas_webhook_admin_audit','asaas_webhook_integrations','asaas_webhook_events','asaas_payments_map','asaas_customers_map','asaas_logs',
        'company_expenses','invoicepaymentrecords','invoices','issuers_registry','clients','options','modules'];
    foreach($tables as $table)$pdo->exec('DROP TABLE IF EXISTS `'.db_prefix().$table.'`');
}
cleanupAsaasNativeFixture($pdo);
register_shutdown_function(static function()use($pdo){cleanupAsaasNativeFixture($pdo);});
$db=new ModuleMigrationDb($pdo);
$GLOBALS['migrationCi']=(object)['db'=>$db,'load'=>new class{function dbforge(){}},'lang'=>new class{function load($x){}function line($x){return $x.' %s';}},'app_modules'=>new class{function get($name){return ['headers'=>['version'=>'1.5.1']];}}];
$CI=&get_instance();
$pdo->exec('CREATE TABLE module_test_modules(module_name VARCHAR(50) PRIMARY KEY,installed_version VARCHAR(20)) ENGINE=InnoDB');
$pdo->exec('CREATE TABLE module_test_clients(userid INT PRIMARY KEY) ENGINE=InnoDB');
$pdo->exec("INSERT INTO module_test_modules VALUES('asaas','1.5.1')");
$pdo->exec('CREATE TABLE module_test_issuers_registry(id INT UNSIGNED PRIMARY KEY) ENGINE=InnoDB');
$pdo->exec('CREATE TABLE module_test_invoicepaymentrecords(id INT PRIMARY KEY) ENGINE=InnoDB');
$pdo->exec('CREATE TABLE module_test_company_expenses(id INT UNSIGNED PRIMARY KEY) ENGINE=InnoDB');
$n=0;function verify($v,$m){global $n;if(!$v)throw new RuntimeException($m);$n++;}
require __DIR__.'/../modules/asaas/install.php';
verify($pdo->query("SELECT installed_version FROM module_test_modules WHERE module_name='asaas'")->fetchColumn()==='1.5.1','fresh install native tracker reaches151');
foreach(['asaas_payment_origins','asaas_payment_origin_events','asaas_webhook_integrations','asaas_webhook_admin_audit','asaas_settlement_outbox'] as $table)verify($db->table_exists(db_prefix().$table),'native migration created '.$table);
verify($db->field_exists('integration_id',db_prefix().'asaas_webhook_events'),'native 148 alters inbox schema');
$pdo->exec("INSERT INTO module_test_asaas_webhook_events(event_id,payload,process_status) VALUES('legacy_fixture','{}','received')");
require __DIR__.'/../modules/asaas/install.php';
verify((int)$pdo->query('SELECT COUNT(*) FROM module_test_asaas_webhook_events')->fetchColumn()===1,'reactivation preserves history without duplicate records');
$pdo->exec("UPDATE module_test_modules SET installed_version='1.4.6' WHERE module_name='asaas'");
$pdo->exec('DROP TABLE module_test_asaas_settlement_outbox');
$runner=new App_module_migration('asaas');
verify((string)$runner->version('147')==='147','route correction has native numbered noop');
verify(!$db->table_exists('module_test_asaas_settlement_outbox'),'147 noop does not execute security schema early');
verify($pdo->query('SELECT installed_version FROM module_test_modules')->fetchColumn()==='1.4.7','native noop version recorded before 148');
verify((string)$runner->to_latest()==='151','native upgrade from146 preserves148 then executes149/150/151');
verify($db->table_exists('module_test_asaas_settlement_outbox'),'upgrade creates missing new table');
verify($pdo->query('SELECT payload FROM module_test_asaas_webhook_events')->fetchColumn()==='{}','upgrade preserves legacy payload');
$pdo->exec("UPDATE module_test_modules SET installed_version='1.4.5' WHERE module_name='asaas'");$pdo->exec('DROP TABLE module_test_asaas_settlement_outbox');
$db->failQuery='CREATE TABLE `module_test_asaas_settlement_outbox`';
try{(new App_module_migration('asaas'))->version('151');verify(false,'schema failure incorrectly accepted');}catch(RuntimeException $e){verify($e->getMessage()==='settlement_outbox_schema_failed','migration failure propagates');}
verify($pdo->query('SELECT installed_version FROM module_test_modules')->fetchColumn()==='1.4.7','failed 148 never recorded complete');
$db->failQuery=null;verify((string)(new App_module_migration('asaas'))->version('151')==='151','retry resumes native148 then149/150/151');
verify($pdo->query('SELECT installed_version FROM module_test_modules')->fetchColumn()==='1.5.1','successful retry tracked151');
foreach(['accounts','origin_meta','proofs','events','receipts','jobs','fee_expenses'] as $suffix)verify($db->table_exists('module_test_asaas_company_'.$suffix),'149 creates native adapter ledger '.$suffix);
$pdo->exec("UPDATE module_test_modules SET installed_version='1.4.8' WHERE module_name='asaas'");
$db->failQuery='CREATE TABLE IF NOT EXISTS `module_test_asaas_company_accounts`';
try{(new App_module_migration('asaas'))->version('149');verify(false,'149 DDL failure incorrectly accepted');}catch(RuntimeException $error){verify($error->getMessage()==='issuer_storage_failed','149 storage failure is sanitized');}
verify($pdo->query('SELECT installed_version FROM module_test_modules')->fetchColumn()==='1.4.8','failed149 preserves confirmed148 tracking');
$db->failQuery=null;verify((string)(new App_module_migration('asaas'))->version('149')==='149','retry149 verifies partially existing ledgers');
verify($pdo->query('SELECT installed_version FROM module_test_modules')->fetchColumn()==='1.4.9','successful149 readback confirmed');
verify((new App_module_migration('asaas'))->version('149')===true,'149 replay is native no-op');
$pdo->exec("ALTER TABLE module_test_asaas_company_receipts ALTER COLUMN proof_id SET DEFAULT 0");
try{\Asaas\CompanySchema::ensure($db,db_prefix(),false);verify(false,'149 default0 silently accepted');}catch(RuntimeException $error){verify($error->getMessage()==='asaas_company_schema_invalid','149 rejects incompatible default metadata');}
$pdo->exec("ALTER TABLE module_test_asaas_company_receipts ALTER COLUMN proof_id DROP DEFAULT");\Asaas\CompanySchema::ensure($db,db_prefix(),false);
// Native150 is additive to the confirmed149 schema and tracking.
$before149=$pdo->query('SHOW CREATE TABLE module_test_asaas_company_proofs')->fetch(PDO::FETCH_NUM)[1];
$db->failQuery='CREATE TABLE IF NOT EXISTS `module_test_asaas_company_remote_operations`';
try{(new App_module_migration('asaas'))->version('150');verify(false,'150 DDL failure incorrectly accepted');}catch(RuntimeException $error){verify($error->getMessage()==='issuer_storage_failed','150 storage failure is sanitized');}
verify($pdo->query('SELECT installed_version FROM module_test_modules')->fetchColumn()==='1.4.9','failed150 preserves confirmed149 tracking');
$db->failQuery=null;verify((string)(new App_module_migration('asaas'))->version('150')==='150','retry150 verifies existing additive ledger');
verify($pdo->query('SELECT installed_version FROM module_test_modules')->fetchColumn()==='1.5.0','successful150 readback confirmed');
verify($db->table_exists('module_test_asaas_company_remote_operations'),'150 creates native remote operation ledger');
verify($pdo->query('SHOW CREATE TABLE module_test_asaas_company_proofs')->fetch(PDO::FETCH_NUM)[1]===$before149,'150 preserves confirmed149 proof schema');
verify((new App_module_migration('asaas'))->version('150')===true,'150 replay is native no-op');
$pdo->exec('ALTER TABLE module_test_asaas_company_remote_operations ALTER COLUMN proof_id SET DEFAULT 0');
try{\Asaas\CompanyRemoteOperationsSchema::ensure($db,db_prefix(),false);verify(false,'150 default0 silently accepted');}catch(RuntimeException $error){verify($error->getMessage()==='asaas_remote_schema_invalid','150 rejects incompatible default metadata');}
$pdo->exec('ALTER TABLE module_test_asaas_company_remote_operations ALTER COLUMN proof_id DROP DEFAULT');\Asaas\CompanyRemoteOperationsSchema::ensure($db,db_prefix(),false);
// Native151 adds payer/publication ledgers without rewriting150 remote intentions.
$before150=$pdo->query('SHOW CREATE TABLE module_test_asaas_company_remote_operations')->fetch(PDO::FETCH_NUM)[1];
$db->failQuery='CREATE TABLE IF NOT EXISTS `module_test_asaas_company_payers`';
try{(new App_module_migration('asaas'))->version('151');verify(false,'151 DDL failure incorrectly accepted');}catch(RuntimeException $error){verify($error->getMessage()==='issuer_storage_failed','151 storage failure is sanitized');}
verify($pdo->query('SELECT installed_version FROM module_test_modules')->fetchColumn()==='1.5.0','failed151 preserves confirmed150 tracking');
$db->failQuery=null;verify((string)(new App_module_migration('asaas'))->version('151')==='151','retry151 verifies additive ledgers');
verify($pdo->query('SELECT installed_version FROM module_test_modules')->fetchColumn()==='1.5.1','successful151 readback confirmed');
foreach(\Asaas\CompanyCheckoutSchema::tables() as $table)verify($db->table_exists('module_test_'.$table),'151 creates native ledger '.$table);
verify($pdo->query('SHOW CREATE TABLE module_test_asaas_company_remote_operations')->fetch(PDO::FETCH_NUM)[1]===$before150,'151 preserves confirmed150 operation schema');
verify((new App_module_migration('asaas'))->version('151')===true,'151 replay is native no-op');
$pdo->exec('ALTER TABLE module_test_asaas_company_payers ALTER COLUMN client_id SET DEFAULT 0');
try{\Asaas\CompanyCheckoutSchema::ensure($db,db_prefix(),false);verify(false,'151 default0 silently accepted');}catch(RuntimeException $error){verify($error->getMessage()==='asaas_checkout_schema_invalid','151 rejects incompatible default metadata');}
$pdo->exec('ALTER TABLE module_test_asaas_company_payers ALTER COLUMN client_id DROP DEFAULT');\Asaas\CompanyCheckoutSchema::ensure($db,db_prefix(),false);
// Native152 is independent of historical151 failure/retry/replay above.
// Read the actual module header only after historical fixtures have completed.
$CI->app_modules=new class{function get($name){$header=file_get_contents(__DIR__.'/../modules/asaas/asaas.php');if(!preg_match('/Version:\s*([0-9.]+)/',$header,$m))throw new RuntimeException('Actual Asaas header required');return ['headers'=>['version'=>$m[1]]];}};
$before152=[];foreach(['asaas_payment_origins','asaas_company_accounts','asaas_company_proofs','asaas_company_receipts','invoicepaymentrecords'] as $table){if($db->table_exists(db_prefix().$table))$before152[$table]=$pdo->query('SELECT * FROM `'.db_prefix().$table.'`')->fetchAll(PDO::FETCH_ASSOC);}
$db->failQuery='CREATE TABLE IF NOT EXISTS `'.db_prefix().'asaas_company_refund_observations`';
try{(new App_module_migration('asaas'))->version('152');verify(false,'152 late DDL failure incorrectly accepted');}catch(RuntimeException $error){verify($error->getMessage()==='issuer_storage_failed','152 DDL failure is sanitized and propagated');}
verify($pdo->query("SELECT installed_version FROM ".db_prefix()."modules WHERE module_name='asaas'")->fetchColumn()==='1.5.1','152 failure leaves historical151 tracking intact');
$db->failQuery=null;verify((string)(new App_module_migration('asaas'))->version('152')==='152','native152 retry verifies partial additive refund schema');
\Asaas\CompanyRefundSchema::ensure($db,db_prefix(),false);foreach(\Asaas\CompanyRefundSchema::definitions() as $table=>$fields)verify((int)$pdo->query('SELECT COUNT(*) FROM `'.db_prefix().$table.'`')->fetchColumn()===0,'native152 default-off empty '.$table);
verify((new App_module_migration('asaas'))->version('152')===true,'152 replay is native no-op');foreach($before152 as $table=>$rows)verify($pdo->query('SELECT * FROM `'.db_prefix().$table.'`')->fetchAll(PDO::FETCH_ASSOC)===$rows,'152 failure/retry/replay preserves '.$table);


// Native153 validates the actual core437 posting participant; historical152 is kept above.
require_once APPPATH.'services/CompanyAsaasRefundSchema.php';
$pdo->exec('CREATE TABLE module_test_invoices(id INT PRIMARY KEY) ENGINE=InnoDB');
try{(new App_module_migration('asaas'))->version('153');verify(false,'153 missing core437 participant incorrectly accepted');}catch(RuntimeException $error){verify($error->getMessage()==='issuer_asaas_full_refund_schema_invalid','153 explicitly rejects missing core437 posting schema');}
verify($pdo->query("SELECT installed_version FROM module_test_modules WHERE module_name='asaas'")->fetchColumn()==='1.5.2','153 unmet core437 prerequisite preserves historical152 tracking');
CompanyAsaasRefundSchema::ensure($db,db_prefix());
$before153=[];foreach(array_merge(array_keys($before152),['asaas_company_refund_grants','asaas_company_refund_requests','asaas_company_refund_observations']) as $table)$before153[$table]=$pdo->query('SELECT * FROM `'.db_prefix().$table.'`')->fetchAll(PDO::FETCH_ASSOC);
$db->failQuery='UPDATE `module_test_modules`';
verify((new App_module_migration('asaas'))->version('153')===false,'153 tracking write failure cannot report complete native upgrade');
verify($pdo->query("SELECT installed_version FROM module_test_modules WHERE module_name='asaas'")->fetchColumn()==='1.5.2','153 failed tracking retains actual152 marker');
$db->failQuery=null;
verify((string)(new App_module_migration('asaas'))->version('153')==='153','native153 retry validates exact existing participants');
verify($pdo->query("SELECT installed_version FROM module_test_modules WHERE module_name='asaas'")->fetchColumn()==='1.5.3','153 native tracking durable readback');
verify((new App_module_migration('asaas'))->version('153')===true,'153 replay is native no-op');
verify((int)$pdo->query('SELECT COUNT(*) FROM module_test_company_asaas_refund_postings')->fetchColumn()===0,'437 posting participant does not backfill financial declarations');
foreach($before153 as $table=>$rows)verify($pdo->query('SELECT * FROM `'.db_prefix().$table.'`')->fetchAll(PDO::FETCH_ASSOC)===$rows,'153 failure/retry/replay preserves '.$table);
// New154 is independently applied after the historical153 checkpoint above.
$pdo->exec('CREATE TABLE module_test_options(name VARCHAR(191) PRIMARY KEY,value TEXT NOT NULL,autoload INT NOT NULL DEFAULT 1) ENGINE=InnoDB');
$db->insert(db_prefix().'options',['name'=>'paymentmethod_asaas_description','value'=>'']);
$db->failQuery='ALTER TABLE `module_test_asaas_company_accounts` ADD `token_cipher`';
try{(new App_module_migration('asaas'))->version('154');verify(false,'154 token DDL failure incorrectly accepted');}catch(RuntimeException $error){verify($error->getMessage()==='issuer_storage_failed','154 DDL failure propagates sanitized');}
verify($pdo->query("SELECT installed_version FROM module_test_modules WHERE module_name='asaas'")->fetchColumn()==='1.5.3','154 partial DDL preserves historical153 tracking');
verify(!$db->get_where(db_prefix().'options',['name'=>'paymentmethod_asaas_profile_schema'])->row_array(),'154 token schema completes before any profile marker');
$db->failQuery='UPDATE `module_test_modules`';
$native154=new App_module_migration('asaas');verify($native154->version('154')===false,'154 tracking write failure cannot report complete upgrade');
verify($native154->error_string()==='module_migration_version_write_failed','154 native failure reason remains available');
verify($pdo->query("SELECT installed_version FROM module_test_modules WHERE module_name='asaas'")->fetchColumn()==='1.5.3','154 failed tracking still records153 even after complete schema/seed');
$db->failQuery=null;
verify((string)(new App_module_migration('asaas'))->version('154')==='154','native154 retry verifies exact additive schema and seed');
verify($pdo->query("SELECT installed_version FROM module_test_modules WHERE module_name='asaas'")->fetchColumn()==='1.5.4','154 native tracking readback confirmed');
\Asaas\WebhookTokenSchema::ensure($db,db_prefix(),false);
foreach(['production','sandbox'] as $environment)verify($db->get_where(db_prefix().'options',['name'=>'paymentmethod_asaas_profile_'.$environment.'_description'])->row_array()['value']==='','154 preserves empty legacy values for '.$environment);
$profiles154=$pdo->query('SELECT * FROM module_test_options ORDER BY name')->fetchAll(PDO::FETCH_ASSOC);
verify((new App_module_migration('asaas'))->version('154')===true,'154 replay is native no-op');
verify($pdo->query('SELECT * FROM module_test_options ORDER BY name')->fetchAll(PDO::FETCH_ASSOC)===$profiles154,'154 replay preserves all operational values');
foreach($before153 as $table=>$rows){$after=$pdo->query('SELECT * FROM `'.db_prefix().$table.'`')->fetchAll(PDO::FETCH_ASSOC);if($table==='asaas_company_accounts')$after=array_map(static function($row){unset($row['token_cipher']);return $row;},$after);verify($after===$rows,'154 failures/retry/replay preserve existing '.$table);}
// Historical155 is reached only after preserving every explicit historical154 checkpoint.
$before155=[];
foreach(array_merge(array_keys($before153),['options']) as $table)$before155[$table]=$pdo->query('SELECT * FROM `'.db_prefix().$table.'`')->fetchAll(PDO::FETCH_ASSOC);
$db->failQuery='CREATE TABLE IF NOT EXISTS `module_test_asaas_company_origin_profiles`';
try{(new App_module_migration('asaas'))->version('155');verify(false,'155 late DDL failure incorrectly accepted');}catch(RuntimeException $error){verify($error->getMessage()==='issuer_storage_failed','155 late DDL failure propagates sanitized');}
verify($pdo->query("SELECT installed_version FROM module_test_modules WHERE module_name='asaas'")->fetchColumn()==='1.5.4','155 partial DDL preserves confirmed historical154 tracking');
verify($db->table_exists('module_test_asaas_company_preferences')&&!$db->table_exists('module_test_asaas_company_origin_profiles'),'155 late DDL fixture actually leaves a partial additive schema');
$db->failQuery='UPDATE `module_test_modules`';
$native155=new App_module_migration('asaas');verify($native155->version('155')===false,'155 tracking failure cannot report a completed upgrade');
verify($native155->error_string()==='module_migration_version_write_failed','155 tracking failure reason remains available');
verify($pdo->query("SELECT installed_version FROM module_test_modules WHERE module_name='asaas'")->fetchColumn()==='1.5.4','155 failed tracking preserves154 after complete schema');
\Asaas\CompanyPreferencesSchema::ensure($db,db_prefix(),false);
$db->failQuery=null;
verify((string)(new App_module_migration('asaas'))->version('155')==='155','historical native target retries and completes155');
verify($pdo->query("SELECT installed_version FROM module_test_modules WHERE module_name='asaas'")->fetchColumn()==='1.5.5','155 native tracking readback confirmed');
foreach(\Asaas\CompanyPreferencesSchema::tables() as $table)verify((int)$pdo->query('SELECT COUNT(*) FROM `'.db_prefix().$table.'`')->fetchColumn()===0,'155 creates empty default-off ledger without adoption: '.$table);
verify((new App_module_migration('asaas'))->version('155')===true,'historical155 replay is native no-op');
foreach($before155 as $table=>$rows)verify($pdo->query('SELECT * FROM `'.db_prefix().$table.'`')->fetchAll(PDO::FETCH_ASSOC)===$rows,'155 failures/retry/replay preserve existing '.$table);
$before156=[];
foreach(array_unique(array_merge(array_keys($before155),\Asaas\CompanyPreferencesSchema::tables())) as $table)$before156[$table]=$pdo->query('SELECT * FROM `'.db_prefix().$table.'`')->fetchAll(PDO::FETCH_ASSOC);
$db->failQuery='UPDATE `module_test_modules`';
$native156=new App_module_migration('asaas');verify($native156->to_latest()===false,'156 cannot hide a native tracking failure');
verify($pdo->query("SELECT installed_version FROM module_test_modules WHERE module_name='asaas'")->fetchColumn()==='1.5.5','failed156 tracking preserves confirmed155');
$db->failQuery=null;
verify((string)(new App_module_migration('asaas'))->to_latest()==='156','current native target executes156');
verify($pdo->query("SELECT installed_version FROM module_test_modules WHERE module_name='asaas'")->fetchColumn()==='1.5.6','156 native tracking readback confirmed');
verify((new App_module_migration('asaas'))->to_latest()===true,'156 replay is native no-op');
foreach($before156 as $table=>$rows)verify($pdo->query('SELECT * FROM `'.db_prefix().$table.'`')->fetchAll(PDO::FETCH_ASSOC)===$rows,'156 preserves existing data: '.$table);
echo "OK: {$n} native module migration MySQL assertions.\n";
