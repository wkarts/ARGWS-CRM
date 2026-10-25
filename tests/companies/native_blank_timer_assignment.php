<?php
require __DIR__.'/native_blank_timers.php';
require_once APPPATH.'services/NativeCompanyWorkAssignmentsSchema.php';
require_once APPPATH.'services/CompanyTasks.php';require_once APPPATH.'services/CompanyStandaloneTasks.php';
foreach(CompanyProjectExtrasSchema::definitions($p)as$table=>$columns)if(!$db->table_exists($p.$table)){$ddl='CREATE TABLE '.$p.$table.'('.implode(',',$columns).')';$ddl=str_replace(' CHARACTER SET utf8mb4 COLLATE utf8mb4_bin','',$ddl);$db->query($ddl);}unset($db->data_cache['table_names']);
NativeCompanyWorkAssignmentsSchema::ensure($db,$p);
$db->query('UPDATE '.$p.'company_blank_task_timers SET closed_at=NULL,started_at=? WHERE id=?',[gmdate('Y-m-d H:i:s',time()-60),$second]);
$beforeTask=$db->query('SELECT * FROM '.$p.'company_standalone_tasks WHERE id=?',[$task])->row_array();
$history=$db->query('SELECT * FROM '.$p.'company_standalone_timers ORDER BY id')->result_array();
$key=hash('sha256','assign-blank');$assigned=$service->assign($a,7,$second,1,'company_standalone_task',$task,1,'Preserved note',$key);
essentialCheck($assigned===$second&&$service->assign($a,7,$second,1,'company_standalone_task',$task,1,'Preserved note',$key)===$second,'assignment exact replay creates one completed timer');
$blank=$db->query('SELECT * FROM '.$p.'company_blank_task_timers WHERE id=?',[$second])->row_array();$timer=$db->query('SELECT * FROM '.$p.'company_standalone_timers WHERE id=?',[$blank['target_timer_id']])->row_array();
essentialCheck($blank['state']==='assigned'&&$blank['target_kind']==='company_standalone_task'&&(int)$blank['target_id']===$task&&$blank['note']==='Preserved note'&&(int)$blank['revision']===2,'assignment preserves provenance, note and revision');
essentialCheck((int)$timer['seconds']>=60&&(int)$timer['seconds']<65&&$timer['stopped_at']!==null&&(int)$timer['actor_id']===7,'elapsed time attaches as completed owned timer');
essentialCheck($db->query('SELECT * FROM '.$p.'company_standalone_tasks WHERE id=?',[$task])->row_array()===$beforeTask,'assigning elapsed time does not relabel task status or change task fields');
essentialCheck(array_slice($db->query('SELECT * FROM '.$p.'company_standalone_timers ORDER BY id')->result_array(),0,count($history))===$history,'other active timer remains untouched under native policy OFF');
essentialThrows(fn()=>$service->assign($a,7,$second,2,'company_standalone_task',$task,1,'',hash('sha256','twice')),'native_timer_closed','closed source cannot be transferred twice');
$new=$service->start($a,7,$off,hash('sha256','new-blank'));
essentialThrows(fn()=>$service->assign($b,7,$new,1,'company_standalone_task',$task,1,'',hash('sha256','cross-owner')),'native_company_task_denied','cross issuer cannot consume blank');
essentialThrows(fn()=>$service->assign($a,7,$new,1,'company_standalone_task',$task,2,'',hash('sha256','stale-task')),'company_project_stale','task revision is validated in same transaction');
$db->query("UPDATE {$p}company_standalone_tasks SET status='unknown' WHERE id=?",[$task]);essentialThrows(fn()=>$service->assign($a,7,$new,1,'company_standalone_task',$task,1,'',hash('sha256','unknown-status')),'company_project_closed','unknown task state is not assumed mutable');$db->query("UPDATE {$p}company_standalone_tasks SET status='todo' WHERE id=?",[$task]);
$db->query('UPDATE '.$p.'company_standalone_assignees SET active=0 WHERE issuer_id=? AND task_id=? AND staff_id=7',[$a,$task]);
essentialThrows(fn()=>$service->assign($a,7,$new,1,'company_standalone_task',$task,1,'',hash('sha256','no-assignee')),'native_timer_assignee_required','revoked assignment cannot receive elapsed time');
$db->query('UPDATE '.$p.'company_standalone_assignees SET active=1 WHERE issuer_id=? AND task_id=? AND staff_id=7',[$a,$task]);
$before=$db->query('SELECT * FROM '.$p.'company_standalone_timers ORDER BY id')->result_array();$db->failOn='UPDATE '.$p.'company_blank_task_timers';essentialThrows(fn()=>$service->assign($a,7,$new,1,'company_standalone_task',$task,1,'',hash('sha256','source-write-failure')),'issuer_storage_failed','failed source close rolls back target insertion');$db->failOn=null;
essentialCheck($before===$db->query('SELECT * FROM '.$p.'company_standalone_timers ORDER BY id')->result_array(),'no unlinked target receipt after failed source transition');
$db->query('UPDATE '.$p.'company_standalone_timers SET seconds=seconds+1 WHERE id=?',[$blank['target_timer_id']]);
essentialThrows(fn()=>$service->assign($a,7,$second,1,'company_standalone_task',$task,1,'Preserved note',$key),'issuer_native_timer_write_unconfirmed','replay rejects altered target duration');
$db->query('UPDATE '.$p.'company_standalone_timers SET seconds=seconds-1 WHERE id=?',[$blank['target_timer_id']]);
// Audit-time target corruption must roll back the source, target, journal and audit together.
if($db->driver()==='sqlite'){
$db->query('CREATE TRIGGER '.$p.'corrupt_import AFTER INSERT ON '.$p."issuers_audit WHEN NEW.event='company_native_blank_assigned' BEGIN UPDATE ".$p.'company_standalone_timers SET seconds=seconds+1 WHERE id=(SELECT target_timer_id FROM '.$p.'company_blank_task_timers WHERE id=NEW.subject_id); END');
essentialThrows(fn()=>$service->assign($a,7,$new,1,'company_standalone_task',$task,1,'',hash('sha256','audit-corruption')),'issuer_native_timer_write_unconfirmed','post-insert audit cannot mutate linked timer undetected');
essentialCheck($before===$db->query('SELECT * FROM '.$p.'company_standalone_timers ORDER BY id')->result_array(),'audit-time corruption rolls back all target changes');$db->query('DROP TRIGGER '.$p.'corrupt_import');
}
$db->query("INSERT INTO {$p}clients(userid,company) VALUES(701,'Synthetic project client')");
$db->query("INSERT INTO {$p}company_projects(issuer_id,client_id,responsible_id,name,description,status,start_date,due_date,revision,created_at,updated_at) VALUES(?,701,7,'Project','','active','2026-10-01','2026-12-31',1,'2026-10-25 12:00:00','2026-10-25 12:00:00')",[$a]);$project=(int)$db->insert_id();
$db->query("INSERT INTO {$p}company_tasks(issuer_id,project_id,assignee_id,name,description,status,due_date,checklist_json,revision,created_at,updated_at) VALUES(?,?,7,'Project task','','todo','2026-12-31','[]',1,'2026-10-25 12:00:00','2026-10-25 12:00:00')",[$a,$project]);$projectTask=(int)$db->insert_id();NativeCompanyWorkAssignmentsSchema::ensure($db,$p);
$db->query("UPDATE {$p}company_projects SET status='completed' WHERE id=?",[$project]);essentialThrows(fn()=>$service->assign($a,7,$new,1,'company_task',$projectTask,1,'',hash('sha256','closed-project')),'company_project_closed','closed project cannot absorb blank duration');$db->query("UPDATE {$p}company_projects SET status='active' WHERE id=?",[$project]);
$service->assign($a,7,$new,1,'company_task',$projectTask,1,'Project duration',hash('sha256','project-assign'));
$projectSource=$db->query('SELECT * FROM '.$p.'company_blank_task_timers WHERE id=?',[$new])->row_array();$projectTimer=$db->query('SELECT * FROM '.$p.'company_task_timers WHERE id=?',[$projectSource['target_timer_id']])->row_array();essentialCheck($projectSource['target_kind']==='company_task'&&(int)$projectTimer['task_id']===$projectTask&&(int)$projectTimer['issuer_id']===$a,'project target with overlapping task id uses correct timer store');
echo "Native blank timer assignment: {$GLOBALS['essentialChecks']} cumulative checks passed.\n";
