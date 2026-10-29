<?php
/** Original copy endpoint plus real project/task/member/metadata writers, on disposable storage. */
require __DIR__.'/native_project_form.php';
// The included form suite has already checked its query history. Release only
// that diagnostic trace before the independent copy requests so PHP 8.1's
// 128 MiB limit covers the operation, not all previously completed fixtures.
$db->queries=[];
require_once APPPATH.'services/NativeCompanyProjectCopyHttp.php';
if($db->driver()==='mysql'){NativeCompanyTaskFormSchema::ensure($db,$p);CompanyProjectCopiesSchema::ensure($db,$p);}else{
 foreach(NativeCompanyTaskFormSchema::TABLES as$table){
  $name=$p.$table;$old=$db->query("SELECT sql FROM sqlite_master WHERE type='table' AND name=?",[$name])->row_array()['sql'];
  $ddl=preg_replace('/`?assignee_id`? int NOT NULL/i','`assignee_id` int NULL',$old);$ddl=preg_replace('/`?due_date`? date NOT NULL/i','`due_date` date NULL',$ddl);
  $ddl=preg_replace('/\)\s*$/',',`native_fields_json` longtext NULL,`native_fields_revision` int unsigned NULL)',$ddl);
  // SQLite final-shape fixture only. MySQL runs the production additive ALTER.
  $ddl=preg_replace('/,`native_fields_json` longtext NULL,`native_fields_revision` int unsigned NULL\)$/',')',$ddl);
  $ddl=preg_replace('/,\s*(PRIMARY KEY|UNIQUE|FOREIGN KEY)/',',`native_fields_json` longtext NULL,`native_fields_revision` int unsigned NULL,$1',$ddl,1);
  $ddl=preg_replace('/^CREATE TABLE\s+`?'.preg_quote($name,'/').'`?/i','CREATE TABLE `'.$name.'_shape`',$ddl);
  $indexes=$db->query("SELECT sql FROM sqlite_master WHERE type='index' AND tbl_name=? AND sql IS NOT NULL",[$name])->result_array();
  $columns=array_column($db->query('PRAGMA table_info('.$name.')')->result_array(),'name');$db->query('PRAGMA foreign_keys=OFF');$db->query($ddl);$db->query('INSERT INTO '.$name.'_shape(`'.implode('`,`',$columns).'`) SELECT `'.implode('`,`',$columns).'` FROM '.$name);$db->query('DROP TABLE '.$name);$db->query('ALTER TABLE '.$name.'_shape RENAME TO '.$name);foreach($indexes as$index)$db->query($index['sql']);$db->query('PRAGMA foreign_keys=ON');
 }
 foreach(CompanyProjectCopiesSchema::definitions($p)as$table=>$parts)$db->query('CREATE TABLE '.$p.$table.'('.preg_replace('/ CHARACTER SET [a-z0-9_]+ COLLATE [a-z0-9_]+/','',implode(',',$parts)).')');
}
unset($db->data_cache['table_names']);NativeCompanyTaskFormSchema::assertValid($db,$p);CompanyTaskPrioritySchema::ensure($db,$p);
$db->query("INSERT INTO {$p}issuers_resource_permissions(issuer_id,staff_id,feature,capability) VALUES(?,92,'tasks','view')",[$a]);
$writer=new CompanyTasks($db,$p);$copySource=$withState;$srcCanonical=NativeCompanyResourceId::encode('company_project',$copySource);
$taskInput=['project_id'=>$copySource,'assignee_id'=>7,'name'=>'Copied native child','description'=>'Literal child','due_date'=>'2026-11-01','status'=>'testing','checklist'=>[['text'=>'Review source','done'=>0]],'priority'=>3];
$taskFields=['start_date'=>'2026-10-25','billable'=>1,'hourly_rate'=>'12.50','is_public'=>0,'visible_to_client'=>1,'workflow_status'=>3,'date_finished'=>null,'relation_type'=>'project','relation_source_kind'=>'company_project','relation_source_id'=>$copySource];
$sourceTask=$writer->createNative($a,7,$taskInput,$taskFields,$tk('copy-native-child'),['assignees'=>[7,92],'followers'=>[92],'tags'=>['Source child tag'],'custom_fields'=>[]]);
if(!class_exists('Projects',false))require APPPATH.'controllers/admin/Projects.php';
$copyController=new Projects();$GLOBALS['CI']->input=new class{public string $verb='POST';public array $body=[];function method($u=true){return$this->verb;}function post($k=null,$f=false){return$k===null?$this->body:($this->body[$k]??null);}};$GLOBALS['CI']->security=new class{function get_csrf_token_name(){return'csrf_token';}};
CompanyRequestScope::$actor=7;
CompanyRequestScope::$issuer=$a;$body=['name'=>'Original POST copied project','clientid_copy_project'=>(string)$client,'start_date'=>'25/11/2026','deadline'=>'31/01/2027','tasks'=>'on','milestones'=>'on','members'=>'on','tasks_include_checklist_items'=>'on','task_include_assignees'=>'on','task_include_followers'=>'on','copy_project_task_status'=>'3','native_copy_request_key'=>$tk('original-copy-post')];$copyController->input->body=$body;
$before=(new CompanyProjectCopies($db,$p))->preview($a,7,$copySource,true);$copyController->copy($srcCanonical);$location=[$GLOBALS['taskBillingRedirect'],$GLOBALS['CI']->output->status];$target=NativeCompanyResourceId::decode('project',basename($location[0]))['source_id'];
essentialCheck($location[1]===303&&$target!==$copySource,'Original Projects::copy POST redirects to a distinct canonical project');
$targetDto=$adapter->read($a,7,NativeCompanyResourceId::encode('company_project',$target));$sourceDto=$adapter->read($a,7,$srcCanonical);
essentialCheck($targetDto['project']->name===$body['name']&&count($targetDto['project_members'])===2&&$targetDto['native_project_form']['tags']===$sourceDto['native_project_form']['tags'],'Copy preserves selected members and actual tags inside original project form');
$receipt=(new CompanyProjectCopies($db,$p))->receipt($a,7,$body['native_copy_request_key']);$child=$writer->get($a,7,$receipt['task_map'][$sourceTask]);$fields=json_decode($child['native_fields_json'],true);
essentialCheck($child['status']==='testing'&&$fields['start_date']==='2026-12-19'&&$fields['hourly_rate']==='12.50'&&$fields['relation_source_id']===$target&&count($child['checklist'])===1,'Native task phase, shifted dates, rate and remapped relation survive the original copy options');
$members=$db->query('SELECT staff_id FROM '.$p.'company_task_assignees WHERE issuer_id=? AND task_id=? AND active=1 ORDER BY staff_id',[$a,$child['id']])->result_array();$followers=$db->query('SELECT staff_id FROM '.$p.'company_task_followers WHERE issuer_id=? AND task_id=?',[$a,$child['id']])->result_array();essentialCheck(array_map('intval',array_column($members,'staff_id'))===[7,92]&&array_map('intval',array_column($followers,'staff_id'))===[92],'Explicit assignees and followers remain distinct and owned');
$copyController->copy($srcCanonical);essentialCheck([$GLOBALS['taskBillingRedirect'],$GLOBALS['CI']->output->status]===$location,'Exact original POST replay converges without duplicate copy');
$copyError=function(callable $f,int $status,string $label){$GLOBALS['CI']->output->status=200;$f();essentialCheck($GLOBALS['CI']->output->status===$status,$label);};
CompanyRequestScope::$valid=false;$copyError(fn()=>$copyController->copy($srcCanonical),403,'Original copy refuses invalid native CSRF');CompanyRequestScope::$valid=true;CompanyRequestScope::$issuer=$b;$copyError(fn()=>$copyController->copy($srcCanonical),403,'Original copy refuses another company source');CompanyRequestScope::$issuer=$a;
$bad=$body;$bad['native_copy_request_key']=$tk('invalid-copy-date');$bad['start_date']='31/02/2026';$copyController->input->body=$bad;$copyError(fn()=>$copyController->copy($srcCanonical),422,'Invalid localized date has no copy effect');
$count=(int)$db->query('SELECT COUNT(*) n FROM '.$p.'company_projects')->row()->n;$bad=$body;$bad['native_copy_request_key']=$tk('copy-audit-failure');$copyController->input->body=$bad;$db->failOn='INSERT INTO '.$p.'company_project_copy_snapshots';try{$copyError(fn()=>$copyController->copy($srcCanonical),503,'Late copy receipt failure aborts the composition');}finally{$db->failOn=null;}
essentialCheck((int)$db->query('SELECT COUNT(*) n FROM '.$p.'company_projects')->row()->n===$count&&(new CompanyProjectCopies($db,$p))->preview($a,7,$copySource,true)===$before,'Rollback removes new project and children and preserves the full source snapshot');
echo 'Original project copy POST: native metadata, members, tasks, replay, isolation, CSRF, validation and rollback passed ('.$db->driver().').'.PHP_EOL;
$legacyChild=$writer->create($a,7,['project_id'=>$copySource,'assignee_id'=>7,'name'=>'Historical child without native fields','description'=>'Legacy metadata remains absent','due_date'=>'2026-11-15','status'=>'todo','checklist'=>[],'priority'=>2],$tk('legacy-copy-child'));
$minimal=$body;$minimal['native_copy_request_key']=$tk('copy-without-assignments');foreach(['members','tasks_include_checklist_items','task_include_assignees','task_include_followers']as$flag)unset($minimal[$flag]);$minimal['copy_project_task_status']='1';$copyController->input->body=$minimal;$copyController->copy($srcCanonical);$minimalReceipt=(new CompanyProjectCopies($db,$p))->receipt($a,7,$minimal['native_copy_request_key']);$newLegacy=$writer->get($a,7,$minimalReceipt['task_map'][$legacyChild]);
essentialCheck($newLegacy['native_fields_json']===null&&$newLegacy['native_fields_revision']===null&&$newLegacy['assignee_id']===null,'Copy preserves missing historical billing metadata and actual unassigned state');
essentialCheck((int)$db->query('SELECT COUNT(*) n FROM '.$p.'company_project_members WHERE project_id=?',[$newLegacy['project_id']])->row()->n===0&&(int)$db->query('SELECT COUNT(*) n FROM '.$p.'company_task_followers WHERE task_id=?',[$minimalReceipt['task_map'][$sourceTask]])->row()->n===0,'Unselected members and followers are not copied');
$empty=$minimal;$empty['native_copy_request_key']=$tk('copy-project-only');unset($empty['tasks'],$empty['milestones']);$copyController->input->body=$empty;$copyController->copy($srcCanonical);$emptyReceipt=(new CompanyProjectCopies($db,$p))->receipt($a,7,$empty['native_copy_request_key']);essentialCheck($emptyReceipt['task_map']===[],'Original project-only copy does not manufacture tasks');
CompanyRequestScope::$actor=92;$copyError(fn()=>$copyController->copy($srcCanonical),403,'Member without project create permission cannot replay another actor copy');CompanyRequestScope::$actor=7;
echo 'Original project copy optional sections and historical metadata preservation passed.'.PHP_EOL;
