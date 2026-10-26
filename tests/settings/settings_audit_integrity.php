<?php
/** A successful INSERT with a changed audit is not a confirmed settings change. */
require __DIR__.'/../support/TestPdo.php';require __DIR__.'/company_settings.php';
$auditStart=$GLOBALS['essentialChecks'];$observe=getenv('SETTINGS_FENCE_OBSERVE_BASELINE')==='1';
function settingsAuditSnapshot($db,string $p):array
{
    $state=[];foreach(['issuers_registry','issuers_audit','options'] as $name){$rows=$db->query('SELECT * FROM '.$p.$name)->result_array();usort($rows,static fn($a,$b)=>strcmp(json_encode($a),json_encode($b)));$state[$name]=$rows;}return $state;
}
foreach([
    ['private setting','company_settings_update',fn()=>$store->mutate(41,12,'update','audit_fence','own value')],
    ['canonical identity','company_identity_updated',fn()=>$identity->save(41,12,['legal_name'=>'Audited identity'])],
] as [$label,$event,$operation]){
    $before=settingsAuditSnapshot($db,$p);
    if($db->driver()==='mysql'){
        $db->query('SET @settings_audit_hit=0');
        $db->query("CREATE TRIGGER {$p}settings_audit BEFORE INSERT ON {$p}issuers_audit FOR EACH ROW BEGIN IF NEW.event='$event' THEN SET @settings_audit_hit=COALESCE(@settings_audit_hit,0)+1; SET NEW.event='company_audit_substituted'; END IF; END");
    }else{
        $GLOBALS['settingsAuditHit']=0;TestPdo::registerSqliteFunction($db->pdo,'settings_audit_hit',static function(){return ++$GLOBALS['settingsAuditHit'];},0);
        $db->query("CREATE TRIGGER {$p}settings_audit AFTER INSERT ON {$p}issuers_audit WHEN NEW.event='$event' BEGIN SELECT settings_audit_hit(); UPDATE {$p}issuers_audit SET event='company_audit_substituted' WHERE id=NEW.id; END");
    }
    $error=null;try{$operation();}catch(Throwable $e){$error=$e->getMessage();}
    $hit=$db->driver()==='mysql'?(int)$db->query('SELECT @settings_audit_hit n')->row()->n:$GLOBALS['settingsAuditHit'];
    $db->query('DROP TRIGGER '.$p.'settings_audit');
    if($observe){echo json_encode(['baseline_case'=>$label,'sql_witness'=>$hit,'returned_success'=>$error===null,'error'=>$error]).PHP_EOL;exit;}
    essentialCheck($hit===1&&$error==='company_settings_write_unconfirmed','actual changed audit is rejected for '.$label);
    essentialCheck(settingsAuditSnapshot($db,$p)===$before,'changed audit rolls back settings and history for '.$label);
    essentialCheck($operation()!==false,'retry records the correct audit for '.$label);
}
echo 'Settings audit integrity: '.($GLOBALS['essentialChecks']-$auditStart).' added / '.$GLOBALS['essentialChecks'].' cumulative ('.$db->driver().').'.PHP_EOL;
