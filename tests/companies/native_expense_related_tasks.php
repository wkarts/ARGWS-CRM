<?php
/** Original expense task modal/writer/table against actual PDO storage and source-aware identities. */
require __DIR__.'/native_invoice_related_tasks.php';
require_once APPPATH.'services/CompanyExpenses.php';
require_once APPPATH.'services/NativeCompanyTaskScopedDb.php';
$start=$GLOBALS['essentialChecks'];
if($db->driver()==='mysql')CompanyExpenseSchema::ensure($db,$p);
else foreach(CompanyExpenseSchema::definitions()as$table=>$fields){
    $parts=[];foreach($fields as$name=>$type)$parts[]='`'.$name.'` '.(str_contains($type,'AUTO_INCREMENT')?'INTEGER PRIMARY KEY AUTOINCREMENT':$type.' NOT NULL');
    if($table==='company_expense_requests')$parts[]='PRIMARY KEY(request_key)';$db->query('CREATE TABLE IF NOT EXISTS '.$p.$table.'('.implode(',',$parts).')');
}
require_once APPPATH.'services/NativeCompanyExpenseBillabilitySchema.php';NativeCompanyExpenseBillabilitySchema::ensure($db,$p);
unset($db->data_cache['table_names']);
$expenses=new CompanyExpenses($db,$p);
$category=$expenses->saveCategory($a,7,'Related task expense',$key('expense-task-category'));
$expense=$expenses->create($a,7,['category_id'=>$category,'client_id'=>0,'project_id'=>0,'currency'=>'BRL','date'=>'2026-11-05','description'=>'Expense task parent A','note'=>'','amount'=>'12.34','tax_rate'=>'0.00','billable'=>0],$key('expense-task-parent'));
$expenseId=NativeCompanyResourceId::encode('company_expense',$expense);
$other=$expenses->create($a,7,['category_id'=>$category,'client_id'=>0,'project_id'=>0,'currency'=>'BRL','date'=>'2026-11-05','description'=>'Unrelated same-company expense','note'=>'','amount'=>'12.34','tax_rate'=>'0.00','billable'=>0],$key('expense-task-other'));
$query=['rel_type'=>'expense','rel_id'=>$expenseId];$dto=$form->newForm($a,7,$query,'2026-11-05');
essentialCheck($dto['native_task_form']['selected_relation']['id']===$expenseId,'Original task modal selects the exact typed expense parent');
\NativeTaskHttpTest\CompanyRequestScope::$issuer=$a;\NativeTaskHttpTest\CompanyRequestScope::$actor=7;
$controller->input->verb='GET';$controller->input->query=$query;$controller->task();
essentialCheck(str_contains($controller->output->body,'id="_task_modal"')&&str_contains($controller->output->body,$expenseId),'Actual Tasks controller renders its original modal for an expense');
$expenseWire=$wire;unset($expenseWire['native_milestone_revision'],$expenseWire['assignees']);
$expenseWire['rel_type']='expense';$expenseWire['rel_id']=$expenseId;$expenseWire['name']='Related expense task';$expenseWire['native_request_key']=$key('expense-task-create');
$task=$form->save($a,7,null,$expenseWire);$identity=NativeCompanyResourceId::decode('task',$task);$record=$standalone->get($a,7,$identity['source_id']);
$fields=json_decode($record['native_fields_json'],true,32,JSON_THROW_ON_ERROR);
essentialCheck($identity['source_kind']==='company_standalone_task'&&$fields['relation_type']==='expense'&&$fields['relation_source_kind']==='company_expense'&&(int)$fields['relation_source_id']===$expense,'Writer retains the expense type/source in the existing standalone task domain');
essentialCheck($form->save($a,7,null,$expenseWire)===$task,'Replayed native expense task creates no second task');
$page=(new NativeCompanyRelatedTaskPage($db,$p))->read($a,7,'expense',$expenseId,$input,'2026-11-05');
essentialCheck(array_column($page['rows'],'id')===[$task],'Related table returns only the exact expense task');
essentialCheck((new NativeCompanyRelatedTaskPage($db,$p))->read($a,7,'expense',NativeCompanyResourceId::encode('company_expense',$other),$input,'2026-11-05')['rows']===[],'A different same-company expense cannot receive this task by customer or ID coincidence');
$edited=$form->editForm($a,7,$task,'2026-11-05');$edit=$expenseWire;$edit['name']='Revised expense task';$edit['native_request_key']=$key('expense-task-edit');
$edit['native_revision']=$edited['native_task_form']['fields']['native_revision'];$edit['native_fields_revision']=$edited['native_task_form']['fields']['native_fields_revision'];$edit['native_tags_revision']=$edited['native_task_form']['fields']['native_tags_revision'];
essentialCheck($edited['task']->rel_type==='expense'&&$edited['task']->rel_id===$expenseId&&$form->save($a,7,$task,$edit)===$task,'Original edit retains the exact expense relationship and commits with revision');
essentialThrows(fn()=>$form->newForm($b,7,$query,'2026-11-05'),'native_resource_reference_denied','Other company cannot open the expense task form');
essentialThrows(fn()=>$form->save($b,7,null,$expenseWire),'native_resource_reference_denied','Other company cannot create a task under a foreign expense');
$db->query('UPDATE '.$p.'issuers_staff SET can_view=1 WHERE issuer_id=? AND staff_id=92',[$a]);
$db->query('DELETE FROM '.$p.'issuers_resource_permissions WHERE issuer_id=? AND staff_id=92 AND feature=?',[$a,'expenses']);
essentialThrows(fn()=>(new NativeCompanyRelatedTaskPage($db,$p))->read($a,92,'expense',$expenseId,$input,'2026-11-05'),'native_resource_reference_denied','Task view alone does not confer expense visibility');
$db->query('INSERT INTO '.$p.'issuers_resource_permissions(issuer_id,staff_id,feature,capability) VALUES(?,92,?,?)',[$a,'expenses','view']);
essentialCheck((new NativeCompanyRelatedTaskPage($db,$p))->read($a,92,'expense',$expenseId,$input,'2026-11-05')['recordsTotal']===1,'Existing tasks and expense grants permit the bounded read without expense edit');
$before=$db->query('SELECT * FROM '.$p.'company_standalone_tasks ORDER BY id')->result_array();
$db->query('UPDATE '.$p.'company_expenses SET issuer_id=? WHERE id=?',[$b,$expense]);
essentialThrows(fn()=>NativeCompanyResourceReferences::resolve($db,$p,$a,7,'task',$task),'native_resource_reference_denied','Direct task URL revalidates the current parent owner');
$bad=$edit;$bad['native_request_key']=$key('expense-task-revoked');
essentialThrows(fn()=>$form->save($a,7,$task,$bad),'native_resource_reference_denied','Parent ownership revocation blocks subsequent task mutation');
essentialCheck($db->query('SELECT * FROM '.$p.'company_standalone_tasks ORDER BY id')->result_array()===$before,'Denied parent mutation leaves task bytes unchanged');
$db->query('UPDATE '.$p.'company_expenses SET issuer_id=? WHERE id=?',[$a,$expense]);

