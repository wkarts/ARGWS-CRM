<?php
require __DIR__.'/native_task_create_fields.php';require_once APPPATH.'services/NativeCompanyTaskForm.php';
$storage=new CompanySettingsStorage($db,$p);$storage->mutate($a,7,'add','dateformat','d/m/Y|%d/%m/%Y');$adapter=new NativeCompanyTaskForm($db,$p);
$wire=['name'=>'Original native task payload','description'=>'Safe description','startdate'=>'25/10/2026','duedate'=>'','priority'=>'3','hourly_rate'=>'9.50','billable'=>'on','rel_type'=>'project','rel_id'=>NativeCompanyResourceId::encode('company_project',$project),'assignees'=>['7','92'],'followers'=>[],'milestone'=>'','repeat_every'=>'','repeat_every_custom'=>'1','repeat_type_custom'=>'day','checklist_items'=>[],'tags'=>'Payload task tag','custom_fields'=>['tasks'=>[$fieldId=>'Payload required value']],'native_request_key'=>$key('form-payload')];
$wire['native_milestone_revision']=$tasks->nativeMilestoneSelection($a,92,$project)['revision'];
$canonical=$adapter->save($a,92,null,$wire);$decoded=NativeCompanyResourceId::decode('task',$canonical);$loaded=$tasks->get($a,7,$decoded['source_id']);
essentialCheck($decoded['source_kind']==='company_task'&&$loaded['name']===$wire['name']&&$loaded['due_date']===null,'Original form payload reaches existing owned writer and returns canonical task ID');
essentialCheck(json_decode($loaded['native_fields_json'],true)['billable']===1&&$loaded['priority']===3,'Checkbox and original priority persist without implicit global options');
essentialCheck($adapter->save($a,92,null,$wire)===$canonical,'Original form repeated POST replays one source');
$edit=$wire;unset($edit['assignees']);$edit['native_revision']='1';$edit['native_fields_revision']='1';$edit['native_tags_revision']=$tagReader->read($a,7,'task',$canonical)['revision'];$edit['native_request_key']=$key('form-edit');$edit['name']='Updated through same native form';$edit['tags']='Updated task tag';$edit['custom_fields']['tasks'][$fieldId]='Updated required value';
essentialCheck($adapter->save($a,7,$canonical,$edit)===$canonical,'Original edit payload retains exact task identity');
essentialCheck(array_column($tagReader->read($a,7,'task',$canonical)['tags'],'name')===['Updated task tag']&&$fieldReader->values($a,7,'tasks',$canonical)[$fieldId]==='Updated required value','Same edit commits real tags and custom fields with canonical task');
essentialThrows(fn()=>$adapter->save($a,92,$canonical,$edit),'native_task_form_denied','Create-only remains unable to edit');
$bad=$wire;$bad['startdate']='31/02/2026';$bad['native_request_key']=$key('invalid-date');essentialThrows(fn()=>$adapter->save($a,7,null,$bad),'native_task_form_date_invalid','Localized invalid date cannot normalize silently');
$bad=$wire;$bad['milestone']='123';$bad['native_request_key']=$key('uncomposed-milestone');essentialThrows(fn()=>$adapter->save($a,7,null,$bad),'company_project_reference_denied','Unknown milestone cannot be attached by raw ID coincidence');
echo 'Native task original payload: '.$GLOBALS['essentialChecks'].' cumulative checks passed. HTTP admission remains pending.'.PHP_EOL;
