<?php
require __DIR__.'/native_task_items_commands.php';
foreach(['NativeCompanyResourceReferences','CompanyTasks','CompanyStandaloneTasks','NativeCompanyTaskItems']as$class)if(!class_exists('NativeTaskHttpTest\\'.$class,false))class_alias($class,'NativeTaskHttpTest\\'.$class);
\NativeTaskHttpTest\CompanyRequestScope::$issuer=$a;\NativeTaskHttpTest\CompanyRequestScope::$actor=7;$controller->input->verb='POST';$controller->input->query=[];
$controller->input->body=['action'=>'add','task_revision'=>(string)$task['revision'],'checklist_revision'=>(string)$state['revision'],'request_key'=>$key('controller-checklist-add'),'text'=>'Interface','_company_context'=>'synthetic-context','_native_company_csrf'=>'synthetic-csrf'];
$controller->checklist_command($canonical);$response=json_decode($controller->output->body,true,32,JSON_THROW_ON_ERROR);essentialCheck($response['success']===true&&end($response['checklist']['items'])['description']==='Interface','Original controller exposes scoped checklist command and revisions');
$controller->checklist_command($canonical);essentialCheck(json_decode($controller->output->body,true)['checklist']===$response['checklist'],'Controller exact retry preserves item IDs');
$controller->input->body['request_key']=$key('controller-checklist-stale');try{$controller->checklist_command($canonical);throw new RuntimeException('Stale accepted');}catch(LogicException$e){essentialCheck($e->getMessage()==='HTTP 409','Controller rejects stale task revision');}
unset($controller->input->body['_native_company_csrf']);try{$controller->checklist_command($canonical);throw new RuntimeException('CSRF accepted');}catch(LogicException$e){essentialCheck($e->getMessage()==='HTTP 403','Checklist command requires CSRF');}
$controller->input->body['_native_company_csrf']='synthetic-csrf';\NativeTaskHttpTest\CompanyRequestScope::$issuer=$b;try{$controller->checklist_command($canonical);throw new RuntimeException('Foreign accepted');}catch(LogicException$e){essentialCheck($e->getMessage()==='HTTP 403','Other issuer cannot mutate checklist');}
\NativeTaskHttpTest\CompanyRequestScope::$issuer=$a;try{$controller->get_task_data($canonical);throw new RuntimeException('Unscoped modal accepted');}catch(LogicException$e){essentialCheck($e->getMessage()==='HTTP 422','Uncomposed native detail never reaches global task model');}
// The official action names now reach that same writer, with path/payload
// agreement, parent ownership, command revisions and the original UI transport.
$invokeOriginal=function(string $method,array $fields,array $args=[])use($controller,$standalone,$reader,$a,$id,$canonical,$key){
    $task=$standalone->get($a,7,$id);$state=$reader->read($a,7,$canonical);
    $controller->input->body=['task_id'=>$canonical,'task_revision'=>(string)$task['revision'],'checklist_revision'=>(string)$state['revision'],'request_key'=>$key('official-'.$method),'_native_company_csrf'=>'synthetic-csrf']+$fields;
    $controller->$method(...$args);$response=json_decode($controller->output->body,true,32,JSON_THROW_ON_ERROR);
    essentialCheck($response['success']===true,'official '.$method.' reaches the scoped transactional writer');return$response;
};
$response=$invokeOriginal('add_checklist_item',['description'=>'Original route']);$item=end($response['checklist']['items']);
$response=$invokeOriginal('update_checklist_item',['listid'=>(string)$item['id'],'item_revision'=>(string)$item['revision'],'description'=>'Original updated']);$item=end($response['checklist']['items']);
essentialCheck($item['description']==='Original updated','original edit stores the requested text on the same item ID');
$response=$invokeOriginal('checkbox_action',['item_revision'=>(string)$item['revision']],[$item['id'],'1']);$item=end($response['checklist']['items']);
essentialCheck((int)$item['finished']===1&&(int)$item['finished_by']===7,'original checkbox records actual completion and actor');
$response=$invokeOriginal('save_checklist_assigned_staff',['checklistId'=>(string)$item['id'],'item_revision'=>(string)$item['revision'],'staffId'=>'7']);$item=end($response['checklist']['items']);
essentialCheck((int)$item['assigned_id']===7,'original assignment keeps an authorized member');
$response=$invokeOriginal('update_checklist_order',['ids'=>array_reverse(array_column($response['checklist']['items'],'id'))]);$item=$response['checklist']['items'][0];
$response=$invokeOriginal('delete_checklist_item',['item_revision'=>(string)$item['revision']],[$item['id']]);
essentialCheck(!in_array($item['id'],array_column($response['checklist']['items'],'id')),'original delete removes the visible item while the writer preserves its tombstone');
$controller->delete_checklist_item($item['id']);essentialCheck(json_decode($controller->output->body,true)['checklist']===$response['checklist'],'official delete replay preserves history');
$controller->input->body['action']='add';try{$controller->delete_checklist_item($item['id']);throw new RuntimeException('Conflicting action accepted');}catch(LogicException $e){essentialCheck($e->getMessage()==='HTTP 422','path action cannot be changed through the payload');}
echo 'Task checklist controllers: '.$GLOBALS['essentialChecks']." cumulative checks passed; original action writers and negative boundaries exercised.\n";