// A late audit trigger cannot change parent authority and still commit the task.
$trigger=$p.'expense_task_owner_fault';$fault=$expenseWire;$fault['native_request_key']=$key('expense-task-audit-fault');
$frames=static fn()=>[
    $db->query('SELECT * FROM '.$p.'company_standalone_tasks ORDER BY id')->result_array(),
    $db->query('SELECT * FROM '.$p.'company_project_requests ORDER BY request_key')->result_array(),
    $db->query('SELECT * FROM '.$p.'company_expenses ORDER BY id')->result_array(),
    $db->query('SELECT * FROM '.$p.'issuers_audit ORDER BY id')->result_array(),
];
$beforeFault=$frames();
$db->query($db->driver()==='sqlite'
    ?'CREATE TRIGGER '.$trigger.' AFTER INSERT ON '.$p."issuers_audit WHEN NEW.event='company_standalone_task_native_created' BEGIN UPDATE ".$p.'company_expenses SET issuer_id='.$b.' WHERE id='.$expense.'; END'
    :'CREATE TRIGGER '.$trigger.' AFTER INSERT ON '.$p.'issuers_audit FOR EACH ROW UPDATE '.$p.'company_expenses SET issuer_id='.$b.' WHERE id='.$expense." AND NEW.event='company_standalone_task_native_created'");
