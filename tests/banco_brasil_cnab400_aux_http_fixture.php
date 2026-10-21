<?php
/** New443 read-only HTTP prerequisite and synthetic wire generator. No DDL, tracking setters, bank or financial writes. */
set_exception_handler(static function(Throwable$e){fwrite(STDERR,get_class($e).': '.$e->getMessage().' at '.$e->getFile().':'.$e->getLine().PHP_EOL);exit(1);});
if(PHP_SAPI!=='cli'||getenv('ISSUERS_BROWSER_DB_NAME')!=='argws_upgrade_ci')throw new RuntimeException('explicit_disposable_CLI_environment_required');
define('FCPATH',dirname(__DIR__).'/');define('BASEPATH',FCPATH.'system/');define('APPPATH',FCPATH.'application/');define('APP_MODULES_PATH',FCPATH.'modules/');
require APPPATH.'config/app-config.php';if(APP_DB_NAME!=='argws_upgrade_ci'||!preg_match('/^(127\.0\.0\.1|argws-crm-provision-db-[0-9]+)(:[0-9]+)?$/D',APP_DB_HOSTNAME))throw new RuntimeException('native_config_disposable_host_and_database_required');
require __DIR__.'/support/BbPdoDatabase.php';require __DIR__.'/support/BbHttpNativeRelease.php';
require_once APP_MODULES_PATH.'banco_brasil/libraries/BancoBrasil/ModuleStore.php';require_once APP_MODULES_PATH.'banco_brasil/libraries/BancoBrasil/Cnab400Generator.php';require_once APP_MODULES_PATH.'banco_brasil/libraries/BancoBrasil/Cnab400ReturnParser.php';
require __DIR__.'/support/Bb400AuxHttpWire.php';
function db_prefix(){return 'tbl';}
$pdo=new PDO('mysql:host='.str_replace(':',';port=',APP_DB_HOSTNAME).';dbname='.APP_DB_NAME.';charset=utf8mb4',APP_DB_USERNAME,APP_DB_PASSWORD,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);$db=new BbPdoDatabase($pdo);
$pdo->exec('SET SESSION TRANSACTION READ ONLY');$pdo->beginTransaction();
try{
 $config=[];require APPPATH.'config/migration.php';$target=(int)($config['migration_version']??0);if($target<443||(int)$pdo->query('SELECT version FROM tblmigrations')->fetchColumn()!==$target)throw new RuntimeException('exact_native443_or_later_required');
 $release=bbHttpNativeRelease();$modules=$pdo->query("SELECT installed_version,active FROM tblmodules WHERE module_name='banco_brasil'")->fetchAll(PDO::FETCH_ASSOC);if($release['migration']<122||count($modules)!==1||$modules[0]['installed_version']!==$release['version']||(int)$modules[0]['active']!==1)throw new RuntimeException('actual_exact_shipped_BB122_or_later_required');
 $action=$argv[1]??'snapshot';
 if($action==='snapshot'){
  $tables=['invoices','issuers_invoice_documents','company_invoice_requests','issuers_sequences','itemable','item_tax','invoicepaymentrecords','creditnotes','credits','company_financial_events','company_payment_receipts','company_expenses','bb_origins','bb_event_links','bb_event_reviews','bb_event_receipts','asaas_payment_origins'];$hashes=[];$counts=[];
  foreach($tables as$table){if($table==='asaas_payment_origins'&&!$db->table_exists('tbl'.$table)){$counts[$table]=null;$hashes[$table]='absent_optional_module';continue;}$rows=$pdo->query('SELECT * FROM tbl'.$table.' ORDER BY 1')->fetchAll(PDO::FETCH_ASSOC);$counts[$table]=count($rows);$hashes[$table]=hash('sha256',json_encode($rows,JSON_THROW_ON_ERROR));}
  $out=['read_only'=>true,'native_marker'=>$target,'module'=>$release['version'],'hashes'=>$hashes,'counts'=>$counts];
 }elseif($action==='prepare'){
  $letter=$argv[2]??'';$connection=$argv[3]??'';if(!in_array($letter,['a','b'],true)||!preg_match('/^[1-9][0-9]*$/D',$connection))throw new RuntimeException('synthetic_scope_input_required');
  $s=$pdo->prepare('SELECT * FROM tblissuers_registry WHERE tax_id=?');$s->execute([$letter==='a'?'11222333000181':'52998224725']);$owners=$s->fetchAll(PDO::FETCH_ASSOC);if(count($owners)!==1)throw new RuntimeException('prior_real_GUI_owner_required');$issuer=(int)$owners[0]['id'];
  $s=$pdo->prepare('SELECT * FROM tblbb_connections WHERE id=? AND issuer_id=?');$s->execute([(int)$connection,$issuer]);$rows=$s->fetchAll(PDO::FETCH_ASSOC);if(count($rows)!==1||$rows[0]['environment']!=='sandbox')throw new RuntimeException('existing_own_sandbox_connection_required');$a=\BancoBrasil\ModuleStore::agreement($rows[0]);
  $email='<script>raw-'.$letter.'</script>@example.invalid';$key='http://127.0.0.1:9/private-'.$letter.'?<script>raw</script>';$txid='<script>TXID-'.$letter.'</script>';
  $fields=['email'=>$email,'key'=>$key,'txid'=>$txid,'occurrence'=>'12'];$base=bb400AuxWire($a,[]);$wire=bb400AuxWire($a,['01','10'],$fields);$repack=bb400Mutate($wire,0,101,'0000002');$unknown=bb400AuxWire($a,['01','04'],$fields);
  $out=['read_only'=>true,'native_marker'=>$target,'module'=>$release['version'],'issuer'=>$issuer,'connection'=>(int)$connection,'base_layout'=>\BancoBrasil\LayoutCatalog::CNAB400,'aux_layout'=>\BancoBrasil\LayoutCatalog::CNAB400_AUX,'base'=>base64_encode($base),'wire'=>base64_encode($wire),'repack'=>base64_encode($repack),'unsupported'=>base64_encode($unknown),'hashes'=>['base'=>hash('sha256',$base),'wire'=>hash('sha256',$wire),'repack'=>hash('sha256',$repack),'unsupported'=>hash('sha256',$unknown)],'raw'=>['email'=>str_pad($email,137),'key'=>str_pad($key,77),'txid'=>str_pad($txid,35),'occurrence'=>'12'],'external_transport'=>false];
 }else throw new RuntimeException('unknown_read_only_fixture_action');
 echo json_encode($out,JSON_THROW_ON_ERROR).PHP_EOL;
}finally{if($pdo->inTransaction())$pdo->rollBack();}
