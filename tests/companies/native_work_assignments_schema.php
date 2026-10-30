<?php
/** Real database replay/backfill proof; source rows and unrelated followers stay unchanged. */
require __DIR__.'/../issuers_core_support.php';
require_once APPPATH.'services/issuers/IssuerCore.php';
require_once APPPATH.'services/NativeCompanyWorkAssignmentsSchema.php';
require_once APPPATH.'services/NativeCompanyWorkAssignments.php';
require_once APPPATH.'services/issuers/IssuerRegistry.php';
register_shutdown_function(static function():void{foreach($GLOBALS['essentialFixtures']??[] as $f){foreach(array_keys(NativeCompanyWorkAssignmentsSchema::definitions()) as $t)$f->db->query('DROP TABLE IF EXISTS '.$f->prefix.$t);}essentialCleanup();});
$f=essentialFixture('1.0.2',1);$db=$f->db;$p=$f->prefix;
IssuerCore::reconcile($db,$p);IssuerCore::bindInstallationMaster($db,$p,7);
$db->query("ALTER TABLE {$p}staff ADD firstname VARCHAR(100) NOT NULL DEFAULT 'Synthetic'");
$db->query("ALTER TABLE {$p}staff ADD lastname VARCHAR(100) NOT NULL DEFAULT 'Staff'");
$db->query("INSERT INTO {$p}staff(staffid,active,admin) VALUES(51,1,0),(52,1,0)");
$db->query("INSERT INTO {$p}issuers_staff(issuer_id,staff_id,can_view) VALUES(1,51,1)");
foreach(['projects','tasks'] as $feature)$db->query("INSERT INTO {$p}issuers_resource_permissions(issuer_id,staff_id,feature,capability) VALUES(1,51,?,'view')",[$feature]);
$foreign=(new IssuerRegistry($db,$p))->saveIssuer(7,['legal_name'=>'Assignment B','tax_id'=>'52998224725']);
$db->query("INSERT INTO {$p}issuers_staff(issuer_id,staff_id,can_view) VALUES(?,52,1)",[$foreign]);
$db->query("INSERT INTO {$p}issuers_resource_permissions(issuer_id,staff_id,feature,capability) VALUES(?,52,'projects','view')",[$foreign]);
foreach(NativeCompanyWorkAssignmentsSchema::definitions() as $table=>$d){
    $parent=$d['parent'];$source=$d['source'];
    $db->query("CREATE TABLE {$p}{$parent}(id int unsigned NOT NULL,issuer_id int unsigned NOT NULL,{$source} int NOT NULL,PRIMARY KEY(id),UNIQUE(issuer_id,id))".($db->driver()==='sqlite'?'':' ENGINE=InnoDB'));
    $db->query("INSERT INTO {$p}{$parent}(id,issuer_id,{$source}) VALUES(1,1,7),(2,1,51)");
}
$before=[];foreach(NativeCompanyWorkAssignmentsSchema::definitions() as $d)$before[$d['parent']]=$db->query('SELECT * FROM '.$p.$d['parent'].' ORDER BY id')->result_array();
NativeCompanyWorkAssignmentsSchema::ensure($db,$p);
foreach(NativeCompanyWorkAssignmentsSchema::definitions() as $table=>$d){
    $rows=$db->query('SELECT * FROM '.$p.$table.' ORDER BY '.$d['resource'])->result_array();
    essentialCheck(count($rows)===2,'exact historical assignments copied '.$table);
    essentialCheck((int)$rows[0]['staff_id']===7&&(int)$rows[1]['staff_id']===51,'existing assignee identities preserved '.$table);
    essentialCheck($rows[0]['origin']==='historical_'.$d['source'],'source provenance explicit '.$table);
    $db->query('UPDATE '.$p.$table.' SET active=0,revision=2 WHERE issuer_id=1 AND '.$d['resource'].'=1 AND staff_id=7');
}
NativeCompanyWorkAssignmentsSchema::ensure($db,$p);
foreach(NativeCompanyWorkAssignmentsSchema::definitions() as $table=>$d){
    $rows=$db->query('SELECT * FROM '.$p.$table.' ORDER BY '.$d['resource'])->result_array();
    essentialCheck(count($rows)===2&&(int)$rows[0]['active']===0&&(int)$rows[0]['revision']===2,'replay cannot duplicate or reactivate '.$table);
    essentialCheck($db->query('SELECT * FROM '.$p.$d['parent'].' ORDER BY id')->result_array()===$before[$d['parent']],'source rows byte preserved '.$table);
}
$assignments=new NativeCompanyWorkAssignments($db,$p);$current=$assignments->read(1,7,'project',1);
$changed=$assignments->replace(1,7,'project',1,$current['revision'],[7,51]);
essentialCheck(array_map('intval',array_column($changed['members'],'staffid'))===[7,51],'explicit active member added without granting issuer ACL');
essentialThrows(fn()=>$assignments->replace(1,7,'project',1,$current['revision'],[7]),'native_assignment_stale','stale membership update rejected');
essentialThrows(fn()=>$assignments->replace(1,7,'project',1,$changed['revision'],[7,52]),'native_assignment_denied','staff belonging only to other issuer rejected');
$db->query("UPDATE {$p}staff SET active=0 WHERE staffid=51");
essentialThrows(fn()=>$assignments->replace(1,7,'project',1,$changed['revision'],[7,51]),'native_assignment_denied','inactive staff rejected under write lock');
$db->query("UPDATE {$p}staff SET active=1 WHERE staffid=51");
essentialCheck($assignments->read(1,7,'project',1)['revision']===$changed['revision'],'failed writes leave member revision untouched');
foreach(NativeCompanyWorkAssignmentsSchema::definitions() as $d)essentialCheck($db->query('SELECT * FROM '.$p.$d['parent'].' ORDER BY id')->result_array()===$before[$d['parent']],'membership changes never rewrite operational source');
$assignments->replace(1,7,'project',1,$changed['revision'],[7,51]);$firstReceipt=$db->query('SELECT metadata_json FROM '.$p.'issuers_audit ORDER BY id DESC LIMIT 1')->row_array();
$assignments->replace(1,7,'project',1,$changed['revision'],[7,51]);$secondReceipt=$db->query('SELECT metadata_json FROM '.$p.'issuers_audit ORDER BY id DESC LIMIT 1')->row_array();
essentialCheck(json_decode($firstReceipt['metadata_json'],true)['operation_nonce']!==json_decode($secondReceipt['metadata_json'],true)['operation_nonce'],'identical assignment actions have distinct unpredictable audit receipts');
if($db->driver()==='sqlite'){
 $db->query("CREATE TRIGGER {$p}deny_assignment_audit BEFORE INSERT ON {$p}issuers_audit WHEN NEW.event='native_project_members_updated' BEGIN SELECT RAISE(IGNORE); END");
 essentialThrows(fn()=>$assignments->replace(1,7,'project',1,$changed['revision'],[7]),'issuer_storage_failed','silent audit omission aborts membership mutation');
 essentialThrows(fn()=>$assignments->replace(1,7,'project',1,$changed['revision'],[7,51]),'issuer_storage_failed','identical previous receipt cannot authenticate ignored no-op audit');
 essentialCheck($assignments->read(1,7,'project',1)===$changed,'audit failure rolls back members and revisions');
 $db->query("DROP TRIGGER {$p}deny_assignment_audit");
}
echo "Native company assignment schema: {$GLOBALS['essentialChecks']} checks passed.\n";