try{essentialThrows(fn()=>$form->save($a,7,null,$fault),'native_resource_reference_denied','Late parent ownership mutation aborts the whole task transaction');}
finally{$db->query('DROP TRIGGER '.$trigger);}
essentialCheck($frames()===$beforeFault,'Late failure rolls back task, request receipt, audit and changed parent bytes');
$recovered=$form->save($a,7,null,$fault);
essentialCheck($form->save($a,7,null,$fault)===$recovered,'Exact failed request can retry once and then replay idempotently');

// The principal retains historical expense tasks, even when numeric IDs match private sources.
if(!$db->table_exists($p.'expenses')){
    $seed=file_get_contents(dirname(__DIR__,2).'/install/database.sql');preg_match('/CREATE TABLE `tblexpenses` \(.*?\) ENGINE=InnoDB[^;]*;/s',$seed,$ddl);
    if(!$ddl)throw new RuntimeException('Missing original expense schema');$sql=str_replace('`tblexpenses`','`'.$p.'expenses`',$ddl[0]);
    if($db->driver()==='sqlite')$sql=preg_replace('/ CHARACTER SET [a-zA-Z0-9_]+ COLLATE [a-zA-Z0-9_]+/','',$sql);$db->query($sql);
}
unset($db->data_cache['table_names']);
$db->query('INSERT INTO '.$p.'expenses(id,category,expense_name,note,clientid,date,amount,currency,billable,addedfrom,send_invoice_to_customer,dateadded) VALUES(?,1,?,?,0,?,12.34,1,0,7,0,?)',[$expense,'Historical expense task parent','Historical preserved','2026-11-05','2026-11-05 12:00:00']);
$db->query('INSERT INTO '.$p.'tasks(id,name,description,priority,dateadded,startdate,status,addedfrom,rel_type,rel_id,billable,billed) VALUES(9901,?,?,2,?,?,1,7,?,?,1,0)',['Historical related expense task','Exact historical relation','2020-01-01 12:00:00','2020-01-01','expense',$expense]);
$historical=$db->query('SELECT * FROM '.$p.'tasks WHERE id=9901')->row_array();
$legacyPage=(new NativeCompanyRelatedTaskPage($db,$p))->read(1,7,'expense',(string)$expense,$input,'2026-11-05');
essentialCheck(array_column($legacyPage['rows'],'id')===['9901']&&$legacyPage['rows'][0]['source_kind']==='task','Principal receives actual historical tasks and excludes the coincident private parent');
essentialCheck($legacyPage['rows'][0]['facts']['startdate']==='2020-01-01','Historical task keeps its real date instead of invented native metadata');
essentialThrows(fn()=>(new NativeCompanyRelatedTaskPage($db,$p))->read($a,7,'expense',(string)$expense,$input,'2026-11-05'),'native_resource_reference_denied','Secondary cannot alias the historical raw expense ID');
essentialCheck($db->query('SELECT * FROM '.$p.'tasks WHERE id=9901')->row_array()===$historical,'Task projection never rewrites or adopts historical rows');

foreach(['NativeCompanyRelatedTaskPage','NativeCompanyRelatedTaskRows','NativeCompanyTaskScopedDb']as$class)if(!class_exists('NativeTaskHttpTest\\'.$class,false))class_alias($class,'NativeTaskHttpTest\\'.$class);
$controller->input->verb='POST';$controller->input->body=$input+['_native_company_csrf'=>'synthetic-csrf'];$controller->init_relation_tasks($expenseId,'expense');
$result=json_decode($controller->output->body,true,64,JSON_THROW_ON_ERROR);
essentialCheck($result['iTotalRecords']===2&&str_contains(json_encode($result['aaData'],JSON_THROW_ON_ERROR),'Revised expense task'),'Actual related-task endpoint renders original row with scoped expense identity');
$controller->input->verb='GET';
try{$controller->init_relation_tasks($expenseId,'expense');throw new RuntimeException('GET unexpectedly admitted');}catch(LogicException $error){essentialCheck($error->getMessage()==='HTTP 403','Related task table remains POST with native CSRF');}
echo 'Expense-related original tasks: '.($GLOBALS['essentialChecks']-$start).' additional checks passed ('.$db->driver().').'.PHP_EOL;
