<?php
/** Own canonical principal fixture only; never changes master, permissions, version tracking or existing snapshots. */
if(PHP_SAPI!=='cli'||getenv('ISSUERS_BROWSER_DB_NAME')!=='argws_upgrade_ci')exit(2);
define('FCPATH',dirname(__DIR__,2).'/');define('BASEPATH',FCPATH.'system/');define('APPPATH',FCPATH.'application/');
require APPPATH.'config/app-config.php';if(APP_DB_NAME!=='argws_upgrade_ci')exit(2);
require dirname(__DIR__).'/support/BbPdoDatabase.php';require APPPATH.'services/CompanySettingsIdentity.php';require APPPATH.'services/CompanyClients.php';
function db_prefix(){return 'tbl';}function log_message($level,$message){}
$pdo=new PDO('mysql:host='.APP_DB_HOSTNAME.';dbname='.APP_DB_NAME.';charset=utf8mb4',APP_DB_USERNAME,APP_DB_PASSWORD,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);$db=new BbPdoDatabase($pdo);
$identity=$db->query('SELECT master_staff_id,primary_issuer_id FROM tblissuers_installation_identity WHERE id=1')->row_array();$actor=(int)($identity['master_staff_id']??0);$issuer=(int)($identity['primary_issuer_id']??0);if(!$actor||!$issuer)throw new RuntimeException('native fixture authority unresolved');
$service=new CompanySettingsIdentity($db,'tbl');$current=$service->read($issuer);$profile=[];foreach(['invoice_company_address'=>'Synthetic principal street','invoice_company_city'=>'Synthetic city','company_state'=>'BA','invoice_company_country_code'=>'BR','invoice_company_postal_code'=>'00000-000'] as $key=>$value)if(($current['profile'][$key]??'')==='')$profile[$key]=$value;$input=$profile?['profile'=>$profile]:[];if(($current['tax_id']??'')==='')$input['tax_id']='65123456000194';if($input)$service->save($issuer,$actor,$input);
$name='Synthetic principal PDF format client';$client=(int)($db->query('SELECT c.userid FROM tblclients c JOIN tblissuers_clients o ON o.client_id=c.userid WHERE o.owner_issuer_id=? AND c.company=? AND c.active=1',[$issuer,$name])->row()->userid??0);if(!$client)$client=(new CompanyClients($db,'tbl'))->create($issuer,$actor,['company'=>$name,'vat'=>''],hash('sha256','sales-pdf-browser-primary-client'));
echo json_encode(['issuer'=>$issuer,'client'=>$client],JSON_THROW_ON_ERROR);
