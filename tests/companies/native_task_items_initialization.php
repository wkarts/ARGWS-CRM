<?php
require __DIR__.'/native_task_item_schema.php';
// Recreate a valid state after the deliberately damaged-schema migration check.
$db->query('ALTER TABLE '.$p.'company_project_checklist_state DROP COLUMN unexpected');unset($db->data_cache['table_names']);
require_once APPPATH.'services/NativeCompanyTaskItems.php';NativeCompanyTaskItemSchema::assertValid($db,$p);
$decoded=NativeCompanyResourceId::decode('task',$canonical);$id=$decoded['source_id'];$json='[{"text":"Antigo A","done":0},{"text":"Antigo B","done":1}]';$db->query('UPDATE '.$p.'company_standalone_tasks SET checklist_json=? WHERE issuer_id=? AND id=?',[$json,$a,$id]);
$reader=new NativeCompanyTaskItems($db,$p);$before=$reader->read($a,7,$canonical);essentialCheck(!$before['initialized']&&$before['historical_count']===2&&$before['items']===[],'Uninitialized historical items never gain fabricated IDs');
$row=$standalone->get($a,7,$id);$standalone->initializeNativeChecklist($a,7,$id,$row['revision'],$key('checklist-bootstrap'));$initialized=$reader->read($a,7,$canonical);essentialCheck($initialized['initialized']&&count($initialized['items'])===2&&array_column($initialized['items'],'description')===['Antigo A','Antigo B'],'Initialization preserves original checklist values and order');
essentialCheck(array_column($initialized['items'],'created_by')===[null,null]&&$standalone->get($a,7,$id)['checklist_json']===$json,'Unknown authors remain unknown and original JSON is unchanged');
essentialCheck(array_column($initialized['items'],'finished_by')===[null,null]&&array_column($initialized['items'],'assigned_id')===[null,null],'Historical finishers and assignments remain explicitly unknown');
$standalone->initializeNativeChecklist($a,7,$id,$row['revision'],$key('checklist-bootstrap'));essentialCheck($reader->read($a,7,$canonical)===$initialized,'Exact retry keeps assigned stable IDs');
essentialThrows(fn()=>$reader->read($b,7,$canonical),'native_company_task_denied','Another company cannot read checklist');
$db->query('UPDATE '.$p.'company_standalone_tasks SET checklist_json=? WHERE issuer_id=? AND id=?',['[{"text":"Alterado","done":0}]',$a,$id]);essentialThrows(fn()=>$reader->read($a,7,$canonical),'native_task_checklist_changed','External source change is detected instead of overwriting checklist');
echo 'Task checklist historical initialization: '.$GLOBALS['essentialChecks']." cumulative checks passed; UI and commands remain pending.\n";
