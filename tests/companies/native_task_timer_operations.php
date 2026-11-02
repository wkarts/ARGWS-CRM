<?php
require __DIR__.'/native_timer_rate_snapshot.php';
$standalone=new CompanyStandaloneTasks($db,$p);$policy=(new NativeCompanyTimerPolicy($db,$p))->read($a,7);$policyHash=NativeCompanyBlankTimers::policyHash($policy);
$row=$db->query('SELECT * FROM '.$p.'company_standalone_tasks WHERE id=?',[$task])->row_array();
$standalone->nativeTimer($a,7,$task,(int)$row['revision'],'start',null,0,'Native start',$policyHash,hash('sha256','native-start-one'));
$timerRow=$db->query('SELECT * FROM '.$p.'company_standalone_timers WHERE issuer_id=? AND task_id=? AND actor_id=7 AND stopped_at IS NULL',[$a,$task])->row_array();
essentialCheck($timerRow!==[]&&$details->forOwnTimesheet($a,7,'company_standalone_timer',(int)$timerRow['id'])['hourly_rate']==='99.00','Native task start captures current company rate');
$standalone->nativeTimer($a,7,$task,(int)$row['revision'],'start',null,0,'Native start',$policyHash,hash('sha256','native-start-one'));
essentialCheck(count($db->query('SELECT id FROM '.$p.'company_standalone_timers WHERE issuer_id=? AND task_id=? AND actor_id=7 AND stopped_at IS NULL',[$a,$task])->result_array())===1,'Start replay is not a second timer');
$standalone->nativeTimer($a,7,$task,(int)$row['revision']+1,'stop',(int)$timerRow['id'],1,'Finished note',$policyHash,hash('sha256','native-stop-one'));
$stopped=$db->query('SELECT * FROM '.$p.'company_standalone_timers WHERE id=?',[$timerRow['id']])->row_array();essentialCheck($stopped['stopped_at']!==null&&$details->forOwnTimesheet($a,7,'company_standalone_timer',(int)$timerRow['id'])['note']==='Finished note','Exact timer stop persists CAS note and canonical duration');
echo 'Native timer operations: '.$GLOBALS['essentialChecks']." cumulative checks passed.\n";
$makeTask=fn($name)=>$standalone->create($a,7,['assignee_id'=>7,'name'=>$name,'description'=>'','due_date'=>'2026-10-30','status'=>'todo','checklist'=>[]],hash('sha256',$name));
$two=$makeTask('Native multi two');$three=$makeTask('Native multi three');$four=$makeTask('Native multi four');
$standalone->nativeTimer($a,7,$two,1,'start',null,0,'Two',$policyHash,hash('sha256','multi-two'));
$standalone->nativeTimer($a,7,$three,1,'start',null,0,'Three',$policyHash,hash('sha256','multi-three'));
essentialCheck(count($db->query('SELECT id FROM '.$p.'company_standalone_timers WHERE issuer_id=? AND actor_id=7 AND stopped_at IS NULL',[$a])->result_array())===2,'Native auto-stop OFF preserves two distinct assigned timers');
(new CompanyTaskTimerStopPreferences($db,$p))->save($a,7,2,[CompanyTaskTimerStopPreferences::AUTO_STOP=>'1']);$onHash=NativeCompanyBlankTimers::policyHash((new NativeCompanyTimerPolicy($db,$p))->read($a,7));
$standalone->nativeTimer($a,7,$four,1,'start',null,0,'Four',$onHash,hash('sha256','multi-four'));
$remaining=$db->query('SELECT task_id FROM '.$p.'company_standalone_timers WHERE issuer_id=? AND actor_id=7 AND stopped_at IS NULL',[$a])->result_array();essentialCheck(count($remaining)===1&&(int)$remaining[0]['task_id']===$four,'Native auto-stop ON closes both prior owned timers atomically');
foreach([$two,$three]as$t){$stoppedRow=$db->query('SELECT * FROM '.$p.'company_standalone_timers WHERE issuer_id=? AND task_id=? AND actor_id=7',[$a,$t])->row_array();essentialCheck($stoppedRow['stopped_at']!==null&&$details->forOwnTimesheet($a,7,'company_standalone_timer',(int)$stoppedRow['id'])['hourly_rate']==='99.00','Auto-stop preserves rate snapshot');}
$fourTimer=$db->query('SELECT * FROM '.$p.'company_standalone_timers WHERE issuer_id=? AND task_id=? AND actor_id=7',[$a,$four])->row_array();
essentialThrows(fn()=>$standalone->nativeTimer($a,7,$four,2,'stop',(int)$fourTimer['id'],0,'Wrong revision',$onHash,hash('sha256','bad-note-revision')),'native_timer_note_stale','Stop requires snapshot CAS revision');
essentialCheck($db->query('SELECT stopped_at FROM '.$p.'company_standalone_timers WHERE id=?',[$fourTimer['id']])->row_array()['stopped_at']===null,'Failed note CAS cannot stop timer');
$five=$makeTask('Native multi five');$beforeTimers=$db->query('SELECT * FROM '.$p.'company_standalone_timers ORDER BY id')->result_array();$db->failOn='INSERT INTO '.$p.'company_project_requests';essentialThrows(fn()=>$standalone->nativeTimer($a,7,$five,1,'start',null,0,'Fail',$onHash,hash('sha256','start-journal-fail')),'issuer_storage_failed','Journal failure rolls back start and automatic stops');$db->failOn=null;
essentialCheck($db->query('SELECT * FROM '.$p.'company_standalone_timers ORDER BY id')->result_array()===$beforeTimers,'Full canonical timer history preserved after rollback');
if($db->driver()==='sqlite'){$db->query('CREATE TRIGGER '.$p.'native_timer_revoke AFTER INSERT ON '.$p."issuers_audit WHEN NEW.event='company_task_native_start' BEGIN UPDATE ".$p.'company_standalone_tasks SET assignee_id=51 WHERE id='.$five.'; END');essentialThrows(fn()=>$standalone->nativeTimer($a,7,$five,1,'start',null,0,'Revoke',$onHash,hash('sha256','native-start-revoke')),'native_timer_assignee_required','Post-audit assignment revocation rejects timer and auto-stops');$db->query('DROP TRIGGER '.$p.'native_timer_revoke');essentialCheck($db->query('SELECT * FROM '.$p.'company_standalone_timers ORDER BY id')->result_array()===$beforeTimers,'Revocation rolls back whole timer operation');}
echo 'Native multiple-timer policies and rollback: '.$GLOBALS['essentialChecks']." cumulative checks passed.\n";

