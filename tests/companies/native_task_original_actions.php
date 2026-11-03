<?php
/** Original HTTP actions execute the real owned writers; no model, storage or permission stubs. */
require __DIR__.'/native_task_status_controller.php';
\NativeTaskHttpTest\CompanyRequestScope::$issuer=$a;\NativeTaskHttpTest\CompanyRequestScope::$actor=7;
$controller->input->verb='POST';$controller->input->query=[];
$taskId=NativeCompanyResourceId::decode('task',$canonical)['source_id'];
$originalChecks=$GLOBALS['essentialChecks'];$sequence=0;
$body=function(array $extra=[])use($controller,$tasks,$a,$taskId,$key,&$sequence):array{
    $row=$tasks->get($a,7,$taskId);return array_merge(['native_revision'=>(string)$row['revision'],'native_fields_revision'=>(string)$row['native_fields_revision'],'native_request_key'=>$key('original-task-action-'.++$sequence),'_company_context'=>'synthetic-context','_native_company_csrf'=>'synthetic-csrf'],$extra);
};
$run=function(string $method,array $arguments,array $extra,callable $check)use($controller,$body,$tasks,$a,$taskId,$key,&$sequence):void{
    $controller->input->body=$body($extra);$submitted=$controller->input->body;$before=$tasks->get($a,7,$taskId);
    $controller->$method(...$arguments);$response=json_decode($controller->output->body,true,32,JSON_THROW_ON_ERROR);$after=$tasks->get($a,7,$taskId);
    essentialCheck($response['success']===true&&(int)$after['revision']===(int)$before['revision']+1&&$check($after),'Original '.$method.' commits only its intended task change');
    $controller->$method(...$arguments);essentialCheck($tasks->get($a,7,$taskId)===$after,'Original '.$method.' exact replay is durable');
    $controller->input->body['native_request_key']=$key('original-task-stale-'.++$sequence);
    try{$controller->$method(...$arguments);throw new RuntimeException('Stale action accepted: '.$method);}catch(LogicException $e){essentialCheck($e->getMessage()==='HTTP 409','Original '.$method.' rejects an old task revision');}
};
$run('update_task_description',[$canonical],['description'=>'Actual original description'],static fn($row)=>$row['description']==='Actual original description');
$run('make_public',[$canonical],['value'=>'1'],static fn($row)=>json_decode($row['native_fields_json'],true)['is_public']===1);
$run('make_public',[$canonical],['value'=>'0'],static fn($row)=>json_decode($row['native_fields_json'],true)['is_public']===0);
$tags=new NativeCompanyResourceTags($db,$p);
$run('update_tags',[],['task_id'=>$canonical,'tags'=>'First original, Second original','tags_revision'=>$tags->read($a,7,'task',$canonical)['revision']],fn($row)=>array_column($tags->read($a,7,'task',$canonical)['tags'],'name')===['First original','Second original']);
$members=new NativeCompanyWorkAssignments($db,$p);
$run('remove_assignee',['92',$canonical],['assignment_revision'=>$members->read($a,7,'task',$taskId)['revision']],fn($row)=>array_map('intval',array_column($members->read($a,7,'task',$taskId)['members'],'staffid'))===[7]);
$run('add_task_assignees',[],['taskid'=>$canonical,'staff_id'=>'92','assignment_revision'=>$members->read($a,7,'task',$taskId)['revision']],fn($row)=>array_map('intval',array_column($members->read($a,7,'task',$taskId)['members'],'staffid'))===[7,92]);
$followerIds=fn()=>array_map('intval',array_column($db->query('SELECT staff_id FROM '.$p.'company_task_followers WHERE issuer_id=? AND task_id=? ORDER BY staff_id',[$a,$taskId])->result_array(),'staff_id'));
$run('add_task_followers',[],['taskid'=>$canonical,'staff_id'=>'92'],fn($row)=>$followerIds()===[92]);
$run('remove_follower',['92',$canonical],[],fn($row)=>$followerIds()===[]);
$run('change_milestone',['0',$canonical],['milestone_revision'=>$tasks->nativeMilestoneSelection($a,7,(int)$tasks->get($a,7,$taskId)['project_id'])['revision']],fn($row)=>!$db->query('SELECT * FROM '.$p.'company_task_milestones WHERE issuer_id=? AND task_id=?',[$a,$taskId])->result_array());
$controller->input->body=$body();$controller->mark_as('5',$canonical);
$run('unmark_complete',[$canonical],[],static fn($row)=>$row['status']==='todo'&&json_decode($row['native_fields_json'],true)['date_finished']===null);

