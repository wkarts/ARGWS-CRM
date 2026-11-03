<?php
require __DIR__.'/native_task_form_http.php';
$templates=new NativeCompanyTaskChecklistTemplates($db,$p);$template=$templates->create($a,7,'Checklist from actual selected template',$key('selected-template'));
$createWire=$wire;$createWire['native_request_key']=$key('task-selected-template');$createWire['checklist_items']=[$template['id']];$taskId=$adapter->save($a,92,null,$createWire);$source=NativeCompanyResourceId::decode('task',$taskId);$task=$tasks->get($a,7,$source['source_id']);
essentialCheck($task['checklist']===[['text'=>'Checklist from actual selected template','done'=>0]],'Original selected template IDs compose actual unchecked items in canonical task');
$bad=$createWire;$bad['native_request_key']=$key('selected-template-cross');$foreign=$templates->create($b,7,'B secret template',$key('foreign-template'));$bad['checklist_items']=[$foreign['id']];essentialThrows(fn()=>$adapter->save($a,92,null,$bad),'native_task_template_denied','Foreign selected template rejected before task creation');
if($db->driver()==='sqlite'){
 $before=(int)$db->query('SELECT COUNT(*) AS n FROM '.$p.'company_tasks')->row_array()['n'];$db->query('CREATE TRIGGER corrupt_selected_template AFTER INSERT ON '.$p.'company_tasks BEGIN UPDATE '.$p."company_task_checklist_templates SET description='Changed after selection' WHERE id=".$template['source_id'].'; END');
 $bad=$createWire;$bad['native_request_key']=$key('selected-template-inflight');essentialThrows(fn()=>$adapter->save($a,92,null,$bad),'issuer_project_write_unconfirmed','Late template mutation prevents misleading create proof');$db->query('DROP TRIGGER corrupt_selected_template');essentialCheck((int)$db->query('SELECT COUNT(*) AS n FROM '.$p.'company_tasks')->row_array()['n']===$before&&$templates->get($a,7,$template['id'])===$template,'Late mutation rolls back task, template, tags, fields and receipt');
}
echo 'Task original template selection: '.$GLOBALS['essentialChecks']." cumulative checks passed.\n";
