<?php
require __DIR__.'/../issuers_core_support.php';
require_once APPPATH.'services/NativeCompanyBlankTimerSchema.php';
register_shutdown_function(static function():void{foreach($GLOBALS['essentialFixtures']??[] as$f)$f->db->query('DROP TABLE IF EXISTS '.$f->prefix.'company_blank_task_timers');essentialCleanup();});
$f=essentialFixture('1.0.2',1);$db=$f->db;$p=$f->prefix;IssuerCore::reconcile($db,$p);
NativeCompanyBlankTimerSchema::ensure($db,$p);essentialCheck($db->table_exists($p.'company_blank_task_timers'),'blank timer table created');
$db->query('INSERT INTO '.$p.'company_blank_task_timers(issuer_id,actor_id,started_at,closed_at,state,revision,target_kind,target_id,target_timer_id,note) VALUES(1,7,?,NULL,?,1,NULL,NULL,NULL,?)',['2026-10-25 12:00:00','active','Synthetic note']);
$before=$db->query('SELECT * FROM '.$p.'company_blank_task_timers')->result_array();NativeCompanyBlankTimerSchema::ensure($db,$p);essentialCheck($before===$db->query('SELECT * FROM '.$p.'company_blank_task_timers')->result_array(),'schema replay preserves timer and note');
$db->query('ALTER TABLE '.$p.'company_blank_task_timers ADD unrecognized INT NULL');essentialThrows(fn()=>NativeCompanyBlankTimerSchema::assertValid($db,$p),'native_blank_timer_schema_invalid','unexpected schema fails closed');
echo "Native blank timer schema: {$GLOBALS['essentialChecks']} checks passed.\n";
