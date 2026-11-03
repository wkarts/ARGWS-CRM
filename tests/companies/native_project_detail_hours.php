<?php
/** Original chart is built from the scoped native timer projection. */
require __DIR__.'/native_project_timer_projection.php';
require_once APPPATH.'services/NativeCompanyProjectDetail.php';
if($db->driver()==='sqlite'){foreach(['company_tasks','company_standalone_tasks'] as$table)foreach(['native_fields_json'=>'TEXT','native_fields_revision'=>'INT'] as$field=>$type)if(!$db->field_exists($field,$p.$table))$db->query('ALTER TABLE '.$p.$table.' ADD '.$field.' '.$type.' NULL');}
else {require_once APPPATH.'services/NativeCompanyTaskFormSchema.php';NativeCompanyTaskFormSchema::ensure($db,$p);}
unset($db->data_cache['table_names']);
$detail=new NativeCompanyProjectDetail($db,$p);$view=$detail->hoursChart($a,7,$saved,'this_week',$clock+5);
$detailFacts=$detail->read($a,7,$saved);
essentialCheck($detailFacts['status']['state']==='in_progress','declared workflow is preserved in original project detail facts');
essentialCheck(($detailFacts['custom_values'][(string)$fieldId]??null)==='Native form required value'&&in_array((string)$fieldId,array_map('strval',array_column($detailFacts['custom_definitions'],'id')),true),'project detail retains authorized custom field definition and actual value');
$overview=$detail->overview($a,7,$saved,'this_week','_l',$clock+5);
essentialCheck($overview['task_metrics']['total']>=1&&$overview['logged_seconds']===$stopped['total_seconds'],'overview uses actual task and timer projection');
essentialCheck(($overview['finance']['project_cost']??null)==='123.45'&&$overview['status']['id']===$detailFacts['status']['id'],'overview returns authorized actual financial fields and workflow');
essentialCheck($overview['progress']===37,'manual progress uses the persisted value');
$metaBefore=$db->query('SELECT workflow_status FROM '.$p.'company_project_native_metadata WHERE issuer_id=? AND project_id=?',[$a,$projectSource])->row_array();
$db->query('UPDATE '.$p.'company_project_native_metadata SET workflow_status=4 WHERE issuer_id=? AND project_id=?',[$a,$projectSource]);
try{essentialThrows(fn()=>$detail->read($a,7,$saved),'native_project_workflow_lifecycle_conflict','inconsistent workflow fails closed');}
finally{$db->query('UPDATE '.$p.'company_project_native_metadata SET workflow_status=? WHERE issuer_id=? AND project_id=?',[$metaBefore['workflow_status'],$a,$projectSource]);}
essentialCheck($view===$chart,'detail uses original chart renderer and actual scoped timer facts');
essentialThrows(fn()=>$detail->hoursChart($b,7,$saved,'this_week',$clock+5),'native_resource_reference_denied','other company cannot render project hours');
essentialThrows(fn()=>$detail->hoursChart($a,7,$saved,'arbitrary',$clock+5),'native_project_chart_invalid','unrecognized chart range is rejected');
$db->query('INSERT INTO '.$p."issuers_resource_permissions VALUES(?,92,'tasks','view')",[$a]);
$db->query('DELETE FROM '.$p."issuers_resource_permissions WHERE issuer_id=? AND staff_id=92 AND feature='projects' AND capability='create'",[$a]);
$privateChart=$detail->hoursChart($a,92,$saved,'this_week',$clock+5);
essentialCheck(array_sum($privateChart['data']['datasets'][0]['data'])===0.0,'view-only actor sees no other staff timer in chart');
$privateOverview=$detail->overview($a,92,$saved,'this_week','_l',$clock+5);
essentialCheck($privateOverview['finance']===null&&$privateOverview['logged_seconds']===0,'view-only overview never returns project financial fields or other staff time');
$db->query('DELETE FROM '.$p."issuers_resource_permissions WHERE issuer_id=? AND staff_id=92 AND feature='tasks' AND capability='view'",[$a]);
$withoutTasks=$detail->overview($a,92,$saved,'this_week','_l',$clock+5);
essentialCheck($withoutTasks['task_metrics']===null&&$withoutTasks['chart']===null&&$withoutTasks['progress']===37,'no task access omits task metrics but retains declared manual progress');
$db->query('UPDATE '.$p.'company_project_native_metadata SET progress_from_tasks=1 WHERE issuer_id=? AND project_id=?',[$a,$projectSource]);
try{
    $derived=$detail->overview($a,7,$saved,'this_week','_l',$clock+5);
    essentialCheck($derived['progress']===$derived['task_metrics']['completed_percent'],'task-driven progress comes from actual authorized task statuses');
    $withoutDerived=$detail->overview($a,92,$saved,'this_week','_l',$clock+5);
    essentialCheck($withoutDerived['progress']===null,'task-derived progress is not invented without task authority');
}finally{$db->query('UPDATE '.$p.'company_project_native_metadata SET progress_from_tasks=0 WHERE issuer_id=? AND project_id=?',[$a,$projectSource]);}
echo "Project detail real timer chart and issuer isolation passed; route remains closed.\n";
