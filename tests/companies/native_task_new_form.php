<?php
require __DIR__.'/native_task_checklist_templates.php';require_once APPPATH.'services/NativeCompanyTaskForm.php';
function _l($key,...$args){return$key;}
$storage=new CompanySettingsStorage($db,$p);$storage->mutate($a,7,'add','dateformat','d/m/Y|%d/%m/%Y');$storage->mutate($b,7,'add','dateformat','d/m/Y|%d/%m/%Y');$form=new NativeCompanyTaskForm($db,$p);
$data=$form->newForm($a,92,[],'2026-10-25');essentialCheck($data['id']===''&&$data['start_date']==='25/10/2026'&&count($data['members'])===2,'New original form uses actual company date and eligible members');
essentialCheck(array_column($data['checklistTemplates'],'description')===['Actual explicit capability'],'Template picker uses real A catalog only');
essentialCheck($data['native_task_form']['options']['task_biillable_checked_on_creation']==='1','Absent setting uses original public creation default, not existing task classification');
$storage->mutate($a,7,'add','task_biillable_checked_on_creation','0');$next=$form->newForm($a,92,[],'2026-10-25');essentialCheck($next['native_task_form']['options']['task_biillable_checked_on_creation']==='0','Explicit zero overrides public default');
essentialCheck($form->newForm($b,7,[],'2026-10-25')['native_task_form']['options']['task_biillable_checked_on_creation']==='1','Another company does not inherit A preference');
$projectForm=$form->newForm($a,92,['rel_type'=>'project','rel_id'=>NativeCompanyResourceId::encode('company_project',$project)],'2026-10-25');essentialCheck($projectForm['native_task_form']['selected_relation']['id']===NativeCompanyResourceId::encode('company_project',$project),'Original project selector preserves canonical lineage');
essentialThrows(fn()=>$form->newForm($b,7,['rel_type'=>'project','rel_id'=>NativeCompanyResourceId::encode('company_project',$project)],'2026-10-25'),'native_resource_reference_denied','Other company project denied before form output');
$storage->mutate($a,7,'update','task_biillable_checked_on_creation','');essentialThrows(fn()=>$form->newForm($a,92,[],'2026-10-25'),'native_task_form_preference_invalid','Present empty preference is invalid, not absent/default');
echo 'Original new Task form source: '.$GLOBALS['essentialChecks']." cumulative checks passed.\n";
