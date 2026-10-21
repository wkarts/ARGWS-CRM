<?php
/** Read-only recurrence proof on the disposable native HTTP fixture. */
if (PHP_SAPI!=='cli' || getenv('ISSUERS_BROWSER_DB_NAME')!=='argws_upgrade_ci') { exit(2); }
define('FCPATH',dirname(__DIR__,2).'/'); define('BASEPATH',FCPATH.'system/'); define('APPPATH',FCPATH.'application/');
require APPPATH.'config/app-config.php';
if (APP_DB_NAME!=='argws_upgrade_ci' || !preg_match('/^(127\.0\.0\.1|argws-crm-provision-db-[0-9]+)(:[0-9]+)?$/D',APP_DB_HOSTNAME)) { exit(2); }
require dirname(__DIR__).'/support/BbPdoDatabase.php'; require APPPATH.'services/CompanyTaskRecurring.php';
function db_prefix() { return 'tbl'; }
$pdo=new PDO('mysql:host='.str_replace(':',';port=',APP_DB_HOSTNAME).';dbname='.APP_DB_NAME.';charset=utf8mb4',APP_DB_USERNAME,APP_DB_PASSWORD,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$db=new BbPdoDatabase($pdo);
$identity=$db->query('SELECT master_staff_id,primary_issuer_id,source FROM tblissuers_installation_identity WHERE id=1')->row_array();
if ((int)$identity['master_staff_id']!==17 || !in_array($identity['source'],['installer','legacy_designation'],true)) { throw new RuntimeException('Existing installer master required'); }
$issuer=IssuerPolicy::id($argv[1]??null); $kind=$argv[2]??''; $id=IssuerPolicy::id($argv[3]??null);
$service=new CompanyTaskRecurring($db,'tbl'); $detail=$service->get($issuer,17,$kind,$id);
echo json_encode($detail,JSON_THROW_ON_ERROR).PHP_EOL;
