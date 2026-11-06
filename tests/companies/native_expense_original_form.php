<?php
/** Real final schemas, financial writers and original form mapping; HTTP/rendering tested separately. */
require __DIR__.'/native_expense_custom_fields.php';
require_once APPPATH.'services/NativeCompanyExpenseOriginalForm.php';
if(!function_exists('_l')){function _l($key,$values=null){return $key;}}
foreach(NativeCompanyExpenseBillabilitySchema::definitions($p) as $table=>$parts)$db->query(preg_replace('/ CHARACTER SET [a-z0-9]+ COLLATE [a-z0-9_]+/','', 'CREATE TABLE `'.$p.$table.'` ('.implode(',',$parts).') ENGINE=InnoDB'));
foreach(['contacts','contact_permissions'] as $table){
    if($db->table_exists($p.$table))continue;preg_match('/CREATE TABLE `tbl'.preg_quote($table,'/').'` \(.*?\) ENGINE=InnoDB[^;]*;/s',$native,$match);
    $ddl=str_replace('`tbl'.$table.'`','`'.$p.$table.'`',$match[0]);$ddl=str_replace('`id` int NOT NULL,','`id` '.($db->driver()==='mysql'?'int NOT NULL AUTO_INCREMENT PRIMARY KEY':'INTEGER PRIMARY KEY AUTOINCREMENT').',',$ddl);
    if($db->driver()==='sqlite'){$ddl=preg_replace('/ CHARACTER SET [A-Za-z0-9_]+ COLLATE [A-Za-z0-9_]+/','',$ddl);$ddl=preg_replace('/enum\([^)]*\)/','varchar(50)',$ddl);}$db->query($ddl);
}
unset($db->data_cache['table_names']);NativeCompanyExpenseBillabilitySchema::assertValid($db,$p);
$start=$GLOBALS['essentialChecks'];$original=new NativeCompanyExpenseOriginalForm($db,$p);
$wire=['native_expense_form'=>NativeCompanyExpenseOriginalForm::WIRE,'request_key'=>hash('sha256','original-expense-create'),'expense_id'=>'','revision'=>'',
    'expense_name'=>'Original <script>name','reference_no'=>'R8-EX-42','note'=>'Original note','category'=>NativeCompanyResourceId::encode('company_expense_category',$category),
    'date'=>'2026-11-05','amount'=>'0.05','currency'=>'1','tax'=>NativeCompanyResourceId::encode('company_tax',1),'tax2'=>NativeCompanyResourceId::encode('company_tax',2),
    'paymentmode'=>'','clientid'=>(string)$ca,'billable'=>'on','project_id'=>'','custom_fields'=>['expenses'=>[$field['id']=>'Original custom field']],
    'repeat_every'=>'','repeat_every_custom'=>'1','repeat_type_custom'=>'day','native_due_days'=>'','native_contact_id'=>''];
