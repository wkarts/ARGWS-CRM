<?php
require __DIR__.'/native_project_task_edit_form.php';
$extras=new CompanyProjectExtras($db,$p);$parent=$ps->get($a,7,$project);
$extras->milestone($a,7,$project,(int)$parent['revision'],$key('native-selector-milestone'),['name'=>'Original milestone','due_date'=>'2026-11-20','done'=>0]);
$milestone=(int)$db->query('SELECT id FROM '.$p.'company_project_milestones WHERE issuer_id=? AND project_id=? AND name=?',[$a,$project,'Original milestone'])->row()->id;
$selection=$tasks->nativeMilestoneSelection($a,7,$project);$create=$wire;$create['native_request_key']=$key('create-with-milestone');$create['native_milestone_revision']=$selection['revision'];$create['milestone']=(string)$milestone;
$db->query('INSERT INTO '.$p.'issuers_resource_permissions(issuer_id,staff_id,feature,capability) VALUES(?,92,?,?)',[$a,'tasks','create']);
$count=(int)$db->query('SELECT COUNT(*) n FROM '.$p.'company_tasks')->row()->n;
essentialThrows(fn()=>$form->save($a,92,null,$create),'company_project_portal_access_denied','Task create alone cannot gain existing projects.edit milestone authority');
essentialCheck((int)$db->query('SELECT COUNT(*) n FROM '.$p.'company_tasks')->row()->n===$count,'Denied association rolls back task, tags and metadata');
$task=$form->save($a,7,null,$create);$raw=NativeCompanyResourceId::decode('task',$task)['source_id'];
$map=fn()=>$db->query('SELECT milestone_id FROM '.$p.'company_task_milestones WHERE issuer_id=? AND project_id=? AND task_id=?',[$a,$project,$raw])->row_array();
essentialCheck((int)$map()['milestone_id']===$milestone,'Original task form writes existing milestone mapping atomically');
essentialCheck($form->save($a,7,null,$create)===$task,'Same form request replays exact creation despite changed milestone hash');
essentialCheck(!$db->query('SELECT * FROM '.$p.'company_milestone_details WHERE issuer_id=? AND project_id=? AND milestone_id=?',[$a,$project,$milestone])->row_array(),'No invented start date or description for a historical basic milestone');
$editDto=$form->editForm($a,7,$task,'2026-10-25');$edit=$create;unset($edit['assignees']);$edit=array_replace($edit,$editDto['native_task_form']['fields']);$edit['native_request_key']=$key('keep-milestone');$edit['name']='Edited with same milestone';$form->save($a,7,$task,$edit);
essentialCheck((int)$map()['milestone_id']===$milestone&&$tasks->get($a,7,$raw)['name']===$edit['name'],'Existing milestone no longer prevents original edit');
$db->query('INSERT INTO '.$p.'company_milestone_details(issuer_id,project_id,milestone_id,start_date,description,description_visible,sort_order,revision,actor_id,updated_at) VALUES(?,?,?, ?,?,0,1,1,7,?)',[$a,$project,$milestone,'2026-10-25','Actual explicit milestone details','2026-10-25 09:00:00']);
$editDto=$form->editForm($a,7,$task,'2026-10-25');$remove=array_replace($edit,$editDto['native_task_form']['fields']);$remove['native_request_key']=$key('remove-milestone');$remove['milestone']='';$form->save($a,7,$task,$remove);essentialCheck(!$map(),'Clearing original selector removes only the selected task mapping');
$detail=$db->query('SELECT * FROM '.$p.'company_milestone_details WHERE issuer_id=? AND project_id=? AND milestone_id=?',[$a,$project,$milestone])->row_array();essentialCheck((int)$detail['revision']===2&&$detail['start_date']==='2026-10-25'&&$detail['description']==='Actual explicit milestone details','Existing details advance revision without changing their declared dates or content');
$editDto=$form->editForm($a,7,$task,'2026-10-25');$stale=array_replace($edit,$editDto['native_task_form']['fields']);$stale['native_request_key']=$key('stale-milestone');
$db->query('UPDATE '.$p.'company_project_milestones SET name=? WHERE issuer_id=? AND project_id=? AND id=?',['Changed by another action',$a,$project,$milestone]);$before=$tasks->get($a,7,$raw);
essentialThrows(fn()=>$form->save($a,7,$task,$stale),'native_task_milestone_stale','Form rejects changed milestone source before association');essentialCheck($tasks->get($a,7,$raw)===$before,'Stale milestone also rolls back canonical task update');
$latest=$form->editForm($a,7,$task,'2026-10-25');$bad=array_replace($edit,$latest['native_task_form']['fields']);$bad['native_request_key']=$key('missing-milestone');$bad['milestone']='2147483646';essentialThrows(fn()=>$form->save($a,7,$task,$bad),'company_project_reference_denied','Unrelated/absent source is not assigned by raw ID');
$parentB=$ps->get($b,7,$projectB);$extras->milestone($b,7,$projectB,(int)$parentB['revision'],$key('B-native-selector'),['name'=>'B milestone','due_date'=>'2026-11-20','done'=>0]);$foreign=(int)$db->query('SELECT id FROM '.$p.'company_project_milestones WHERE issuer_id=? AND project_id=?',[$b,$projectB])->row()->id;$bad['milestone']=(string)$foreign;$bad['native_request_key']=$key('foreign-milestone');essentialThrows(fn()=>$form->save($a,7,$task,$bad),'company_project_reference_denied','Actual B milestone denied even with A task authority');
if($db->driver()==='sqlite'){
 $mutate=array_replace($edit,$latest['native_task_form']['fields']);$mutate['native_request_key']=$key('milestone-audit-tamper');
 $db->query('CREATE TRIGGER native_milestone_tamper AFTER INSERT ON '.$p.'issuers_audit WHEN NEW.event=\'company_task_native_updated\' BEGIN UPDATE '.$p.'company_project_milestones SET name=\'tampered\' WHERE issuer_id='.$a.' AND id='.$milestone.'; END');
 essentialThrows(fn()=>$form->save($a,7,$task,$mutate),'issuer_project_write_unconfirmed','Final frame rejects milestone change after task audit');$db->query('DROP TRIGGER native_milestone_tamper');essentialCheck($tasks->get($a,7,$raw)===$before&&!$map(),'Late mutation rolls back task and milestone mapping together');
 $db->query('INSERT INTO '.$p.'issuers_resource_permissions(issuer_id,staff_id,feature,capability) VALUES(?,92,?,?)',[$a,'projects','edit']);$revoked=$create;$revoked['native_request_key']=$key('milestone-authority-revoked');$revoked['native_milestone_revision']=$tasks->nativeMilestoneSelection($a,92,$project)['revision'];
 $db->query('CREATE TRIGGER native_milestone_revoke AFTER INSERT ON '.$p.'issuers_audit WHEN NEW.event=\'company_task_native_created\' AND NEW.actor_id=92 BEGIN DELETE FROM '.$p.'issuers_resource_permissions WHERE issuer_id='.$a.' AND staff_id=92 AND feature=\'projects\' AND capability=\'edit\'; END');
 essentialThrows(fn()=>$form->save($a,92,null,$revoked),'company_project_portal_access_denied','Milestone authority revoked after audit is checked before commit');$db->query('DROP TRIGGER native_milestone_revoke');
}
echo 'Original task milestone selector: '.$GLOBALS['essentialChecks']." cumulative checks passed.\n";
