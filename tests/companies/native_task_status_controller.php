<?php
require __DIR__.'/native_task_form_http.php';
require_once APPPATH.'services/NativeCompanyHeaderSchema.php';
$contactFixture=!$db->table_exists($p.'contacts');
if($contactFixture){$db->query('CREATE TABLE '.$p.'contacts(id INT NOT NULL PRIMARY KEY,userid INT NOT NULL)');unset($db->data_cache['table_names']);}
NativeCompanyHeaderSchema::ensure($db,$p);
require_once APPPATH.'services/NativeCompanyNotifications.php';
essentialCheck(!in_array('company_announcements',NativeCompanyNotifications::tables('task_status'),true)&&in_array('company_native_notifications',NativeCompanyNotifications::tables('task_status'),true),'Task status notification depends on its own storage, not the announcements catalog');
register_shutdown_function(static function()use($db,$p,$contactFixture){if($db->driver()==='mysql')foreach(array_merge(['company_native_notifications','company_native_search_history'],$contactFixture?['contacts']:[])as$table)$db->pdo->exec('DROP TABLE IF EXISTS '.$p.$table);});
class_alias(NativeCompanyResourceReferences::class,'NativeTaskHttpTest\\NativeCompanyResourceReferences');
class_alias(CompanyTasks::class,'NativeTaskHttpTest\\CompanyTasks');
class_alias(CompanyStandaloneTasks::class,'NativeTaskHttpTest\\CompanyStandaloneTasks');
\NativeTaskHttpTest\CompanyRequestScope::$issuer=$a;\NativeTaskHttpTest\CompanyRequestScope::$actor=7;
$identity=NativeCompanyResourceId::decode('task',$canonical);$before=$tasks->get($a,7,$identity['source_id']);
$controller->input->verb='POST';$controller->input->query=[];
$controller->input->body=['native_revision'=>(string)$before['revision'],'native_fields_revision'=>(string)$before['native_fields_revision'],'native_request_key'=>$key('controller-mark-status'),'_company_context'=>'synthetic-context','_native_company_csrf'=>'synthetic-csrf'];
$controller->mark_as('5',$canonical);$response=json_decode($controller->output->body,true,32,JSON_THROW_ON_ERROR);
$after=$tasks->get($a,7,$identity['source_id']);essentialCheck($response['success']===true&&$after['status']==='done'&&(int)$after['revision']===(int)$before['revision']+1,'Original mark_as POST commits exact native status');
$notifications=$db->query('SELECT issuer_id,recipient_id,body FROM '.$p.'company_native_notifications WHERE source_kind=? ORDER BY id',['task_status'])->result_array();
essentialCheck(count($notifications)>=1&&array_reduce($notifications,static fn($ok,$row)=>$ok&&(int)$row['issuer_id']===$a&&str_contains($row['body'],$before['name']),true),'Actual status notices retain scoped task name and never target another issuer');
$controller->mark_as('5',$canonical);essentialCheck($tasks->get($a,7,$identity['source_id'])===$after,'Exact retry preserves durable status without duplicate transition');
$controller->input->body['native_request_key']=$key('controller-mark-stale');try{$controller->mark_as('3',$canonical);throw new RuntimeException('Stale status accepted');}catch(LogicException$e){essentialCheck($e->getMessage()==='HTTP 409','Old task revision is refused before writing');}
$controller->input->body['native_revision']=(string)$after['revision'];$controller->input->body['native_fields_revision']=(string)$after['native_fields_revision'];$controller->input->body['native_request_key']=$key('controller-mark-detail');$controller->input->query=['single_task'=>'true'];$controller->mark_as('3',$canonical);$response=json_decode($controller->output->body,true,32,JSON_THROW_ON_ERROR);$after=$tasks->get($a,7,$identity['source_id']);
essentialCheck($response['success']===true&&$after['status']==='testing'&&str_contains($response['taskHtml'],'data-native-task-detail')&&str_contains($response['taskHtml'],$canonical),'Status mutation refreshes the official scoped task detail');
$controller->input->query=[];$controller->input->body['native_revision']=(string)$after['revision'];$controller->input->body['native_fields_revision']=(string)$after['native_fields_revision'];$controller->input->body['native_request_key']=$key('controller-mark-notice-fail');$db->failOn='INSERT INTO '.$p.'company_native_notifications';try{$controller->mark_as('4',$canonical);throw new RuntimeException('Failed notification accepted');}catch(LogicException$e){essentialCheck($e->getMessage()==='HTTP 503','Notification storage failure is surfaced');}finally{$db->failOn=null;}essentialCheck($tasks->get($a,7,$identity['source_id'])===$after,'Notification failure rolls back the status mutation');
$controller->input->query=[];unset($controller->input->body['_native_company_csrf']);try{$controller->mark_as('4',$canonical);throw new RuntimeException('Missing CSRF accepted');}catch(LogicException$e){essentialCheck($e->getMessage()==='HTTP 403','Status write requires native CSRF');}
$controller->input->body['_native_company_csrf']='synthetic-csrf';\NativeTaskHttpTest\CompanyRequestScope::$issuer=$b;try{$controller->mark_as('4',$canonical);throw new RuntimeException('Foreign task accepted');}catch(LogicException$e){essentialCheck($e->getMessage()==='HTTP 403','Another company cannot change the task');}
echo 'Original task status controller: '.$GLOBALS['essentialChecks']." cumulative checks passed; actual HTTP/browser remain pending.\n";
