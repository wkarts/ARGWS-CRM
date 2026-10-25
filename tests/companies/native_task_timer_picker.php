<?php
require __DIR__.'/native_task_project_data.php';
require_once APPPATH.'services/NativeCompanyTasksRead.php';
class_alias(NativeCompanyTasksRead::class,'NativeTaskHttpTest\\NativeCompanyTasksRead');
$reader=new NativeCompanyTasksRead($db,$p);
$rows=$reader->timerAssignmentOptions($a,7,'');
essentialCheck(count($rows)>0,'Original picker returns actual assigned tasks');
$ids=array_column($rows,'id');essentialCheck(in_array($task,$ids,true),'Canonical selected project task is present');
foreach($rows as$row)essentialCheck(array_keys($row)===['id','name','subtext','native_revision'],'Native picker retains id/name/subtext plus explicit revision');
$taskId=NativeCompanyResourceId::decode('task',$task)['source_id'];
$db->query('UPDATE '.$p.'company_tasks SET name=? WHERE issuer_id=? AND id=?',['Unique timer picker',$a,$taskId]);
$filtered=$reader->timerAssignmentOptions($a,7,'unique TIMER');essentialCheck(count($filtered)===1&&$filtered[0]['id']===$task,'Picker search is case insensitive and returns canonical task id');
$db->query('UPDATE '.$p.'company_task_assignees SET active=0 WHERE issuer_id=? AND task_id=? AND staff_id=7',[$a,$taskId]);
essentialCheck($reader->timerAssignmentOptions($a,7,'Unique timer picker')===[],'Revoked explicit assignment is not resurrected from canonical singleton');
$db->query('UPDATE '.$p.'company_task_assignees SET active=1 WHERE issuer_id=? AND task_id=? AND staff_id=7',[$a,$taskId]);
essentialCheck($reader->timerAssignmentOptions($b,7,'Unique timer picker')===[],'Other issuer cannot discover task through picker');
$controller->input->verb='POST';$controller->input->body=['q'=>'Unique timer picker','_native_company_csrf'=>'synthetic-csrf'];\NativeTaskHttpTest\CompanyRequestScope::$issuer=$a;
$controller->ajax_search_assign_task_to_timer();$response=json_decode($controller->output->body,true,512,JSON_THROW_ON_ERROR);essentialCheck($response===$reader->timerAssignmentOptions($a,7,'Unique timer picker'),'Original Tasks POST endpoint uses actual reader and original JSON contract');
echo 'Native timer task picker: '.$GLOBALS['essentialChecks']." cumulative checks passed.\n";
$primary=(new IssuerResourceAccess($db,$p))->primaryId();
// Actual legacy identities are separate, not a raw-id alias of company_task.
foreach(['tasks','task_assigned']as$table)if(!$db->table_exists($p.$table)){
 $seed=file_get_contents(dirname(__DIR__,2).'/install/database.sql');preg_match('/CREATE TABLE `tbl'.$table.'` \(.*?\) ENGINE=InnoDB[^;]*;/s',$seed,$ddl);if(!$ddl)throw new RuntimeException('Missing native table definition');$sql=str_replace('`tbl'.$table.'`','`'.$p.$table.'`',$ddl[0]);if($db->driver()==='sqlite')$sql=preg_replace('/ CHARACTER SET [a-zA-Z0-9_]+ COLLATE [a-zA-Z0-9_]+/','',$sql);$db->query($sql);
}
unset($db->data_cache['table_names']);
$db->query('INSERT INTO '.$p.'tasks(id,name,description,priority,dateadded,startdate,status,rel_type,rel_id,billed,addedfrom) VALUES(701,?,?,?,?,?,1,NULL,NULL,0,7),(702,?,?,?,?,?,1,?, ?,0,7)',['Legacy timer picker','','2','2026-10-25 00:00:00','2026-10-25','Private customer task','','2','2026-10-25 00:00:00','2026-10-25','customer',$ca]);
$db->query('INSERT INTO '.$p.'task_assigned(id,staffid,taskid,assigned_from) VALUES(701,7,701,7),(702,7,702,7)');
$legacy=$reader->timerAssignmentOptions($primary,7,'Legacy timer picker');essentialCheck(count($legacy)===1&&$legacy[0]['id']==='701','Principal preserves original task identity without reinterpreting owned ids');
essentialCheck($reader->timerAssignmentOptions($a,7,'Legacy timer picker')===[],'Secondary never reads legacy timer candidates');
essentialCheck($reader->timerAssignmentOptions($primary,7,'Private customer task')===[],'Principal legacy picker excludes task related to another issuer customer');
$controller->input->body=[];try{$controller->ajax_search_assign_task_to_timer();throw new RuntimeException('Missing CSRF accepted');}catch(LogicException$e){essentialCheck($e->getMessage()==='HTTP 422','Original picker requires actual contextual POST proof');}
echo 'Native timer picker lineages: '.$GLOBALS['essentialChecks']." cumulative checks passed.\n";

essentialThrows(fn()=>$reader->timerAssignmentOptions($a,7,'',51),'native_company_task_query_invalid','Caller cannot enlarge bounded result limit');
essentialThrows(fn()=>$reader->timerAssignmentOptions($a,7,str_repeat('x',192)),'native_company_task_query_invalid','Unbounded query is rejected');
echo 'Native timer picker complete contract: '.$GLOBALS['essentialChecks']." cumulative checks passed.\n";
