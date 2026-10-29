<?php
/** Official task comments and attachments preserve canonical ownership and refresh the modal. */
require __DIR__.'/native_task_detail_controller.php';
// The included fixture has completed its assertions. Its query trace is not
// consulted below; release it before exercising the independent mutations.
$db->queries=[];
class_alias(NativeCompanyResourceReferences::class,'NativeTaskHttpTest\\NativeCompanyResourceReferences');
class_alias(CompanyTasks::class,'NativeTaskHttpTest\\CompanyTasks');
class_alias(CompanyStandaloneTasks::class,'NativeTaskHttpTest\\CompanyStandaloneTasks');
class_alias(CompanyProjectExtras::class,'NativeTaskHttpTest\\CompanyProjectExtras');
\NativeTaskHttpTest\CompanyRequestScope::$issuer=$a;\NativeTaskHttpTest\CompanyRequestScope::$actor=7;
$source=NativeCompanyResourceId::decode('task',$canonical)['source_id'];
$controller->input->verb='POST';$controller->input->query=[];$_FILES=[];
$before=$tasks->get($a,7,$source);$commentKey=$key('official-task-comment');
$controller->input->body=['taskid'=>$canonical,'content'=>'  Visible <b>owned</b> comment  ','native_revision'=>(string)$before['revision'],'native_request_key'=>$commentKey,'_company_context'=>'synthetic-context','_native_company_csrf'=>'synthetic-csrf'];
$controller->add_task_comment();$response=json_decode($controller->output->body,true,32,JSON_THROW_ON_ERROR);$after=$tasks->get($a,7,$source);
essentialCheck($response['success']===true&&str_contains($response['taskHtml'],'Visible owned comment')&&(int)$after['revision']===(int)$before['revision']+1,'Official comment POST stores purified text and refreshes task detail');
$comments=array_values(array_filter($after['comments'],static fn($row)=>$row['body']==='Visible owned comment'));
essentialCheck(count($comments)===1&&(int)$comments[0]['actor_id']===7,'Task comment provenance remains bound to the current company actor');
$controller->add_task_comment();$replayed=$tasks->get($a,7,$source);essentialCheck($replayed===$after,'Exact comment retry is durable and does not duplicate activity');

$fileKey=$key('official-task-file');$temporary=tempnam(sys_get_temp_dir(),'native-task-file-');$bytes="owned task attachment\n";file_put_contents($temporary,$bytes);
$controller->input->body=['taskid'=>$canonical,'native_revision'=>(string)$after['revision'],'native_request_key'=>$fileKey,'_company_context'=>'synthetic-context','_native_company_csrf'=>'synthetic-csrf'];
$_FILES=['file'=>['name'=>'evidence.txt','tmp_name'=>$temporary,'error'=>UPLOAD_ERR_OK]];
try{$controller->upload_file();}finally{unlink($temporary);$_FILES=[];}
$response=json_decode($controller->output->body,true,32,JSON_THROW_ON_ERROR);$files=(new CompanyProjectExtras($db,$p))->files($a,7,'task',$source);
essentialCheck($response['success']===true&&count($files['items'])===1&&$files['items'][0]['file_key']===$fileKey&&str_contains($response['taskHtml'],'evidence.txt'),'Official task upload stores owned bytes and refreshes the file list');

$controller->input->body=['taskid'=>$canonical,'native_revision'=>(string)$files['revision'],'native_request_key'=>$key('official-task-file-remove'),'_company_context'=>'synthetic-context','_native_company_csrf'=>'synthetic-csrf'];
$controller->remove_task_attachment($fileKey);$response=json_decode($controller->output->body,true,32,JSON_THROW_ON_ERROR);$filesAfter=(new CompanyProjectExtras($db,$p))->files($a,7,'task',$source);
essentialCheck($response['success']===true&&$filesAfter['items']===[]&&!str_contains($response['taskHtml'],'evidence.txt'),'Official task attachment removal hides only the owned file and refreshes detail');

\NativeTaskHttpTest\CompanyRequestScope::$issuer=$b;$controller->input->body=['taskid'=>$canonical,'content'=>'foreign','native_revision'=>(string)$filesAfter['revision'],'native_request_key'=>$key('foreign-task-comment'),'_company_context'=>'synthetic-context','_native_company_csrf'=>'synthetic-csrf'];
try{$controller->add_task_comment();throw new RuntimeException('Foreign comment accepted');}catch(LogicException$e){essentialCheck($e->getMessage()==='HTTP 403','Another company cannot add a task comment');}
echo 'Official task comments/files: CSRF, replay, provenance, upload/remove and cross-company guards passed.'.PHP_EOL;
