<?php
require __DIR__.'/native_task_form_milestones.php';
$projectCanonical=NativeCompanyResourceId::encode('company_project',$project);$data=$form->projectData($a,7,$projectCanonical,$task);
essentialCheck(array_keys($data)===['deadline','deadline_formatted','allow_to_view_tasks','billing_type','milestones','assignees','native_milestone_revision'],'Original AJAX response contract retained with exact optimistic milestone proof');
essentialCheck($data['billing_type']===null&&$data['allow_to_view_tasks']===0&&$data['deadline']==='2026-12-01'&&$data['deadline_formatted']==='01/12/2026','No synthetic billing mode or publication; deadline uses actual project and company formatter');
essentialCheck(count($data['assignees'])===1&&str_contains($data['assignees'][0],'value="7"'),'Project selector contains demonstrated responsible/member only, not all company staff');
$db->query('UPDATE '.$p.'company_project_milestones SET name=? WHERE issuer_id=? AND project_id=? AND id=?',['<script>no markup</script>',$a,$project,$milestone]);$data=$form->projectData($a,7,$projectCanonical,$task);
essentialCheck(str_contains($data['milestones'][1],'&lt;script&gt;')&&!str_contains($data['milestones'][1],'<script>'),'Original option markup escapes stored labels');
essentialThrows(fn()=>$form->projectData($b,7,$projectCanonical),'native_resource_reference_denied','AJAX denies A project in B context');
essentialThrows(fn()=>$form->projectData($a,7,NativeCompanyResourceId::encode('company_project',$projectB),$task),'company_project_reference_denied','Task editor cannot hydrate unrelated selected project');
// Original Projects controller, context/framework boundaries only; no alternate route or model double.
eval('namespace NativeTaskHttpTest;use \\DomainException;use \\Throwable;use \\NativeCompanyTaskForm;'.substr(file_get_contents(APPPATH.'controllers/admin/Projects.php'),5));
$projectsController=(new ReflectionClass(\NativeTaskHttpTest\Projects::class))->newInstanceWithoutConstructor();foreach(get_object_vars($controller)as$property=>$value)$projectsController->$property=$value;
\NativeTaskHttpTest\CompanyRequestScope::$issuer=$a;\NativeTaskHttpTest\CompanyRequestScope::$actor=7;$projectsController->input->verb='GET';$projectsController->get_rel_project_data($projectCanonical,$task);$response=json_decode($projectsController->output->body,true,512,JSON_THROW_ON_ERROR);
essentialCheck($response===$data,'Original GET controller returns the tested scoped DTO without global model hydration');
\NativeTaskHttpTest\CompanyRequestScope::$issuer=$b;try{$projectsController->get_rel_project_data($projectCanonical,$task);throw new RuntimeException('Foreign project data accepted');}catch(LogicException$e){essentialCheck($e->getMessage()==='HTTP 422','Original project selector rejects another company project');}\NativeTaskHttpTest\CompanyRequestScope::$issuer=$a;
$projectsController->input->verb='POST';try{$projectsController->get_rel_project_data($projectCanonical,$task);throw new RuntimeException('POST reader accepted');}catch(LogicException$e){essentialCheck($e->getMessage()==='HTTP 405','Read-only relation endpoint rejects mutation method');}
echo 'Original project task data endpoint: '.$GLOBALS['essentialChecks']." cumulative checks passed.\n";