// Original auto-stop excludes task_id=0; an existing same-company blank remains active.
(new CompanyTaskTimerStopPreferences($db,$p))->save($a,7,3,[CompanyTaskTimerStopPreferences::AUTO_STOP=>'0']);$offAgain=NativeCompanyBlankTimers::policyHash((new NativeCompanyTimerPolicy($db,$p))->read($a,7));$blankKept=$service->start($a,7,$offAgain,hash('sha256','blank-preserved-on-native-start'));
(new CompanyTaskTimerStopPreferences($db,$p))->save($a,7,4,[CompanyTaskTimerStopPreferences::AUTO_STOP=>'1']);$onAgain=NativeCompanyBlankTimers::policyHash((new NativeCompanyTimerPolicy($db,$p))->read($a,7));
$standalone->nativeTimer($a,7,$five,1,'start',null,0,'Native automatic-stop note',$onAgain,hash('sha256','start-with-existing-blank'));
essentialCheck($db->query('SELECT state FROM '.$p.'company_blank_task_timers WHERE id=?',[$blankKept])->row_array()['state']==='active','Original auto-stop leaves unassigned timer untouched');
essentialCheck($details->forOwnTimesheet($a,7,'company_standalone_timer',(int)$fourTimer['id'])['note']==='Native automatic-stop note','Automatic stop uses the explicit native note input, not a fabricated note');
echo 'Native original blank exclusion: '.$GLOBALS['essentialChecks']." cumulative checks passed.\n";

$service->discard($a,7,$blankKept,1,hash('sha256','discard-before-positive-blank-auto'));$newBlank=$service->start($a,7,$onAgain,hash('sha256','positive-blank-auto-stop'));
essentialCheck($db->query('SELECT stopped_at FROM '.$p.'company_standalone_timers WHERE issuer_id=? AND task_id=? AND actor_id=7',[$a,$five])->row_array()['stopped_at']!==null,'Starting native blank timer stops assigned owned timer under ON policy');
essentialCheck($db->query('SELECT state FROM '.$p.'company_blank_task_timers WHERE id=?',[$newBlank])->row_array()['state']==='active','Blank start and assigned stop commit together');
echo 'Native blank start automatic stop: '.$GLOBALS['essentialChecks']." cumulative checks passed.\n";

