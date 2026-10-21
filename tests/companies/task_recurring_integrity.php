<?php
require __DIR__.'/task_recurring_fixture.php';
foreach(['project','standalone']as$kind){
    $source=$sources[$kind][$b];
    foreach(['company_task_schedules','company_task_schedule_requests','issuers_audit']as$table){
        $before=rrSnapshot();$attempt=$key('rr-create-fail-'.$kind.'-'.$table);$db->failOn='INSERT INTO '.$p.$table;
        try{rrThrows(fn()=>$rr->create($b,7,$kind,$source,5,rrCalendar(),$attempt),'issuer_storage_failed','creation fails atomically at '.$table.' '.$kind);}finally{$db->failOn=null;}
        rrCheck(rrSnapshot()===$before,'no partial schedule or receipt after create failure');$id=$rr->create($b,7,$kind,$source,5,rrCalendar(),$attempt);rrCheck($rr->create($b,7,$kind,$source,5,rrCalendar(),$attempt)===$id,'retry after failed create records one durable identity');
    }
    $id=$rr->create($b,7,$kind,$source,5,rrCalendar(),$key('rr-fault-plan-'.$kind));$native=$kind==='project'?'company_tasks':'company_standalone_tasks';$priority=$kind==='project'?'company_task_priorities':'company_standalone_priorities';
    foreach(['INSERT INTO '.$p.$native,'INSERT INTO '.$p.$priority,'INSERT INTO '.$p.'issuers_audit','INSERT INTO '.$p.'company_task_schedule_runs','UPDATE '.$p.'company_task_schedules']as$sql){
        $before=rrSnapshot();$db->failOn=$sql;try{rrThrows(fn()=>$rr->run($b,7,$id,'2026-11-01 00:00:00',0),'issuer_storage_failed','occurrence fails at exact native/ledger/calendar writer '.$kind);}finally{$db->failOn=null;}rrCheck(rrSnapshot()===$before,'failure rolls back child priority receipt audit and schedule');
    }
    foreach(['receipt_key','calendar_readback','late_audit','late_child','late_priority','late_source']as$mode){
        $trigger=$p.'rr_fault';
        $ddl=match($mode){
            'receipt_key'=>"BEFORE INSERT ON {$p}company_task_schedule_runs FOR EACH ROW SET NEW.request_key=REPEAT('e',64)",
            'calendar_readback'=>"BEFORE UPDATE ON {$p}company_task_schedules FOR EACH ROW SET NEW.completed_cycles=OLD.completed_cycles",
            'late_audit'=>"BEFORE INSERT ON {$p}issuers_audit FOR EACH ROW BEGIN IF NEW.event='company_task_schedule_run' THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='synthetic late recurrence audit'; END IF; END",
            'late_child'=>"AFTER INSERT ON {$p}issuers_audit FOR EACH ROW BEGIN IF NEW.event='company_task_schedule_run' THEN UPDATE {$p}{$native} SET name='mutated after child confirmation' WHERE id=(SELECT task_id FROM {$p}company_task_schedule_runs WHERE issuer_id=NEW.issuer_id AND schedule_id=NEW.subject_id AND cycle=0); END IF; END",
            'late_priority'=>"AFTER INSERT ON {$p}issuers_audit FOR EACH ROW BEGIN IF NEW.event='company_task_schedule_run' THEN UPDATE {$p}{$priority} SET priority=4 WHERE task_id=(SELECT task_id FROM {$p}company_task_schedule_runs WHERE issuer_id=NEW.issuer_id AND schedule_id=NEW.subject_id AND cycle=0) AND issuer_id=NEW.issuer_id; END IF; END",
            'late_source'=>"AFTER INSERT ON {$p}issuers_audit FOR EACH ROW BEGIN IF NEW.event='company_task_schedule_run' THEN UPDATE {$p}{$native} SET description='mutated source' WHERE id={$source} AND issuer_id=NEW.issuer_id; END IF; END",
        };
        $db->query('CREATE TRIGGER '.$trigger.' '.$ddl);$before=rrSnapshot();
        try{rrThrows(fn()=>$rr->run($b,7,$id,'2026-11-01 00:00:00',0),$mode==='late_audit'?'issuer_storage_failed':'issuer_task_recurring_unconfirmed','exact readback/full late frame catches '.$mode.' '.$kind);}finally{$db->query('DROP TRIGGER '.$trigger);}
        rrCheck(rrSnapshot()===$before,'trigger-induced late mutation rolls back every table');
    }
    $good=$rr->run($b,7,$id,'2026-11-01 00:00:00',0);rrCheck($rr->run($b,7,$id,'2026-11-01 00:00:00',0)===$good,'recovery after all fault points commits once');
    $stored=$db->query('SELECT * FROM '.$p.'company_task_schedules WHERE id=?',[$id])->row_array();
    foreach(['state'=>'paused','next_date'=>'2026-12-01','completed_cycles'=>0,'contract_hash'=>str_repeat('0',64)]as$field=>$value){
        $db->query('UPDATE '.$p.'company_task_schedules SET '.$field.'=? WHERE id=?',[$value,$id]);try{rrThrows(fn()=>$rr->get($b,7,$kind,$id),'issuer_task_recurring_unconfirmed','persisted calendar/contract/state corruption rejected '.$field);}finally{$db->query('UPDATE '.$p.'company_task_schedules SET '.$field.'=? WHERE id=?',[$stored[$field],$id]);}
    }
    $run=$db->query('SELECT * FROM '.$p.'company_task_schedule_runs WHERE schedule_id=? AND cycle=0',[$id])->row_array();$birth=json_decode($run['birth_json'],true,32,JSON_THROW_ON_ERROR);$birth['native']['issuer_id']=(string)$a;$json=json_encode($birth,JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
    $db->query('UPDATE '.$p.'company_task_schedule_runs SET birth_json=?,birth_hash=? WHERE schedule_id=? AND cycle=0',[$json,hash('sha256',$json),$id]);try{rrThrows(fn()=>$rr->get($b,7,$kind,$id),'issuer_task_recurring_unconfirmed','self-consistent hash cannot replace immutable origin binding');}finally{$db->query('UPDATE '.$p.'company_task_schedule_runs SET birth_json=?,birth_hash=? WHERE schedule_id=? AND cycle=0',[$run['birth_json'],$run['birth_hash'],$id]);}
    $calls=0;$late=new CompanyTaskRecurring($db,$p,function()use(&$calls,$db,$p,$id){if(++$calls===2)$db->query('UPDATE '.$p.'company_task_schedule_runs SET revision=999 WHERE schedule_id=? AND cycle=0',[$id]);});$before=rrSnapshot();rrThrows(fn()=>$late->run($b,7,$id,'2026-11-03 00:00:00',1),'issuer_task_recurring_unconfirmed','late guard cannot rewrite an earlier durable occurrence');rrCheck(rrSnapshot()===$before,'late history mutation rolls back new child and corruption');
}
$db->trans_begin();try{rrThrows(fn()=>$rr->create($b,7,'standalone',$sources['standalone'][$b],5,rrCalendar(),$key('rr-outer')),'issuer_outer_transaction_not_allowed','service never adopts caller transaction');}finally{$db->trans_rollback();}
rrThrows(fn()=>$standalone->createRecurringTaskInOwnedTransaction($b,7,['assignee_id'=>7,'name'=>'Forbidden compositor','description'=>'','due_date'=>'2026-11-01','status'=>'todo','checklist'=>[],'priority'=>3]),'issuer_owned_transaction_required','compositor cannot be called outside full reviewed transaction');
$db->query('ALTER TABLE '.$p.'company_task_schedule_cursor ENGINE=MyISAM');try{rrThrows(fn()=>$rr->page($b,7,'standalone'),'issuer_task_recurring_schema_invalid','nontransactional participant closes recurrence before mutation');}finally{$db->query('ALTER TABLE '.$p.'company_task_schedule_cursor ENGINE=InnoDB');}
$db->query('ALTER TABLE '.$p.'company_task_schedules MODIFY contract_hash varchar(64) CHARACTER SET ascii COLLATE ascii_general_ci NOT NULL');try{rrThrows(fn()=>$rr->page($b,7,'standalone'),'issuer_task_recurring_schema_invalid','contract hash collation cannot silently change');}finally{$db->query('ALTER TABLE '.$p.'company_task_schedules MODIFY contract_hash varchar(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL');}
rrCheck($financialBefore===$db->query('SELECT * FROM '.$p.'invoices ORDER BY id')->result_array()&&hooks()->trace===[],'faults preserve financial rows and external hook silence');
echo 'Task recurrence rebuilt integrity: '.($GLOBALS['essentialChecks']-$recurringStart).' NEW assertions; '.$GLOBALS['essentialChecks'].' cumulative; '.PHP_VERSION.'.'.PHP_EOL;
