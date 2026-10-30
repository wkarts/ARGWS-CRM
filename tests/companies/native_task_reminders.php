<?php
require __DIR__.'/native_task_original_actions.php';
$start=$GLOBALS['essentialChecks'];
$controller->input->query=[];$controller->input->verb='POST';
$reminderBody=fn(string$label,array$extra=[])=>array_merge([
    'taskid'=>$canonical,'native_revision'=>(string)$tasks->get($a,7,$taskId)['revision'],
    'native_request_key'=>hash('sha256',$label),'_company_context'=>'synthetic-context','_native_company_csrf'=>'synthetic-csrf',
],$extra);
$fields=['description'=>'Private <script>reminder','date'=>'20/11/2027 15:30','staff'=>'7','notify_by_email'=>'1'];
$controller->input->body=$reminderBody('original-task-reminder',$fields);$create=$controller->input->body;
$controller->add_reminder($canonical);$result=json_decode($controller->output->body,true,32,JSON_THROW_ON_ERROR);
$ref=NativeCompanyResourceId::decode('reminder',$result['id']);$reminder=$ref['source_id'];$typed=$result['id'];
$read=fn()=>$db->query('SELECT * FROM '.$p.'company_reminders WHERE issuer_id=? AND id=?',[$a,$reminder])->row_array();$row=$read();
essentialCheck($result['success']===true&&$ref['source_kind']==='company_reminder'&&$row['source_kind']==='company_task'&&(string)$row['rel_id']===$canonical&&$row['description']===$fields['description']&&$row['date']==='2027-11-20 15:30:00'&&(int)$row['notify_by_email']===1,'Original add_reminder stores exact text, localized time, recipient and typed task relation');
$controller->add_reminder($canonical);essentialCheck($read()===$row&&json_decode($controller->output->body,true)['id']===$typed,'Add reminder exact replay retains one record');
$controller->input->body['description']='Changed intent';
try{$controller->add_reminder($canonical);throw new RuntimeException('Reused reminder key accepted');}catch(LogicException$e){essentialCheck($e->getMessage()==='HTTP 409','Same reminder key rejects different intent');}
$controller->input->body=$reminderBody('original-task-reminder-edit',array_replace($fields,['description'=>'Revised private reminder','revision'=>'1','notify_by_email'=>'0']));$edit=$controller->input->body;
$controller->edit_reminder($typed);$updated=$read();essentialCheck($updated['description']==='Revised private reminder'&&(int)$updated['revision']===2&&(int)$updated['notify_by_email']===0,'Original edit_reminder updates only the scoped row and revision');
$controller->edit_reminder($typed);essentialCheck($read()===$updated,'Edit reminder exact replay is durable');
$controller->input->body['native_request_key']=hash('sha256','stale-task-reminder');try{$controller->edit_reminder($typed);throw new RuntimeException('Stale reminder accepted');}catch(LogicException$e){essentialCheck($e->getMessage()==='HTTP 409','Original reminder edit rejects stale reminder revision');}
$controller->input->body=$reminderBody('stale-task-parent',$fields);$controller->input->body['native_revision']='1';try{$controller->add_reminder($canonical);throw new RuntimeException('Stale task parent accepted');}catch(LogicException$e){essentialCheck($e->getMessage()==='HTTP 409','Reminder creation rejects stale task revision');}
$controller->input->body=$reminderBody('foreign-task-reminder',$fields);\NativeTaskHttpTest\CompanyRequestScope::$issuer=$b;
try{$controller->add_reminder($canonical);throw new RuntimeException('Foreign task accepted');}catch(LogicException$e){essentialCheck($e->getMessage()==='HTTP 403','Known foreign task ID cannot receive a reminder');}
\NativeTaskHttpTest\CompanyRequestScope::$issuer=$a;\NativeTaskHttpTest\CompanyRequestScope::$actor=92;
try{$controller->add_reminder($canonical);throw new RuntimeException('Create-only actor accepted');}catch(LogicException$e){essentialCheck($e->getMessage()==='HTTP 403','Create-only task permission does not grant reminder editing');}
\NativeTaskHttpTest\CompanyRequestScope::$actor=7;unset($controller->input->body['_native_company_csrf']);
try{$controller->add_reminder($canonical);throw new RuntimeException('Missing reminder CSRF accepted');}catch(LogicException$e){essentialCheck($e->getMessage()==='HTTP 403','Original reminder mutation requires legitimate CSRF');}
$controller->input->body=$reminderBody('reminder-audit-rollback',array_replace($fields,['revision'=>'2']));$db->failOn='INSERT INTO '.$p.'issuers_audit';
try{$controller->edit_reminder($typed);throw new RuntimeException('Audit failure accepted');}catch(LogicException$e){essentialCheck($e->getMessage()==='HTTP 503','Reminder audit failure is observable');}finally{$db->failOn=null;}
essentialCheck($read()===$updated,'Late audit failure rolls back reminder body and revision');
$controller->input->body=$reminderBody('original-task-reminder-delete',['revision'=>'2']);$controller->delete_reminder($canonical,$typed);
essentialCheck(!$read(),'Original delete_reminder removes the active reminder');$controller->delete_reminder($canonical,$typed);essentialCheck(json_decode($controller->output->body,true)['success']===true,'Removal replay succeeds without repeating the effect');
$audit=$db->query("SELECT metadata_json FROM {$p}issuers_audit WHERE issuer_id=? AND subject_id=? AND event='native_reminder_deleted'",[$a,$reminder])->row_array();
essentialCheck(json_decode($audit['metadata_json'],true)['before']['description']==='Revised private reminder','Removal retains actual previous reminder content in the audit history');
echo 'Original task reminders: '.($GLOBALS['essentialChecks']-$start).' additional checks passed ('.$db->driver().').'.PHP_EOL;
