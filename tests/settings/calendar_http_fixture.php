<?php
/** READ ONLY native HTTP inspection. Source records, settings, identities and grants are made through the official GUI. */
declare(strict_types=1);
set_exception_handler(static function(Throwable $e):void{fwrite(STDERR,get_class($e).': '.$e->getMessage().' at '.$e->getFile().':'.$e->getLine().PHP_EOL);exit(1);});
if(PHP_SAPI!=='cli'||getenv('ISSUERS_BROWSER_DB_NAME')!=='argws_upgrade_ci')exit(2);
define('FCPATH',dirname(__DIR__,2).'/');define('BASEPATH',FCPATH.'system/');define('APPPATH',FCPATH.'application/');
require APPPATH.'config/app-config.php';require_once APPPATH.'services/NativeCompanyCalendarPreferences.php';
if(APP_DB_NAME!=='argws_upgrade_ci'||!preg_match('/^(127\.0\.0\.1|argws-crm-provision-db-[0-9]+)(:[0-9]+)?$/D',APP_DB_HOSTNAME))exit(2);
$pdo=new PDO('mysql:host='.str_replace(':',';port=',APP_DB_HOSTNAME).';dbname='.APP_DB_NAME.';charset=utf8mb4',APP_DB_USERNAME,APP_DB_PASSWORD,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$pdo->exec('SET SESSION TRANSACTION READ ONLY');$pdo->beginTransaction();register_shutdown_function(static function()use($pdo):void{if($pdo->inTransaction())$pdo->rollBack();});
$config=[];require APPPATH.'config/migration.php';$target=$config['migration_version']??null;$version=(int)$pdo->query('SELECT version FROM tblmigrations')->fetchColumn();
if(!is_int($target)||$version!==$target||$target<445)throw new RuntimeException('Exact trusted current native migration required');
function calRows(PDO $pdo,string $sql,array $args=[]):array{$query=$pdo->prepare($sql);$query->execute($args);return$query->fetchAll(PDO::FETCH_ASSOC);}
$identity=$pdo->query('SELECT master_staff_id,primary_issuer_id FROM tblissuers_installation_identity WHERE id=1')->fetch(PDO::FETCH_ASSOC);
$owners=['a'=>(int)$pdo->query("SELECT id FROM tblissuers_registry WHERE tax_id='11222333000181'")->fetchColumn(),'b'=>(int)$pdo->query("SELECT id FROM tblissuers_registry WHERE tax_id='52998224725'")->fetchColumn(),'primary'=>(int)$identity['primary_issuer_id']];
if(min($owners)<1||count(array_unique($owners))!==3)throw new RuntimeException('Three native companies required');
$out=['owners'=>$owners,'actor'=>(int)$identity['master_staff_id'],'identities'=>[],'native_marker'=>$version,'expected_marker'=>$target,'read_only'=>true];
foreach($owners as$issuer)$out['identities'][$issuer]=calRows($pdo,'SELECT id,legal_name,tax_id,identity_status FROM tblissuers_registry WHERE id=?',[$issuer])[0];
$mode=$argv[1]??'inspect';
if($mode==='inspect'){echo json_encode($out,JSON_THROW_ON_ERROR);exit;}
if($mode==='snapshot'){
    $calendarDefaults=NativeCompanyCalendarPreferences::defaults();$calendarKeys=array_fill_keys(array_keys($calendarDefaults),true);
    $globalOptions=[];foreach(calRows($pdo,'SELECT name,value FROM tbloptions ORDER BY name')as$row)$globalOptions[$row['name']]=$row['value'];
    $out=['preferences'=>[],'immutable'=>[],'registry_hashes'=>[],'other_settings'=>[],'audit_hash'=>hash('sha256',json_encode($pdo->query('SELECT * FROM tblissuers_audit ORDER BY id')->fetchAll(PDO::FETCH_ASSOC),JSON_THROW_ON_ERROR))];
    foreach($owners as$issuer){
        $json=calRows($pdo,'SELECT settings_json FROM tblissuers_registry WHERE id=?',[$issuer])[0]['settings_json'];$doc=json_decode($json,true,32,JSON_THROW_ON_ERROR);$node=$doc['company_calendar']??null;$private=$doc['company_options']??[];$values=[];
        foreach($calendarDefaults as$key=>$default){
            if($issuer===(int)$identity['primary_issuer_id'])$values[$key]=(string)($globalOptions[$key]??'');
            elseif(is_array($node)&&is_array($node['values']??null)&&array_key_exists($key,$node['values']))$values[$key]=(string)$node['values'][$key];
            elseif(is_array($private)&&array_key_exists($key,$private))$values[$key]=(string)$private[$key];
            else$values[$key]=$default;
        }
        $out['preferences'][$issuer]=['values'=>$values,'calendar_revision'=>(int)($node['revision']??0)];$out['registry_hashes'][$issuer]=hash('sha256',$json);
        unset($doc['company_calendar']);if(is_array($doc['company_options']??null)){foreach(array_keys($calendarKeys)as$key)unset($doc['company_options'][$key]);if($doc['company_options']===[])unset($doc['company_options']);}
        $out['other_settings'][$issuer]=hash('sha256',json_encode($doc,JSON_THROW_ON_ERROR));
    }
    $otherOptions=array_diff_key($globalOptions,$calendarKeys);$out['immutable']['options']=hash('sha256',json_encode($otherOptions,JSON_THROW_ON_ERROR));
    foreach(['invoices','itemable','item_tax','issuers_invoice_documents','invoicepaymentrecords','credits','company_financial_events','company_payment_receipts','issuers_sequences','company_sales_documents','company_sales_revisions','company_projects','company_tasks','company_standalone_tasks','company_contracts']as$table){
        $query=$pdo->prepare('SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?');$query->execute(['tbl'.$table]);
        if($query->fetchColumn())$out['immutable'][$table]=hash('sha256',json_encode($pdo->query('SELECT * FROM tbl'.$table.' ORDER BY 1')->fetchAll(PDO::FETCH_ASSOC),JSON_THROW_ON_ERROR));
    }
    echo json_encode($out,JSON_THROW_ON_ERROR);exit;
}
if($mode==='verify'){
    $rows=json_decode($argv[2]??'',true,32,JSON_THROW_ON_ERROR);$tables=['invoices'=>'invoices','estimates'=>'company_sales_documents','proposals'=>'company_sales_documents','projects'=>'company_projects','project_tasks'=>'company_tasks','standalone_tasks'=>'company_standalone_tasks','contracts'=>'company_contracts'];
    if(!is_array($rows)||count($rows)!==3)throw new RuntimeException('GUI proof requires three owners');
    foreach($owners as$issuer){$own=$rows[$issuer]??null;if(!is_array($own)||array_diff(array_keys($own),array_keys($tables))||count($own)!==7)throw new RuntimeException('Seven GUI native sources required');foreach($tables as$source=>$table){$id=$own[$source]??null;if(!is_int($id)||$id<1)throw new RuntimeException('Invalid GUI reference');$extra=in_array($source,['estimates','proposals'],true)?' AND kind=?':'';$args=[$id,$issuer];if($extra!=='')$args[]=$source;if(count(calRows($pdo,'SELECT id FROM tbl'.$table.' WHERE id=? AND issuer_id=?'.$extra,$args))!==1)throw new RuntimeException('Foreign or missing GUI reference');}}
    echo json_encode(['rows'=>$rows,'read_only'=>true],JSON_THROW_ON_ERROR);exit;
}
exit(2);
