<?php
/** Disposable real-browser fixture. Adds optional definitions through the actual company writer only. */
if(PHP_SAPI!=='cli'||getenv('ISSUERS_BROWSER_DB_NAME')!=='argws_upgrade_ci')exit(2);
define('FCPATH',dirname(__DIR__,2).'/');define('BASEPATH',FCPATH.'system/');define('APPPATH',FCPATH.'application/');require APPPATH.'config/app-config.php';
if(APP_DB_NAME!=='argws_upgrade_ci'||!preg_match('/^(127\.0\.0\.1|argws-crm-provision-db-[0-9]+)(:[0-9]+)?$/D',APP_DB_HOSTNAME))throw new RuntimeException('disposable_native_application_required');
require FCPATH.'tests/support/BbPdoDatabase.php';function db_prefix(){return 'tbl';}function &get_instance(){return $GLOBALS['CI'];}
$pdo=new PDO('mysql:host='.str_replace(':',';port=',APP_DB_HOSTNAME).';dbname='.APP_DB_NAME.';charset=utf8mb4',APP_DB_USERNAME,APP_DB_PASSWORD,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);$db=new BbPdoDatabase($pdo);$GLOBALS['CI']=(object)['db'=>$db];
require_once APPPATH.'services/NativeCompanyCustomFields.php';$fields=new NativeCompanyCustomFields($db,'tbl');$actor=(int)$db->query('SELECT master_staff_id FROM tblissuers_installation_identity WHERE id=1')->row()->master_staff_id;
$choices=['Literal "<choice>" & selected','Second choice'];$owners=[];
foreach(['a'=>'11222333000181','b'=>'52998224725']as$letter=>$tax){
    $issuer=(int)($db->query('SELECT id FROM tblissuers_registry WHERE tax_id=?',[$tax])->row_array()['id']??0);if(!$issuer)throw new RuntimeException('prior_native_browser_owner_required');$ids=[];$sources=[];
    foreach(['checkbox','multiselect','select','textarea','link']as$type){
        $definition=['fieldto'=>'items','name'=>'Original Sales '.$type.' '.$letter,'type'=>$type,'required'=>0,'active'=>1];if(in_array($type,['checkbox','multiselect','select'],true))$definition['options']=implode(', ',$choices);
        $row=$fields->saveDefinition($issuer,$actor,$definition,null,null,substr(hash('sha256','r8-sales-browser-fields:'.$issuer.':'.$type),0,32));$ids[$type]=$row['id'];$sources[$type]=NativeCompanyResourceId::decode('custom_field',$row['id'])['source_id'];
    }
    $owners[$letter]=['issuer'=>$issuer,'fields'=>$ids,'source_ids'=>$sources];
}
echo json_encode(['owners'=>$owners,'choices'=>$choices],JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE).PHP_EOL;
