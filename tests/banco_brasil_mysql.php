<?php
declare(strict_types=1);
require __DIR__.'/banco_brasil_cnab240.php';
require __DIR__.'/support/BbPdoDatabase.php';
require_once __DIR__.'/../modules/banco_brasil/libraries/BancoBrasil/ModuleStore.php';
define('BASEPATH',__DIR__);define('APP_MODULES_PATH',dirname(__DIR__).'/modules/');
function db_prefix(){return 'bb_test_';}function &get_instance(){return $GLOBALS['CI'];}
require dirname(__DIR__).'/application/libraries/App_module_migration.php';
$pdo=new PDO(getenv('MYSQL_DSN')?:'mysql:host=127.0.0.1;dbname=bb_test;charset=utf8mb4',getenv('MYSQL_USER')?:'root',getenv('MYSQL_PASSWORD')?:'root',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$db=new BbPdoDatabase($pdo);$GLOBALS['CI']=(object)['db'=>$db,'load'=>new class{function dbforge(){}},'lang'=>new class{function load($v){}function line($v){return $v;}},'app_modules'=>new class{function get($v){return ['headers'=>['version'=>'1.0.2']];}}];
$n=0;
$pdo->exec('CREATE TABLE bb_test_modules(module_name VARCHAR(32) PRIMARY KEY,installed_version VARCHAR(16)) ENGINE=InnoDB');
$pdo->exec("INSERT INTO bb_test_modules VALUES('banco_brasil','1.0.1')");
require APP_MODULES_PATH.'banco_brasil/install.php';check($pdo->query('SELECT installed_version FROM bb_test_modules')->fetchColumn()==='1.0.2','native fresh install tracked');
$engines=$pdo->query("SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME LIKE 'bb_test_bb_%'")->fetchAll(PDO::FETCH_COLUMN);check(count($engines)===8&&array_unique($engines)===['InnoDB'],'all module tables InnoDB');
require_once dirname(__DIR__).'/modules/banco_brasil/libraries/BancoBrasil/ReturnArchiveSchema.php';
\BancoBrasil\ReturnArchiveSchema::upgrade($db,'bb_test_');
\BancoBrasil\ReturnArchiveSchema::upgrade($db,'bb_test_'); // Additive replay.
$store=new \BancoBrasil\ModuleStore($db,'bb_test_');$data=['account_id'=>'001:00452-3:000000123873-X','environment'=>'sandbox','agreement_number'=>'1234567','wallet'=>'17','variation'=>'019'];
$id=$store->createConnection(1,9,['id'=>1,'tax_id'=>'12ABC34501DE35','legal_name'=>'EMPRESA SINTETICA'],$data);check($id===1,'connection committed');
check($store->createConnection(1,9,['id'=>1,'tax_id'=>'12ABC34501DE35','legal_name'=>'EMPRESA SINTETICA'],$data)===$id,'same connection idempotent');
$c=$store->connection($id,1);$binding=$store->registerLayout($c,9,\BancoBrasil\Agreement::LAYOUT_ID);$agreement=\BancoBrasil\ModuleStore::agreement($c);$lines2=explode("\r\n",rtrim($g->generate($agreement,1,'2026-10-18',[$title]),"\r\n"));$wire=returnBytes($agreement,$lines2);
$r=$store->importReturn($c,9,$wire,$binding);check(!$r['duplicate']&&$r['events']===1,'real transactional return import');
check($store->importReturn($c,9,$wire,$binding)['duplicate'],'file unique dedup');
$original=$pdo->query('SELECT * FROM bb_test_bb_return_originals WHERE return_file_id='.(int)$r['id'])->fetch(PDO::FETCH_ASSOC);
check($original['content']===$wire&&(int)$original['byte_count']===strlen($wire)&&$original['content_hash']===hash('sha256',$wire),'real DB retains original bytes/hash/size');
check((int)$pdo->query('SELECT COUNT(*) FROM bb_test_bb_return_originals')->fetchColumn()===1,'reimport does not duplicate originals');
$pdo->exec("CREATE TRIGGER corrupt_bank_original BEFORE INSERT ON bb_test_bb_return_originals FOR EACH ROW SET NEW.content='corrupt'");
rejects(fn()=>$store->importReturn($c,9,mutateReturn($wire,0,158,'000008'),$binding),'late original content mutation aborts transactional import');
$pdo->exec('DROP TRIGGER corrupt_bank_original');
check((int)$pdo->query('SELECT COUNT(*) FROM bb_test_bb_return_files')->fetchColumn()===1&&(int)$pdo->query('SELECT COUNT(*) FROM bb_test_bb_return_originals')->fetchColumn()===1,'corrupt retention leaves no orphan file or events');

$pdo->exec('ALTER TABLE bb_test_bb_return_originals ENGINE=MyISAM');rejects(fn()=>$store->importReturn($c,9,$wire,$binding),'archive nontransactional engine blocks import even on replay');$pdo->exec('ALTER TABLE bb_test_bb_return_originals ENGINE=InnoDB');
$pdo->exec('DELETE FROM bb_test_bb_return_originals WHERE return_file_id='.(int)$r['id']);
check($store->importReturn($c,9,$wire,$binding)['duplicate'],'exact known file may restore missing pre-retention original without recreating event');
check($pdo->query('SELECT content FROM bb_test_bb_return_originals LIMIT 1')->fetchColumn()===$wire,'historical recovery uses exact uploaded original');

$store->importReturn($c,9,mutateReturn($wire,0,158,'000002'),$binding);check((int)$pdo->query('SELECT COUNT(*) FROM bb_test_bb_return_events')->fetchColumn()===1,'semantic event unique');
check((int)$pdo->query('SELECT COUNT(*) FROM bb_test_bb_return_file_events')->fetchColumn()===2,'every repackaged file retains event provenance');
$bad=mutateReturn(mutateReturn($wire,0,158,'000003'),2,82,'000000000020001');rejects(fn()=>$store->importReturn($c,9,$bad,$binding),'conflicting event rejected');
check((int)$pdo->query('SELECT COUNT(*) FROM bb_test_bb_return_files')->fetchColumn()===2,'conflict rolls back file insert');
$pdo->beginTransaction();rejects(fn()=>$store->importReturn($c,9,$wire,$binding),'outer PDO transaction refused');check($pdo->inTransaction(),'outer transaction not committed');$pdo->rollBack();
$pdo->exec('SET SESSION completion_type=1');rejects(fn()=>$store->importReturn($c,9,$wire,$binding),'chain completion refused');$pdo->exec('SET SESSION completion_type=0');
$pdo->exec('ALTER TABLE bb_test_bb_audit ENGINE=MyISAM');rejects(fn()=>$store->importReturn($c,9,$wire,$binding),'non transactional participant refused');$pdo->exec('ALTER TABLE bb_test_bb_audit ENGINE=InnoDB');
require APP_MODULES_PATH.'banco_brasil/install.php';check((int)$pdo->query('SELECT COUNT(*) FROM bb_test_bb_return_events')->fetchColumn()===1,'reactivation preserves audit and observations');
check((new App_module_migration('banco_brasil'))->to_latest()===true,'current native no-op');
check($pdo->query("SELECT financial_state FROM bb_test_bb_return_events LIMIT 1")->fetchColumn()==='not_applied','no implicit financial effect');
check(count($store->recentEvents(1))===1&&count($store->recentEvents(2))===0,'review is issuer scoped');
// Existing partial/weak schema must not be certified, and existing observations must survive.
$pdo->exec('ALTER TABLE bb_test_bb_return_events DROP INDEX bb_return_event');
rejects(function(){require APP_MODULES_PATH.'banco_brasil/install.php';},'missing full unique prevents native installation');
check($pdo->query('SELECT installed_version FROM bb_test_modules')->fetchColumn()==='1.0.0','weak schema leaves native baseline');
check((int)$pdo->query('SELECT COUNT(*) FROM bb_test_bb_return_events')->fetchColumn()===1,'failed schema validation preserves existing observations');
$pdo->exec('ALTER TABLE bb_test_bb_return_events ADD UNIQUE KEY bb_return_event(scope_key,event_key)');
check((string)(new App_module_migration('banco_brasil'))->to_latest()==='102','compatible unique fixture restored and upgrade retries');
$pdo->exec('ALTER TABLE bb_test_bb_return_events MODIFY event_key CHAR(64) NULL');
rejects(function(){require APP_MODULES_PATH.'banco_brasil/install.php';},'nullable identity prevents native installation');
check($pdo->query('SELECT installed_version FROM bb_test_modules')->fetchColumn()==='1.0.0','wrong column leaves native baseline');
check((int)$pdo->query('SELECT COUNT(*) FROM bb_test_bb_return_events')->fetchColumn()===1,'wrong column failure preserves existing observations');
$pdo->exec('ALTER TABLE bb_test_bb_return_events MODIFY event_key CHAR(64) NOT NULL');
check((string)(new App_module_migration('banco_brasil'))->to_latest()==='102','compatible column fixture restored and upgrade retries');
echo "OK {$n} Banco do Brasil MySQL checks\n";

$originalCount=(int)$pdo->query('SELECT COUNT(*) FROM bb_test_bb_return_originals')->fetchColumn();
$pdo->exec('ALTER TABLE bb_test_bb_return_originals DROP INDEX bb_return_original_file');
rejects(fn()=>\BancoBrasil\ReturnArchiveSchema::upgrade($db,'bb_test_'),'additive migration does not certify an archive missing its full unique');
check((int)$pdo->query('SELECT COUNT(*) FROM bb_test_bb_return_originals')->fetchColumn()===$originalCount,'failed archive migration preserves all originals');
$pdo->exec('ALTER TABLE bb_test_bb_return_originals ADD UNIQUE KEY bb_return_original_file(return_file_id)');
\BancoBrasil\ReturnArchiveSchema::upgrade($db,'bb_test_');
