<?php
require __DIR__.'/native_blank_timer_assignment.php';
$standalone=new CompanyStandaloneTasks($db,$p);$projects=new CompanyTasks($db,$p);
$before=$db->query('SELECT * FROM '.$p.'company_standalone_tasks WHERE id=?',[$task])->row_array();
foreach(['awaiting_feedback','testing']as$state){
 $db->query('UPDATE '.$p.'company_standalone_tasks SET status=? WHERE id=?',[$state,$task]);
 $candidate=$standalone->nativeBlankTimerCandidate($a,7,$task);
 essentialCheck($candidate['task']['status']===$state&&$candidate['assignment']['basis']==='explicit_membership','picker uses exact native noncompleted workflow, not an approximation');
}
$db->query('UPDATE '.$p.'company_standalone_tasks SET status=? WHERE id=?',[$before['status'],$task]);
$db->query('DELETE FROM '.$p.'company_standalone_assignees WHERE issuer_id=? AND task_id=? AND staff_id=7',[$a,$task]);
$candidate=$standalone->nativeBlankTimerCandidate($a,7,$task);
essentialCheck($candidate['assignment']===['basis'=>'canonical_historical_assignee','staff_id'=>7],'historical explicit canonical assignee is not silently lost or backfilled');
essentialCheck($db->query('SELECT * FROM '.$p.'company_standalone_assignees WHERE issuer_id=? AND task_id=? AND staff_id=7',[$a,$task])->result_array()===[],'read candidate never adopts historical assignment');
$db->query('INSERT INTO '.$p.'company_standalone_assignees(issuer_id,task_id,staff_id,active,revision,origin) VALUES(?,?,7,0,1,?)',[$a,$task,'explicit']);
essentialThrows(fn()=>$standalone->nativeBlankTimerCandidate($a,7,$task),'native_timer_assignee_required','explicit revoked membership overrides historical assignee');
$db->query('UPDATE '.$p.'company_standalone_assignees SET active=1 WHERE issuer_id=? AND task_id=? AND staff_id=7',[$a,$task]);
essentialThrows(fn()=>$standalone->nativeBlankTimerTargetInOwned($a,7,$task),'issuer_owned_transaction_required','internal eligibility rejects caller without owned authority');
$next=$service->start($a,7,$off,hash('sha256','candidate-testing-import'));
$db->query("UPDATE {$p}company_standalone_tasks SET status='testing' WHERE id=?",[$task]);
$service->assign($a,7,$next,1,'company_standalone_task',$task,1,'Testing is a distinct native workflow',hash('sha256','testing-import'));
essentialCheck($db->query('SELECT state FROM '.$p.'company_blank_task_timers WHERE id=?',[$next])->row_array()['state']==='assigned','same writer accepts testing without relabeling workflow');
$db->query("UPDATE {$p}company_standalone_tasks SET status='done' WHERE id=?",[$task]);
essentialCheck($service->assign($a,7,$next,1,'company_standalone_task',$task,1,'Testing is a distinct native workflow',hash('sha256','testing-import'))===$next,'Durable replay confirms past transfer after task completion, without opening a new assignment');
$db->query('UPDATE '.$p.'company_standalone_tasks SET status=? WHERE id=?',[$before['status'],$task]);
$next=$service->start($a,7,$off,hash('sha256','candidate-audit-revoke'));
if($db->driver()==='sqlite'){
 $snapshot=$db->query('SELECT * FROM '.$p.'company_standalone_timers ORDER BY id')->result_array();
 $db->query('CREATE TRIGGER '.$p.'revoke_timer_target AFTER INSERT ON '.$p."issuers_audit WHEN NEW.event='company_native_blank_assigned' BEGIN UPDATE ".$p.'company_standalone_assignees SET active=0 WHERE issuer_id=NEW.issuer_id AND task_id='.$task.' AND staff_id=7; END');
 essentialThrows(fn()=>$service->assign($a,7,$next,1,'company_standalone_task',$task,1,'',hash('sha256','late-target-revocation')),'native_timer_assignee_required','audit-time target membership revocation rejects entire transfer');
 essentialCheck($snapshot===$db->query('SELECT * FROM '.$p.'company_standalone_timers ORDER BY id')->result_array(),'late authority failure rolls back target receipt');
 essentialCheck((int)$db->query('SELECT active FROM '.$p.'company_standalone_assignees WHERE issuer_id=? AND task_id=? AND staff_id=7',[$a,$task])->row_array()['active']===1,'late authority failure rolls back membership mutation too');
 $db->query('DROP TRIGGER '.$p.'revoke_timer_target');
}
echo "Native blank timer candidates: {$GLOBALS['essentialChecks']} cumulative checks passed.\n";
