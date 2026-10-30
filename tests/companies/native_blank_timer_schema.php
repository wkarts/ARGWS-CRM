<?php
require __DIR__.'/../issuers_core_support.php';
require_once APPPATH.'services/NativeCompanyBlankTimerSchema.php';
register_shutdown_function(static function():void{foreach($GLOBALS['essentialFixtures']??[] as$f)$f->db->query('DROP TABLE IF EXISTS '.$f->prefix.'company_blank_task_timers');essentialCleanup();});
$f=essentialFixture('1.0.2',1);$db=$f->db;$p=$f->prefix;IssuerCore::reconcile($db,$p);
NativeCompanyBlankTimerSchema::ensure($db,$p);essentialCheck($db->table_exists($p.'company_blank_task_timers'),'blank timer table created');
$db->query('INSERT INTO '.$p.'company_blank_task_timers(issuer_id,actor_id,started_at,closed_at,state,revision,target_kind,target_id,target_timer_id,note) VALUES(1,7,?,NULL,?,1,NULL,NULL,NULL,?)',['2026-10-25 12:00:00','active','Synthetic note']);
$before=$db->query('SELECT * FROM '.$p.'company_blank_task_timers')->result_array();NativeCompanyBlankTimerSchema::ensure($db,$p);essentialCheck($before===$db->query('SELECT * FROM '.$p.'company_blank_task_timers')->result_array(),'schema replay preserves timer and note');
if($db->driver()!=='sqlite'){
    $db->query('ALTER TABLE '.$p.'company_blank_task_timers MODIFY target_kind varchar(16) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL');
    essentialThrows(fn()=>NativeCompanyBlankTimerSchema::assertValid($db,$p),'native_blank_timer_schema_invalid','old capacity is rejected at runtime until migration');
    NativeCompanyBlankTimerSchema::ensure($db,$p);
    essentialCheck($before===$db->query('SELECT * FROM '.$p.'company_blank_task_timers')->result_array(),'upgrade widens only provenance capacity and preserves every stored value');
}
$db->query('UPDATE '.$p.'company_blank_task_timers SET state=?,closed_at=?,target_kind=?,target_id=31,target_timer_id=41 WHERE id=1',['assigned','2026-10-25 12:01:00','company_standalone_task']);
essentialCheck($db->query('SELECT target_kind FROM '.$p.'company_blank_task_timers WHERE id=1')->row_array()['target_kind']==='company_standalone_task','standalone source lineage is stored without truncation');
$db->query('ALTER TABLE '.$p.'company_blank_task_timers ADD unrecognized INT NULL');essentialThrows(fn()=>NativeCompanyBlankTimerSchema::assertValid($db,$p),'native_blank_timer_schema_invalid','unexpected schema fails closed');
echo "Native blank timer schema: {$GLOBALS['essentialChecks']} checks passed.\n";
