<?php
/** Disposable HTTP prototype only: additive ensure/replay, not a future migration/tracking claim. */
if(PHP_SAPI!=='cli'||getenv('ISSUERS_BROWSER_DB_NAME')!=='argws_upgrade_ci')exit(2);
define('FCPATH',dirname(__DIR__,2).'/');define('BASEPATH',FCPATH.'system/');define('APPPATH',FCPATH.'application/');define('APP_MODULES_PATH',FCPATH.'modules/');require APPPATH.'config/app-config.php';
if(APP_DB_NAME!=='argws_upgrade_ci'||APP_DB_HOSTNAME!=='127.0.0.1:48324')exit(2);
require FCPATH.'tests/support/BbPdoDatabase.php';require APPPATH.'services/CompanyClientImportSchema.php';require APPPATH.'services/CompanyClientRequestSchema.php';
function db_prefix(){return 'tbl';}function &get_instance(){return $GLOBALS['CI'];}
$pdo=new PDO('mysql:host=127.0.0.1;port=48324;dbname='.APP_DB_NAME.';charset=utf8mb4',APP_DB_USERNAME,APP_DB_PASSWORD,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);$db=new BbPdoDatabase($pdo);$GLOBALS['CI']=(object)['db'=>$db];
$markerBefore=$pdo->query('SELECT version FROM tblmigrations')->fetchColumn();$master=(int)$pdo->query('SELECT master_staff_id FROM tblissuers_installation_identity WHERE id=1')->fetchColumn();if($master!==17)throw new RuntimeException('Synthetic installer must identify its actual master17');CompanyClientImportSchema::ensure($db,'tbl');CompanyClientImportSchema::ensure($db,'tbl');CompanyClientRequestSchema::ensure($db,'tbl');
// Explicit synthetic identities only; this fixture never reclassifies an existing NULL client.
foreach([['Synthetic import HTTP A','11222333000181'],['Synthetic import HTTP B','52998224725']] as [$name,$tax]){$statement=$pdo->prepare('SELECT id FROM tblissuers_registry WHERE tax_id=?');$statement->execute([$tax]);if(!$statement->fetchColumn()){$insert=$pdo->prepare('INSERT INTO tblissuers_registry(legal_name,tax_id,active,settings_json,created_by,created_at,identity_status) VALUES(?,?,1,?,17,UTC_TIMESTAMP(),?)');$insert->execute([$name,$tax,'{}','complete']);}}
if((int)$pdo->query("SELECT COUNT(*) FROM tblclients WHERE company='Synthetic NULL client import sentinel'")->fetchColumn()===0)$pdo->exec("INSERT INTO tblclients(company,datecreated,addedfrom,active) VALUES('Synthetic NULL client import sentinel',UTC_TIMESTAMP(),17,1)");
$markerAfter=$pdo->query('SELECT version FROM tblmigrations')->fetchColumn();if((string)$markerBefore!==(string)$markerAfter)throw new RuntimeException('Prototype ensure must not advance native tracking');
echo json_encode(['prototype_schema_ensure_replay'=>true,'native_marker_unchanged'=>(string)$markerAfter,'master_id'=>$master,'database_runtime'=>$pdo->getAttribute(PDO::ATTR_SERVER_VERSION),'tracking_limit'=>'future_official_core_migration_not_executed'],JSON_THROW_ON_ERROR).PHP_EOL;
