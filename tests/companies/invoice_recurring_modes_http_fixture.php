<?php
/** Reconstructed 2026-10-20 prerequisite; NOT the unavailable original439 fixture.
 * Strictly read-only: exact native target from trusted source configuration must already exist; A/B data comes from prior real GUI.
 */
if(PHP_SAPI!=='cli'||getenv('ISSUERS_BROWSER_DB_NAME')!=='argws_upgrade_ci'){fwrite(STDERR,"Disposable browser database required.\n");exit(2);}
define('FCPATH',dirname(__DIR__,2).'/');define('BASEPATH',FCPATH.'system/');define('APPPATH',FCPATH.'application/');
require_once APPPATH.'config/app-config.php';
if(APP_DB_NAME!=='argws_upgrade_ci'||!preg_match('/^(127\.0\.0\.1|argws-crm-provision-db-[0-9]+)(:[0-9]+)?$/D',APP_DB_HOSTNAME))throw new RuntimeException('fixture_requires_disposable_native_application');
require_once FCPATH.'tests/support/BbPdoDatabase.php';
function db_prefix(){return 'tbl';}function &get_instance(){return $GLOBALS['CI'];}
$pdo=new PDO('mysql:host='.str_replace(':',';port=',APP_DB_HOSTNAME).';dbname='.APP_DB_NAME.';charset=utf8mb4',APP_DB_USERNAME,APP_DB_PASSWORD,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$db=new BbPdoDatabase($pdo);$GLOBALS['CI']=(object)['db'=>$db];
$expected=(static function():int{$config=[];require APPPATH.'config/migration.php';$target=$config['migration_version']??null;if(!is_int($target)||$target<440)throw new RuntimeException('fixture_requires_trusted_native_target');return$target;})();
// Enforced by MariaDB: accidental fixture writes fail, rather than silently seeding prerequisites.
$pdo->exec('SET SESSION TRANSACTION READ ONLY');$pdo->beginTransaction();
try{
 $marker=(int)$pdo->query('SELECT version FROM tblmigrations')->fetchColumn();if($marker!==$expected)throw new RuntimeException('fixture_requires_exact_configured_native_marker');
 $schemas=['CompanyInvoiceRecurringSchema','CompanyInvoiceDraftsSchema','CompanyInvoiceRecurringModesSchema','CompanyInvoiceRecurringDraftsSchema','CompanyInvoiceRecurringDiscoverySchema'];
 foreach($schemas as$class){require_once APPPATH.'services/'.$class.'.php';$class::assertValid($db,'tbl');}
 $master=(int)$pdo->query('SELECT master_staff_id FROM tblissuers_installation_identity WHERE id=1')->fetchColumn();
 $s=$pdo->prepare('SELECT staffid FROM tblstaff WHERE staffid=? AND active=1 AND admin=1');$s->execute([$master]);if(!$s->fetchColumn())throw new RuntimeException('fixture_requires_existing_active_master');
 $owners=[];foreach(['a'=>'11222333000181','b'=>'52998224725']as$letter=>$tax){
  $s=$pdo->prepare('SELECT r.id issuer,c.userid client FROM tblissuers_registry r JOIN tblissuers_clients o ON o.owner_issuer_id=r.id JOIN tblclients c ON c.userid=o.client_id WHERE r.tax_id=? AND c.company=? AND c.active=1 ORDER BY c.userid');
  $s->execute([$tax,'Synthetic portal client '.$letter]);$rows=$s->fetchAll(PDO::FETCH_ASSOC);if(count($rows)!==1)throw new RuntimeException('fixture_requires_prior_gui_owner_client_'.$letter);
  $owners[$letter]=array_map('intval',$rows[0]);
 }
 if($owners['a']['issuer']===$owners['b']['issuer']||$owners['a']['client']===$owners['b']['client'])throw new RuntimeException('fixture_requires_distinct_owners');
 if((int)$pdo->query('SELECT version FROM tblmigrations')->fetchColumn()!==$expected)throw new RuntimeException('fixture_marker_changed');
 echo json_encode(['master'=>$master,'owners'=>$owners,'native_marker'=>$marker,'expected_marker'=>$expected,'read_only'=>true,'schemas'=>$schemas,'provenance'=>'reconstructed prerequisite; no original439 fixture source available'],JSON_THROW_ON_ERROR).PHP_EOL;
}finally{if($pdo->inTransaction())$pdo->rollBack();}