$run('task_single_inline_update',[$canonical],['startdate'=>'26/10/2026','duedate'=>'20/11/2026'],static fn($row)=>$row['due_date']==='2026-11-20'&&json_decode($row['native_fields_json'],true)['start_date']==='2026-10-26');
$run('task_single_inline_update',[$canonical],['duedate'=>''],static fn($row)=>$row['due_date']===null);
$controller->input->body=$body(['startdate'=>'30/02/2026']);
try{$controller->task_single_inline_update($canonical);throw new RuntimeException('Invalid date accepted');}catch(LogicException $e){essentialCheck($e->getMessage()==='HTTP 422','Original inline date preserves localized strict validation');}
$tasks->activity($a,7,$taskId,(int)$tasks->get($a,7,$taskId)['revision'],'comment',$key('original-comment-create'),'Original comment <script> stays literal');
$commentRow=fn()=>$db->query('SELECT id,issuer_id,task_id,actor_id,body,created_at FROM '.$p.'company_task_comments WHERE issuer_id=? AND task_id=? ORDER BY id DESC',[$a,$taskId])->row_array();
$comment=$commentRow();$commentId=(int)$comment['id'];
$run('edit_comment',[],['taskid'=>$canonical,'id'=>(string)$commentId,'content'=>'Edited <script> remains literal','no_editor'=>'1','native_comment_revision'=>CompanyTasks::nativeCommentRevision($comment)],fn($row)=>$commentRow()['body']==='Edited <script> remains literal');
$comment=$commentRow();$beforeCommentAudit=$db->query('SELECT metadata_json FROM '.$p.'issuers_audit WHERE issuer_id=? AND event=? ORDER BY id DESC',[$a,'company_task_native_original'])->row_array();
$proof=json_decode($beforeCommentAudit['metadata_json'],true)['native_task_original'];
essentialCheck($proof['before']['body']==='Original comment <script> stays literal'&&$proof['after']['body']==='Edited <script> remains literal','Comment audit preserves both stored versions');
$controller->input->body=$body(['taskid'=>$canonical,'id'=>(string)$commentId,'content'=>'Late failure must not replace','no_editor'=>'1','native_comment_revision'=>CompanyTasks::nativeCommentRevision($comment)]);
$db->failOn='INSERT INTO '.$p.'company_project_requests';try{$controller->edit_comment();throw new RuntimeException('Comment receipt failure accepted');}catch(LogicException $e){essentialCheck($e->getMessage()==='HTTP 503','Comment failure is observable');}finally{$db->failOn=null;}
essentialCheck($commentRow()===$comment,'Comment version rolls back with the whole transaction');
$run('remove_comment',[(string)$commentId],['taskid'=>$canonical,'native_comment_revision'=>CompanyTasks::nativeCommentRevision($comment)],fn($row)=>$commentRow()['body']===''&&!in_array($commentId,array_map('intval',array_column($row['comments'],'id')),true));
essentialCheck((int)$commentRow()['actor_id']===7&&$commentRow()['created_at']===$comment['created_at'],'Comment retirement preserves original identity, author and timestamp');

$controller->input->body=$body(['description'=>'Must roll back']);$before=$tasks->get($a,7,$taskId);$audits=$db->query('SELECT * FROM '.$p.'issuers_audit ORDER BY id')->result_array();
$db->failOn='INSERT INTO '.$p.'company_project_requests';try{$controller->update_task_description($canonical);throw new RuntimeException('Late receipt failure accepted');}catch(LogicException $e){essentialCheck($e->getMessage()==='HTTP 503','Late task receipt failure remains observable');}finally{$db->failOn=null;}
essentialCheck($tasks->get($a,7,$taskId)===$before&&$db->query('SELECT * FROM '.$p.'issuers_audit ORDER BY id')->result_array()===$audits,'Late receipt failure rolls back task and immutable action audit');
$controller->input->body=$body(['description'=>'Denied']);unset($controller->input->body['_native_company_csrf']);
try{$controller->update_task_description($canonical);throw new RuntimeException('Missing CSRF accepted');}catch(LogicException $e){essentialCheck($e->getMessage()==='HTTP 403','Original inline task command requires CSRF');}
$controller->input->body=$body(['description'=>'Denied']);\NativeTaskHttpTest\CompanyRequestScope::$issuer=$b;
try{$controller->update_task_description($canonical);throw new RuntimeException('Foreign task accepted');}catch(LogicException $e){essentialCheck($e->getMessage()==='HTTP 403','Company B cannot change company A task by known ID');}
\NativeTaskHttpTest\CompanyRequestScope::$issuer=$a;\NativeTaskHttpTest\CompanyRequestScope::$actor=92;
try{$controller->update_task_description($canonical);throw new RuntimeException('Create-only actor edited task');}catch(LogicException $e){essentialCheck($e->getMessage()==='HTTP 403','Create-only actor cannot edit through an original inline action');}
\NativeTaskHttpTest\CompanyRequestScope::$actor=7;
$controller->input->body=$body(['description'=>'Denied','issuer_id'=>$b]);
try{$controller->update_task_description($canonical);throw new RuntimeException('Unknown authority field accepted');}catch(LogicException $e){essentialCheck($e->getMessage()==='HTTP 422','Original action rejects authority fields outside its finite input');}
essentialCheck($tasks->get($a,7,$taskId)===$before,'Denied actions preserve the task exactly');
echo 'Original task action composition: '.($GLOBALS['essentialChecks']-$originalChecks).' additional checks passed ('.$db->driver()."). Browser equivalence remains separate.\n";
