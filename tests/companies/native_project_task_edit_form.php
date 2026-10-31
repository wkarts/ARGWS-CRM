<?php
require __DIR__.'/native_task_edit_form.php';
foreach(['CompanyProjectPortalRulesSchema','CompanyProjectMilestonesSchema','CompanyPortalOperationsSchema','NativeCompanyProjectMetadataSchema']as$class){require_once APPPATH.'services/'.$class.'.php';foreach($class::definitions($p)as$table=>$parts){if($db->table_exists($p.$table))continue;if($class==='CompanyPortalOperationsSchema'&&$table!=='company_portal_projects')continue;$db->query('CREATE TABLE '.$p.$table.'('.preg_replace('/ CHARACTER SET [A-Za-z0-9_]+ COLLATE [A-Za-z0-9_]+/','',implode(',',$parts)).')');}}
foreach(['contacts','contact_permissions']as$table)if(!$db->table_exists($p.$table)){preg_match('/CREATE TABLE `tbl'.preg_quote($table,'/').'` \(.*?\) ENGINE=InnoDB[^;]*;/s',$seed,$m);$sql=str_replace('`tbl'.$table.'`','`'.$p.$table.'`',$m[0]);if($db->driver()==='sqlite')$sql=preg_replace('/ CHARACTER SET [A-Za-z0-9_]+ COLLATE [A-Za-z0-9_]+/','',$sql);$db->query($sql);}
$projectTask=$source['source_id']??null;
// Exact source created by the original POST fixture, resolved again rather than assuming raw ID identity.
$canonicalProjectTask=$result['id'];$projection=$form->editForm($a,7,$canonicalProjectTask,'2026-10-25');
essentialCheck($projection['task']->rel_type==='project'&&$projection['task']->rel_id===NativeCompanyResourceId::encode('company_project',$project),'Project task editor retains exact canonical parent');
essentialCheck($projection['native_task_form']['can_client_view']===false&&$projection['native_task_form']['project_task_rate']===null,'Absent publication rules use real deny policy; unknown project billing metadata stays unknown');
$controller->input->verb='GET';$controller->input->body=[];\NativeTaskHttpTest\CompanyRequestScope::$actor=7;$controller->task($canonicalProjectTask);essentialCheck(str_contains($controller->output->body,'id="_task_modal"')&&str_contains($controller->output->body,'Original native task payload'),'Original controller renders project task without global model fallback');
essentialThrows(fn()=>$form->editForm($b,7,$canonicalProjectTask,'2026-10-25'),'native_resource_reference_denied','B cannot render A project task');
echo 'Original project task editor: '.$GLOBALS['essentialChecks']." cumulative checks passed.\n";
