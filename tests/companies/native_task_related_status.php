<?php
require __DIR__.'/native_task_index_rows.php';
require_once APPPATH.'services/NativeCompanyHeaderSchema.php';
$contactFixture=!$db->table_exists($p.'contacts');
if($contactFixture){$db->query('CREATE TABLE '.$p.'contacts(id INT NOT NULL PRIMARY KEY,userid INT NOT NULL)');unset($db->data_cache['table_names']);}
NativeCompanyHeaderSchema::ensure($db,$p);
register_shutdown_function(static function()use($db,$p,$contactFixture){if($db->driver()==='mysql')foreach(array_merge(['company_native_notifications','company_native_search_history'],$contactFixture?['contacts']:[])as$table)$db->pdo->exec('DROP TABLE IF EXISTS '.$p.$table);});
class_alias(NativeCompanyResourceReferences::class,'NativeTaskHttpTest\\NativeCompanyResourceReferences');
class_alias(CompanyTasks::class,'NativeTaskHttpTest\\CompanyTasks');
class_alias(CompanyStandaloneTasks::class,'NativeTaskHttpTest\\CompanyStandaloneTasks');
$related=(new NativeCompanyRelatedTaskPage($db,$p))->read($a,7,'ticket',$ticketCanonical,$input,'2026-10-25');
$rendered=(new NativeCompanyRelatedTaskRows($db,$p))->render($a,7,$related);
$rows=array_values(array_filter($rendered['aaData'],static fn($row)=>$row['DT_RowAttr']['data-native-task-id']===$new));
essentialCheck(count($rows)===1,'Original related task table renders exact ticket-linked owned task');
$attrs=$rows[0]['DT_RowAttr'];$identity=NativeCompanyResourceId::decode('task',$new);
$before=$standalone->get($a,7,$identity['source_id']);
essentialCheck($attrs['data-task-revision']===(string)$before['revision']&&$attrs['data-native-fields-revision']===(string)$before['native_fields_revision'],'Related row exposes both exact CAS revisions');
\NativeTaskHttpTest\CompanyRequestScope::$issuer=$a;\NativeTaskHttpTest\CompanyRequestScope::$actor=7;
$controller->input->verb='POST';$controller->input->query=[];$controller->input->body=['native_revision'=>$attrs['data-task-revision'],'native_fields_revision'=>$attrs['data-native-fields-revision'],'native_request_key'=>$key('related-mark-status'),'_company_context'=>'synthetic-context','_native_company_csrf'=>'synthetic-csrf'];
$controller->mark_as('5',$new);$response=json_decode($controller->output->body,true,32,JSON_THROW_ON_ERROR);$after=$standalone->get($a,7,$identity['source_id']);
essentialCheck($response['success']===true&&$after['status']==='done'&&(int)$after['revision']===(int)$before['revision']+1,'Related task status uses the canonical standalone writer');
$controller->input->body['native_revision']=(string)$after['revision'];$controller->input->body['native_fields_revision']=(string)$after['native_fields_revision'];$controller->input->body['native_request_key']=$key('related-change-priority');
$priority=(int)$after['priority']===4?1:4;$controller->change_priority((string)$priority,$new);$changed=$standalone->get($a,7,$identity['source_id']);
essentialCheck((int)$changed['priority']===$priority&&(int)$changed['revision']===(int)$after['revision']+1,'Related task priority uses the canonical standalone writer');
echo 'Original related task row and status: '.$GLOBALS['essentialChecks']." cumulative checks passed; authenticated HTTP/browser remains pending.\n";