// A fully migrated installation also has the project-only milestone participants.
require_once APPPATH.'services/CompanyProjectMilestonesSchema.php';
if($db->driver()==='sqlite'){
    foreach(CompanyProjectMilestonesSchema::definitions($p) as$table=>$columns){
        $db->query(str_replace(' CHARACTER SET utf8mb4 COLLATE utf8mb4_bin','','CREATE TABLE '.$p.$table.'('.implode(',',$columns).')'));
    }
    unset($db->data_cache['table_names']);
}else CompanyProjectMilestonesSchema::ensure($db,$p);
$service->discard($a,7,$newBlank,1,hash('sha256','discard-before-modality-pairs'));
// Reuse the writers: a declaration prepared before milestone installation must
// not survive into another operation on the same service instance.
$writers=['project'=>$projects,'standalone'=>$standalone];
foreach(['project','standalone'] as$oldKind)foreach(['project','standalone'] as$newKind){
    $pair=$oldKind.'-'.$newKind;$ids=[];
    foreach(['old'=>$oldKind,'new'=>$newKind] as$label=>$kind){
        $input=['assignee_id'=>7,'name'=>'Native pair '.$pair.' '.$label,'description'=>'','due_date'=>'2026-10-30','status'=>'todo','checklist'=>[]];
        if($kind==='project')$input=['project_id'=>$project]+$input;
        $ids[$label]=$writers[$kind]->create($a,7,$input,hash('sha256','pair-create-'.$pair.'-'.$label));
    }
    $oldTable=$oldKind==='project'?'company_task_timers':'company_standalone_timers';
    $newTable=$newKind==='project'?'company_task_timers':'company_standalone_timers';
    $writers[$oldKind]->nativeTimer($a,7,$ids['old'],1,'start',null,0,'Old pair note',$onAgain,hash('sha256','pair-old-'.$pair));
    $beforePair=[];foreach(['company_task_timers','company_standalone_timers'] as$table)$beforePair[$table]=$db->query('SELECT * FROM '.$p.$table.' ORDER BY id')->result_array();
    $db->failOn='INSERT INTO '.$p.'company_project_requests';
    essentialThrows(fn()=>$writers[$newKind]->nativeTimer($a,7,$ids['new'],1,'start',null,0,'Pair replacement',$onAgain,hash('sha256','pair-new-'.$pair)),'issuer_storage_failed','Cross-modality receipt failure rolls back the complete stop/start');
    $db->failOn=null;
    foreach($beforePair as$table=>$rows)essentialCheck($db->query('SELECT * FROM '.$p.$table.' ORDER BY id')->result_array()===$rows,'Both modality histories survive a late failed start');
    $writers[$newKind]->nativeTimer($a,7,$ids['new'],1,'start',null,0,'Pair replacement',$onAgain,hash('sha256','pair-new-'.$pair));
    $oldTimer=$db->query('SELECT * FROM '.$p.$oldTable.' WHERE issuer_id=? AND task_id=?',[$a,$ids['old']])->row_array();
    $newTimers=$db->query('SELECT * FROM '.$p.$newTable.' WHERE issuer_id=? AND task_id=?',[$a,$ids['new']])->result_array();
    essentialCheck(count($newTimers)===1&&$oldTimer['stopped_at']===$newTimers[0]['started_at']&&$newTimers[0]['stopped_at']===null,'Every native modality pair shares one atomic stop/start boundary');
    $oldTaskTable=$oldKind==='project'?'company_tasks':'company_standalone_tasks';
    essentialCheck($db->query('SELECT status FROM '.$p.$oldTaskTable.' WHERE id=?',[$ids['old']])->row_array()['status']==='todo','Automatic stop preserves the prior task workflow');
    $oldSource=$oldKind==='project'?'company_task_timer':'company_standalone_timer';
    essentialCheck($details->forOwnTimesheet($a,7,$oldSource,(int)$oldTimer['id'])['hourly_rate']==='99.00','Cross-modality stop preserves the captured rate');
    $writers[$newKind]->nativeTimer($a,7,$ids['new'],2,'stop',(int)$newTimers[0]['id'],1,'Pair complete',$onAgain,hash('sha256','pair-stop-'.$pair));
}
$newBlank=$service->start($a,7,$onAgain,hash('sha256','restore-active-blank-after-modality-pairs'));
echo 'Native all-modality atomic stop/start: '.$GLOBALS['essentialChecks']." cumulative checks passed.\n";
