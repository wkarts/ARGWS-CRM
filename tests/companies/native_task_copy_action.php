<?php
require __DIR__.'/native_task_original_actions.php';
require_once APPPATH.'services/CompanyTaskCopies.php';
if($db->driver()==='mysql')CompanyTaskCopiesSchema::ensure($db,$p);
else foreach(CompanyTaskCopiesSchema::definitions($p)as$table=>$parts){$parts=array_values(array_filter($parts,static fn($part)=>!str_starts_with($part,'KEY ')));$sql=preg_replace('/ CHARACTER SET [A-Za-z0-9_]+ COLLATE [A-Za-z0-9_]+/','',implode(',',$parts));$db->query('CREATE TABLE '.$p.$table.'('.$sql.')');}
unset($db->data_cache['table_names']);register_shutdown_function(static function()use($db,$p){if($db->driver()==='mysql')$db->pdo->exec('DROP TABLE IF EXISTS '.$p.'company_task_copy_requests');});
\NativeTaskHttpTest\CompanyRequestScope::$issuer=$a;\NativeTaskHttpTest\CompanyRequestScope::$actor=7;
$copies=new CompanyTaskCopies($db,$p);$preview=$copies->preview($a,7,'project',$taskId);$source=$tasks->get($a,7,$taskId);$checks=$GLOBALS['essentialChecks'];
$body=['copy_from'=>$canonical,'source_revision'=>$preview['revision'],'native_request_key'=>$key('native-task-copy'),'name'=>'Original copy with tags and values','start_date'=>'2026-10-26','due_date'=>'','copy_task_status'=>'3','copy_task_assignees'=>'1','copy_task_followers'=>'1','copy_task_checklist_items'=>'1','_native_company_csrf'=>'synthetic-csrf'];
$controller->input->body=$body;$controller->copy();$receipt=$copies->receipt($a,7,$body['native_request_key']);$target=$tasks->get($a,7,$receipt['task_id']);$targetCanonical=NativeCompanyResourceId::encode('company_task',$receipt['task_id']);
essentialCheck($controller->output->status===303&&in_array('Location: https://crm.invalid/sub/admin/tasks/list_tasks/'.$targetCanonical,$controller->output->headers,true),'Original copy POST redirects 303 to the canonical new task');
essentialCheck($target['name']===$body['name']&&$target['status']==='testing'&&$target['due_date']===null&&$target['description']===$source['description'],'Copy respects chosen state, null deadline and stored content');
essentialCheck(json_decode($target['native_fields_json'],true)['hourly_rate']===json_decode($source['native_fields_json'],true)['hourly_rate'],'Copy preserves the actual native rate');
essentialCheck(array_column((new NativeCompanyResourceTags($db,$p))->read($a,7,'task',$targetCanonical)['tags'],'name')===array_column((new NativeCompanyResourceTags($db,$p))->read($a,7,'task',$canonical)['tags'],'name'),'Copy preserves real source tags');
essentialCheck((new NativeCompanyCustomFields($db,$p))->storedInput($a,7,'tasks',$targetCanonical)===(new NativeCompanyCustomFields($db,$p))->storedInput($a,7,'tasks',$canonical),'Copy preserves actual custom-field values');
essentialCheck(array_map('intval',array_column((new NativeCompanyWorkAssignments($db,$p))->read($a,7,'task',$receipt['task_id'])['members'],'staffid'))===[7,92],'Original assignee choice copies current authorized members');
essentialCheck($tasks->get($a,7,$taskId)===$source,'Copy leaves the entire source unchanged');
$controller->copy();essentialCheck($copies->receipt($a,7,$body['native_request_key'])===$receipt,'Exact original copy replay returns the same durable target');
$controller->input->body['name']='Different intent';try{$controller->copy();throw new RuntimeException('Different replay accepted');}catch(LogicException$e){essentialCheck($e->getMessage()==='HTTP 409','Same copy key cannot change intent');}
$controller->input->body=$body;$controller->input->body['native_request_key']=$key('native-task-copy-rollback');$before=$db->query('SELECT * FROM '.$p.'company_tasks ORDER BY id')->result_array();$db->failOn='INSERT INTO '.$p.'company_task_copy_requests';
try{$controller->copy();throw new RuntimeException('Missing copy receipt accepted');}catch(LogicException$e){essentialCheck($e->getMessage()==='HTTP 503','Copy receipt failure is surfaced');}finally{$db->failOn=null;}
essentialCheck($db->query('SELECT * FROM '.$p.'company_tasks ORDER BY id')->result_array()===$before,'Late copy failure rolls back the complete new task');
$controller->input->body=$body;\NativeTaskHttpTest\CompanyRequestScope::$issuer=$b;try{$controller->copy();throw new RuntimeException('Foreign source copied');}catch(LogicException$e){essentialCheck($e->getMessage()==='HTTP 403','Original copy resolves active company ownership');}
\NativeTaskHttpTest\CompanyRequestScope::$issuer=$a;unset($controller->input->body['_native_company_csrf']);try{$controller->copy();throw new RuntimeException('Missing CSRF accepted');}catch(LogicException$e){essentialCheck($e->getMessage()==='HTTP 403','Original copy requires CSRF');}
echo 'Original task copy composition: '.($GLOBALS['essentialChecks']-$checks).' additional checks passed ('.$db->driver().").\n";
