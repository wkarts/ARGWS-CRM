<?php
/** Final-write SQL witnesses. Fixtures only; the original isolation suite remains intact. */
require __DIR__.'/../support/TestPdo.php';require __DIR__.'/native_settings.php';
$fencingStart=$GLOBALS['essentialChecks'];
$observe=getenv('SETTINGS_FENCE_OBSERVE_BASELINE')==='1';
$observed=[];
$db->query("UPDATE {$p}issuers_registry SET active=1 WHERE id=82");
$db->query("ALTER TABLE {$p}options ADD COLUMN autoload INT NOT NULL DEFAULT 1");
$db->query("ALTER TABLE {$p}options ADD COLUMN metadata_note VARCHAR(40) NOT NULL DEFAULT 'preserved'");
$store->mutate($primary,7,'update','fencing_existing','old',1);
$store->mutate($primary,7,'update','pdf_format_invoice','A4-PORTRAIT',1);

function settingsFenceSnapshot($db,string $p):array
{
    $out=[];
    foreach(['issuers_registry','issuers_installation_identity','issuers_staff','issuers_resource_permissions','staff','options','issuers_audit'] as $table){
        $rows=$db->query('SELECT * FROM '.$p.$table)->result_array();
        usort($rows,static fn($a,$b)=>strcmp(json_encode($a),json_encode($b)));
        $out[$table]=$rows;
    }
    return $out;
}
function settingsFenceTrigger($db,string $p,string $event,string $sql):void
{
    if($db->driver()==='mysql'){
        $db->query('SET @settings_fence_hit=0');
        $db->query("CREATE TRIGGER {$p}settings_fence AFTER INSERT ON {$p}issuers_audit FOR EACH ROW BEGIN IF NEW.event='$event' THEN SET @settings_fence_hit=COALESCE(@settings_fence_hit,0)+1; $sql; END IF; END");
    }else{
        $GLOBALS['settingsFenceHit']=0;
        TestPdo::registerSqliteFunction($db->pdo,'settings_fence_hit',static function(){return ++$GLOBALS['settingsFenceHit'];},0);
        $db->query("CREATE TRIGGER {$p}settings_fence AFTER INSERT ON {$p}issuers_audit WHEN NEW.event='$event' BEGIN SELECT settings_fence_hit(); $sql; END");
    }
}
function settingsFenceHit($db):int
{
    return $db->driver()==='mysql'?(int)$db->query('SELECT @settings_fence_hit n')->row()->n:$GLOBALS['settingsFenceHit'];
}
$cases=[
    ['private option','company_settings_update',"UPDATE {$p}issuers_registry SET settings_json='{}' WHERE id=41",fn()=>$store->mutate(41,12,'update','fencing_private','own value'),'company_settings_write_unconfirmed'],
    ['principal option','company_settings_update',"UPDATE {$p}options SET value='unexpected' WHERE name='fencing_existing'",fn()=>$store->mutate($primary,7,'update','fencing_existing','new'),'company_settings_write_unconfirmed'],
    ['private batch','company_settings_batch_updated',"UPDATE {$p}issuers_registry SET settings_json='{}' WHERE id=41",fn()=>$store->updateBatch(41,12,['fencing_batch'=>'new']),'company_settings_write_unconfirmed'],
    ['principal PDF batch','company_settings_batch_updated',"UPDATE {$p}options SET value='A3-PORTRAIT' WHERE name='pdf_format_invoice'",fn()=>$store->updateDocumentPdfPreferences($primary,7,['pdf_format_invoice'=>'LETTER-LANDSCAPE']),'company_settings_write_unconfirmed'],
    ['identity name','company_identity_updated',"UPDATE {$p}issuers_registry SET legal_name='Unexpected identity' WHERE id=41",fn()=>$identity->save(41,12,['legal_name'=>'Fenced identity']),'company_settings_write_unconfirmed'],
    ['private profile','company_identity_updated',"UPDATE {$p}issuers_registry SET settings_json='{}' WHERE id=41",fn()=>$identity->save(41,12,['profile'=>['invoice_company_city'=>'Fenced city']]),'company_settings_write_unconfirmed'],
    ['principal profile','company_identity_updated',"UPDATE {$p}options SET value='Unexpected city' WHERE name='invoice_company_city'",fn()=>$identity->save($primary,7,['profile'=>['invoice_company_city'=>'Fenced principal city']]),'company_settings_write_unconfirmed'],
    ['private grant','company_settings_update',"DELETE FROM {$p}issuers_resource_permissions WHERE issuer_id=41 AND staff_id=12 AND feature='settings' AND capability='edit'",fn()=>$store->mutate(41,12,'update','fencing_acl','new'),'company_settings_access_denied'],
    ['batch membership','company_settings_batch_updated',"UPDATE {$p}issuers_staff SET can_view=0 WHERE issuer_id=41 AND staff_id=12",fn()=>$store->updateBatch(41,12,['fencing_acl_batch'=>'new']),'company_settings_access_denied'],
    ['identity grant','company_identity_updated',"DELETE FROM {$p}issuers_resource_permissions WHERE issuer_id=41 AND staff_id=12 AND feature='settings' AND capability='edit'",fn()=>$identity->save(41,12,['legal_name'=>'Fenced identity ACL']),'company_settings_access_denied'],
    ['unrelated principal metadata','company_settings_update',"UPDATE {$p}options SET metadata_note='unexpected' WHERE name='fencing_existing'",fn()=>$store->mutate($primary,7,'update','fencing_other','new'),'company_settings_write_unconfirmed'],
    ['principal autoload after audit','company_settings_update',"UPDATE {$p}options SET autoload=0 WHERE name='fencing_existing'",fn()=>$store->mutate($primary,7,'update','fencing_existing','new with autoload',1),'company_settings_write_unconfirmed'],
];
foreach($cases as[$label,$event,$sql,$operation,$expected]){
    $before=settingsFenceSnapshot($db,$p);
    settingsFenceTrigger($db,$p,$event,$sql);
    $error=null;$result=null;
    try{$result=$operation();}catch(Throwable $e){$error=$e->getMessage();}
    $hit=settingsFenceHit($db);
    $db->query('DROP TRIGGER '.$p.'settings_fence');
    if($observe){
        $observed[]=['case'=>$label,'sql_witness'=>$hit,'returned_success'=>$error===null,'error'=>$error];
        // Baseline observation intentionally runs one case per process: it must not repair product data.
        break;
    }
    essentialCheck($hit===1&&$error===$expected,'final SQL witness rejects '.$label.' ('.var_export($error,true).')');
    essentialCheck(settingsFenceSnapshot($db,$p)===$before,'complete rollback after '.$label);
    essentialCheck($operation()!==false,'retry succeeds after '.$label);
}
if($observe){echo json_encode(['baseline_observation'=>$observed],JSON_UNESCAPED_SLASHES).PHP_EOL;exit;}
// A same-value UPDATE with a requested autoload change must confirm the metadata too.
$store->mutate($primary,7,'update','fencing_existing','same',1);
$before=settingsFenceSnapshot($db,$p);
if($db->driver()==='mysql'){
    $db->query('SET @settings_fence_hit=0');
    $db->query("CREATE TRIGGER {$p}settings_fence BEFORE UPDATE ON {$p}options FOR EACH ROW BEGIN IF NEW.name='fencing_existing' THEN SET @settings_fence_hit=COALESCE(@settings_fence_hit,0)+1; SET NEW.autoload=OLD.autoload; END IF; END");
}else{
    $GLOBALS['settingsFenceHit']=0;
    TestPdo::registerSqliteFunction($db->pdo,'settings_fence_hit',static function(){return ++$GLOBALS['settingsFenceHit'];},0);
    $db->query("CREATE TRIGGER {$p}settings_fence BEFORE UPDATE ON {$p}options WHEN NEW.name='fencing_existing' BEGIN SELECT settings_fence_hit(); SELECT RAISE(IGNORE); END");
}
$error=null;try{$store->mutate($primary,7,'update','fencing_existing','same',0);}catch(Throwable $e){$error=$e->getMessage();}
$hit=settingsFenceHit($db);$db->query('DROP TRIGGER '.$p.'settings_fence');
essentialCheck($hit===1&&$error==='company_settings_write_unconfirmed','ignored same-value autoload update is not success');
essentialCheck(settingsFenceSnapshot($db,$p)===$before,'ignored autoload rolls back option and audit');
essentialCheck($store->mutate($primary,7,'update','fencing_existing','same',0),'autoload retry succeeds');
$row=$db->query("SELECT * FROM {$p}options WHERE name='fencing_existing'")->row_array();
essentialCheck((int)$row['autoload']===0&&$row['metadata_note']==='preserved','autoload changes alone and preserves unrelated metadata');
echo 'Settings final-write fencing: '.($GLOBALS['essentialChecks']-$fencingStart).' added / '.$GLOBALS['essentialChecks'].' cumulative ('.$db->driver().').'.PHP_EOL;
