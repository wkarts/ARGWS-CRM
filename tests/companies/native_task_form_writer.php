<?php
/** Existing canonical writers on real disposable PDO; HTTP/browser are separate acceptance gates. */
require __DIR__.'/invoices.php';
require_once APPPATH.'services/CompanyProjects.php';require_once APPPATH.'services/CompanyTasks.php';require_once APPPATH.'services/CompanyStandaloneTasks.php';
foreach([CompanyProjectsSchema::class,CompanyProjectExtrasSchema::class,CompanyStandaloneTasksSchema::class]as$schema){
 if($db->driver()==='mysql')$schema::ensure($db,$p);
 else foreach($schema::definitions($p)as$table=>$parts){
  if(in_array($table,NativeCompanyTaskFormSchema::TABLES,true)){
   $parts=array_map(static fn($v)=>str_replace(['`assignee_id` int NOT NULL','`due_date` date NOT NULL'],['`assignee_id` int NULL','`due_date` date NULL'],$v),$parts);
   $at=0;while(isset($parts[$at])&&str_starts_with($parts[$at],'`'))$at++;array_splice($parts,$at,0,['`native_fields_json` longtext NULL','`native_fields_revision` int unsigned NULL']);
  }
  $db->query('CREATE TABLE '.$p.$table.'('.preg_replace('/ CHARACTER SET [A-Za-z0-9_]+ COLLATE [A-Za-z0-9_]+/','',implode(',',$parts)).')');
 }
}
unset($db->data_cache['table_names']);NativeCompanyTaskFormSchema::ensure($db,$p);CompanyTaskPrioritySchema::ensure($db,$p);
if($db->driver()==='sqlite'){$db->query('CREATE UNIQUE INDEX native_fixture_task_origin ON '.$p.'company_tasks(issuer_id,project_id,id)');$db->query('CREATE UNIQUE INDEX native_fixture_milestone_origin ON '.$p.'company_project_milestones(issuer_id,project_id,id)');}
if($db->driver()==='mysql'){require_once APPPATH.'services/CompanyProjectPortalRulesSchema.php';CompanyProjectPortalRulesSchema::ensure($db,$p);}
require_once APPPATH.'services/CompanyProjectMilestonesSchema.php';if($db->driver()==='mysql')CompanyProjectMilestonesSchema::ensure($db,$p);else foreach(CompanyProjectMilestonesSchema::definitions($p)as$table=>$parts)if(!$db->table_exists($p.$table))$db->query('CREATE TABLE '.$p.$table.'('.preg_replace('/ CHARACTER SET [A-Za-z0-9_]+ COLLATE [A-Za-z0-9_]+/','',implode(',',$parts)).')');
require_once APPPATH.'services/NativeCompanyStandaloneFollowersSchema.php';NativeCompanyStandaloneFollowersSchema::ensure($db,$p);
$ps=new CompanyProjects($db,$p);$tasks=new CompanyTasks($db,$p);$standalone=new CompanyStandaloneTasks($db,$p);$key=fn($v)=>hash('sha256','native-task-form-'.$v);
$project=$ps->create($a,7,['client_id'=>$ca,'responsible_id'=>7,'name'=>'Original task form','description'=>'','start_date'=>'2026-10-01','due_date'=>'2026-12-01'],$key('project'));
$input=['project_id'=>$project,'assignee_id'=>null,'name'=>'Unassigned native task','description'=>'','due_date'=>null,'status'=>'testing','checklist'=>[]];
$fields=['start_date'=>'2026-10-25','billable'=>1,'hourly_rate'=>'12.50','is_public'=>0,'visible_to_client'=>1,'workflow_status'=>3,'date_finished'=>null,'relation_type'=>'project','relation_source_kind'=>'company_project','relation_source_id'=>$project];
$id=$tasks->createNative($a,7,$input,$fields,$key('create'));$row=$tasks->get($a,7,$id);
essentialCheck($row['assignee_id']===null&&$row['due_date']===null&&$row['status']==='testing','Actual NULL and distinct native testing state persisted, without invented assignee/date');
essentialCheck(json_decode($row['native_fields_json'],true)['hourly_rate']==='12.50'&&(int)$row['native_fields_revision']===1,'Explicit native declaration and revision persisted');
essentialCheck($tasks->createNative($a,7,$input,$fields,$key('create'))===$id,'Native create receipt is idempotent');
essentialThrows(fn()=>$tasks->createNative($a,7,$input,array_replace($fields,['hourly_rate'=>'13.00']),$key('create')),'company_project_request_conflict','Changed payload cannot reuse create receipt');
essentialThrows(fn()=>$tasks->createNative($b,7,$input,$fields,$key('cross')),'company_project_access_denied','Project from another issuer denied');
$legacy=array_replace($input,['assignee_id'=>7,'due_date'=>'2026-11-01','status'=>'todo']);
essentialThrows(fn()=>$tasks->create($a,7,$input,$key('old-null')),'issuer_invalid_id','Historical API still rejects missing assignee');
essentialThrows(fn()=>$tasks->update($a,7,$id,1,$legacy,$key('old-update')),'native_task_form_writer_required','Historical update cannot silently leave declared native fields stale');
$nextInput=array_replace($input,['status'=>'awaiting_feedback','name'=>'Awaiting real feedback']);$nextFields=array_replace($fields,['workflow_status'=>2]);
essentialCheck($tasks->updateNative($a,7,$id,1,1,$nextInput,$nextFields,$key('update'))===$id,'Native update uses same guarded canonical writer');
$before=$tasks->get($a,7,$id);essentialCheck($before['status']==='awaiting_feedback'&&(int)$before['revision']===2&&(int)$before['native_fields_revision']===2,'Task and native declaration revisions advance atomically');
essentialThrows(fn()=>$tasks->updateNative($a,7,$id,2,1,$input,$fields,$key('stale')),'company_project_stale','Stale declaration revision rejected');
$db->failOn='UPDATE '.$p.'company_tasks SET native_fields_json=';essentialThrows(fn()=>$tasks->updateNative($a,7,$id,2,2,$input,$fields,$key('failure')),'issuer_storage_failed','Metadata write failure surfaces');$db->failOn=null;
essentialCheck($tasks->get($a,7,$id)===$before,'Metadata failure rolls back canonical task change and receipt');
$standInput=$input;unset($standInput['project_id']);$standFields=array_replace($fields,['relation_type'=>null,'relation_source_kind'=>null,'relation_source_id'=>null]);
$sid=$standalone->createNative($a,7,$standInput,$standFields,$key('standalone'));essentialCheck($standalone->get($a,7,$sid)['assignee_id']===null,'Standalone uses existing canonical storage with real unassigned state');
$standalone->activity($a,7,$sid,1,'start',$key('native-start'));$standalone->activity($a,7,$sid,2,'stop',$key('native-stop'));$timed=$standalone->get($a,7,$sid);essentialCheck($timed['status']==='testing'&&count($timed['timers'])===1&&$timed['timers'][0]['stopped_at']!==null,'Existing timer writer preserves real testing state and NULL assignee/deadline');
require_once APPPATH.'services/NativeCompanyWorkAssignments.php';NativeCompanyWorkAssignmentsSchema::ensure($db,$p);
foreach(['firstname','lastname']as$column)if(!$db->field_exists($column,$p.'staff'))$db->query('ALTER TABLE '.$p.'staff ADD '.$column.' varchar(100) NULL');
$db->query("INSERT INTO {$p}staff(staffid,active,admin) VALUES(92,1,0)");$db->query("INSERT INTO {$p}issuers_staff(issuer_id,staff_id,can_view) VALUES(?,92,1)",[$a]);
foreach([['tasks','view'],['tasks','create'],['projects','view']]as[$feature,$cap])$db->query("INSERT INTO {$p}issuers_resource_permissions(issuer_id,staff_id,feature,capability) VALUES(?,92,?,?)",[$a,$feature,$cap]);
$created=$tasks->createNative($a,92,$input,$fields,$key('multi-create-only'),['assignees'=>[7,92]]);
$assigned=$db->query('SELECT staff_id FROM '.$p.'company_task_assignees WHERE issuer_id=? AND task_id=? AND active=1 ORDER BY staff_id',[$a,$created])->result_array();
essentialCheck(array_map('intval',array_column($assigned,'staff_id'))===[7,92]&&$tasks->get($a,92,$created)['assignee_id']===null,'Create-only assigns exact multiple members without inventing a singleton responsible');
essentialCheck(!(new IssuerResourceAccess($db,$p))->can($a,92,'tasks','edit'),'Positive compositor did not grant edit permission');
essentialThrows(fn()=>(new NativeCompanyWorkAssignments($db,$p))->createTaskMembersInOwnedTransaction($a,92,'task',$created,[92],new stdClass()),'native_task_creation_proof_denied','Manufactured creation proof cannot edit memberships');
$projectB=$ps->create($b,7,['client_id'=>$cb,'responsible_id'=>7,'name'=>'B project','description'=>'','start_date'=>'2026-10-01','due_date'=>'2026-12-01'],$key('project-b'));
$beforeCount=(int)$db->query('SELECT COUNT(*) n FROM '.$p.'company_tasks')->row()->n;
essentialThrows(fn()=>$tasks->createNative($b,7,array_replace($input,['project_id'=>$projectB]),array_replace($fields,['relation_source_id'=>$projectB]),$key('cross-member'),['assignees'=>[92]]),'native_assignment_denied','Cross-company member denied even with an authorized B project');
$db->failOn='INSERT INTO '.$p.'company_task_assignees';essentialThrows(fn()=>$tasks->createNative($a,92,$input,$fields,$key('member-failure'),['assignees'=>[92]]),'issuer_storage_failed','Membership write failure rolls back native task creation');$db->failOn=null;
essentialCheck((int)$db->query('SELECT COUNT(*) n FROM '.$p.'company_tasks')->row()->n===$beforeCount,'No partial canonical task or declaration survives member failure');
echo 'Native task original writer: '.$GLOBALS['essentialChecks'].' cumulative checks passed ('.$db->driver().").\n";