$saved=$original->save($a,7,'',$wire);$expense=$ex->get($a,7,$saved['source_id']);
essentialCheck($saved['expenseid']===NativeCompanyResourceId::encode('company_expense',$saved['source_id'])&&$expense['native_form']['name']===$wire['expense_name']&&$expense['native_form']['reference_no']==='R8-EX-42','Actual original fields persist names/references and a typed expense identity');
essentialCheck($expense['tax_rate']===null&&(int)$expense['tax_cents']===1&&count($expense['native_form']['taxes'])===2,'Original two-tax form keeps exact independent-tax calculation');
essentialCheck($fields->values($a,7,'expenses',$saved['expenseid'])===[$field['id']=>'Original custom field'],'Original custom-field input commits with the expense');
$watch=['company_expenses','company_expense_requests','company_expense_native_forms','company_expense_native_billability','company_custom_field_values','company_financial_events','issuers_audit','company_expense_recurring'];
$snapshot=function()use($watch,$db,$p){$result=[];foreach($watch as $table)$result[$table]=$db->query('SELECT * FROM '.$p.$table)->result_array();return$result;};
$before=$snapshot();essentialCheck($original->save($a,7,'',$wire)===$saved&&$snapshot()===$before,'Original AJAX form replay preserves parent, metadata, ledger and request receipts');
$bad=$wire;$bad['reference_no']='Changed same request';essentialThrows(fn()=>$original->save($a,7,'',$bad),'company_expense_conflict','One request cannot silently change original metadata');
$dto=$original->form($a,7,$saved['expenseid']);essentialCheck($dto['native_expense_form']&&$dto['expense']->expenseid===$saved['expenseid']&&$dto['expense']->reference_no==='R8-EX-42'&&$dto['expense']->billable===1,'Actual original edit DTO restores the native name, reference and billable declaration');
essentialCheck(in_array($wire['category'],array_column($dto['categories'],'id'),true)&&count($dto['taxes'])===2&&$dto['native_expense_customer']['client_name']==='Private A','Original catalogs and related labels use selected-company sources');
essentialThrows(fn()=>$original->form($b,7,$saved['expenseid']),'company_expense_denied','Other company cannot hydrate the original expense editor');
essentialThrows(fn()=>$original->customer($a,7,$cb),'company_expense_reference_denied','Customer change endpoint projection does not read a known other-company customer');
$edit=$wire;$edit['expense_id']=$saved['expenseid'];$edit['revision']=$expense['revision_token'];$edit['request_key']=hash('sha256','original-expense-edit');$edit['reference_no']='R8-EX-43';$edit['custom_fields']['expenses'][$field['id']]='Edited original value';
$original->save($a,7,$saved['expenseid'],$edit);$current=$ex->get($a,7,$saved['source_id']);
essentialCheck($current['native_form']['reference_no']==='R8-EX-43'&&$fields->values($a,7,'expenses',$saved['expenseid'])===[$field['id']=>'Edited original value'],'Original edit updates profile/custom fields through actual CAS writer');
$before=$snapshot();essentialCheck($original->save($a,7,$saved['expenseid'],$edit)===$saved&&$snapshot()===$before,'Original edit idempotency preserves exact latest parent and field revisions');
$bad=$edit;$bad['request_key']=hash('sha256','original-stale-edit');essentialThrows(fn()=>$original->save($a,7,$saved['expenseid'],$bad),'company_expense_stale','Original editor rejects its stale parent revision');
$repeat=$wire;$repeat['request_key']=hash('sha256','original-expense-recurring');$repeat['repeat_every']='1-month';$repeat['cycles']='2';unset($repeat['billable']);
$recurringSaved=$original->save($a,7,'',$repeat);$schedules=(new CompanyExpenseRecurring($db,$p))->page($a,7,$recurringSaved['source_id']);
essentialCheck(count($schedules)===1&&$schedules[0]['interval_unit']==='month'&&(int)$schedules[0]['max_cycles']===2,'Original repeat controls create the canonical expense schedule atomically');
essentialCheck($original->form($a,7,$recurringSaved['expenseid'])===null,'An existing active schedule retains its explicit current control workflow');
$before=$snapshot();essentialCheck($original->save($a,7,'',$repeat)===$recurringSaved&&$snapshot()===$before,'Repeated original recurrence submission cannot create a second parent or schedule');
$bad=$repeat;$bad['request_key']=hash('sha256','original-expense-invalid-recurring');$bad['repeat_every']='9-week';
essentialThrows(fn()=>$original->save($a,7,'',$bad),'company_expense_recurring_invalid','Unsupported original recurrence is rejected by the real schedule writer');
essentialCheck($snapshot()===$before,'Schedule rejection rolls back parent, original fields, classifications, ledger and receipts');
$bad=$repeat;$bad['request_key']=hash('sha256','original-expense-recurring-failure');$db->failOn='INSERT INTO '.$p.'company_expense_recurring';
try{essentialThrows(fn()=>$original->save($a,7,'',$bad),'issuer_storage_failed','Actual schedule INSERT failure aborts the whole original form save');}finally{$db->failOn=null;}
essentialCheck($snapshot()===$before,'Schedule storage failure leaves no orphan expense or field values');
$bad=$wire;$bad['request_key']=hash('sha256','original-expense-foreign-category');$bad['category']=NativeCompanyResourceId::encode('company_expense_category',$ex->saveCategory($b,7,'B category',hash('sha256','original-b-category')));
essentialThrows(fn()=>$original->save($a,7,'',$bad),'company_expense_reference_denied','Forged original category remains issuer-bound');
essentialCheck($original->form($a,7,NativeCompanyResourceId::encode('company_expense',$legacyExpense))===null,'Existing scalar tax sources retain their original current financial contract');
echo 'Native original expense form: '.($GLOBALS['essentialChecks']-$start).' added real mapping/projection/atomic-schedule checks passed ('.$db->driver().').'.PHP_EOL;
