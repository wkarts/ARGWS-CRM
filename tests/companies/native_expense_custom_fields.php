<?php
/** Actual expense writer + custom-field schema/storage: no fake persistence or HTTP claim. */
require __DIR__.'/native_expense_form_profile.php';
require_once APPPATH.'services/NativeCompanyCustomFields.php';
$start=$GLOBALS['essentialChecks'];
NativeCompanyCustomFieldSchema::ensure($db,$p);
$fields=new NativeCompanyCustomFields($db,$p);
$field=$fields->saveDefinition($a,7,['fieldto'=>'expenses','name'=>'Cost centre','type'=>'input','required'=>1],null,null,str_repeat('8',32));
$foreign=$fields->saveDefinition($b,7,['fieldto'=>'expenses','name'=>'Private B reference','type'=>'input'],null,null,str_repeat('9',32));
$customInput=$input+['custom_fields'=>[$field['id']=>'Original native value']];
$customId=$ex->create($a,7,$customInput,hash('sha256','expense-custom-create'));
$canonical=NativeCompanyResourceId::encode('company_expense',$customId);
essentialCheck($fields->values($a,7,'expenses',$canonical)===[$field['id']=>'Original native value'],'Original expense form fields are stored by its canonical create transaction');
$tables=['company_expenses','company_expense_requests','company_expense_native_forms','company_custom_field_values','company_financial_events','issuers_audit'];
$snapshot=function()use($db,$p,$tables){$rows=[];foreach($tables as$table)$rows[$table]=$db->query('SELECT * FROM '.$p.$table)->result_array();return$rows;};
$before=$snapshot();
essentialCheck($ex->create($a,7,$customInput,hash('sha256','expense-custom-create'))===$customId&&$snapshot()===$before,'Exact replay preserves parent, field values, revisions, ledger and receipt');
$bad=$customInput;$bad['custom_fields'][$field['id']]='Different replay';
essentialThrows(fn()=>$ex->create($a,7,$bad,hash('sha256','expense-custom-create')),'company_expense_conflict','Receipt includes actual custom fields, not only the financial amount');
$bad=$customInput;$bad['custom_fields']=[];
essentialThrows(fn()=>$ex->create($a,7,$bad,hash('sha256','expense-custom-required')),'native_custom_field_required','Required form fields fail before committing the newly inserted expense');
essentialCheck($snapshot()===$before,'Missing required value rolls back parent, journal, profile and receipt');
$bad=$customInput;$bad['custom_fields'][$foreign['id']]='Cross company';
essentialThrows(fn()=>$ex->create($a,7,$bad,hash('sha256','expense-custom-foreign')),'native_custom_field_denied','Foreign field identity cannot be written through an authorized expense');
essentialCheck($snapshot()===$before,'Foreign value failure leaves the complete financial transaction unchanged');
$revision=$ex->get($a,7,$customId)['revision_token'];$updated=$customInput;$updated['custom_fields'][$field['id']]='Updated original value';
$ex->change($a,7,$customId,$revision,hash('sha256','expense-custom-edit'),$updated);
essentialCheck($fields->values($a,7,'expenses',$canonical)===[$field['id']=>'Updated original value'],'Original edit updates scoped values under the expense optimistic revision');
$before=$snapshot();
$ex->change($a,7,$customId,$revision,hash('sha256','expense-custom-edit'),$updated);
essentialCheck($snapshot()===$before,'Expense edit replay does not increment parent or field revisions twice');
$newRevision=$ex->get($a,7,$customId)['revision_token'];$updated['custom_fields'][$field['id']]='Should roll back';
$db->failOn='INSERT INTO '.$p.'company_expense_requests';
try{essentialThrows(fn()=>$ex->change($a,7,$customId,$newRevision,hash('sha256','expense-custom-late-failure'),$updated),'issuer_storage_failed','Late receipt failure rolls back an actual custom-field update');}finally{$db->failOn=null;}
essentialCheck($snapshot()===$before,'Late failure restores field values together with the parent and financial journal');
essentialThrows(fn()=>IssuerTransaction::run($db,$p,$ex->tables(),fn()=>$fields->saveCreatedInOwnedTransaction($a,7,'expenses',$customId,[$field['id']=>'Forged proof'],$ex)),'native_custom_field_creation_denied','A completed writer cannot attest a newly created resource in a later transaction');
essentialThrows(fn()=>$fields->values($b,7,'expenses',$canonical),'native_resource_reference_denied','Known canonical expense ID cannot expose A values from company B');
echo 'Native expense custom fields: '.($GLOBALS['essentialChecks']-$start).' added writer/rollback/identity checks passed ('.$db->driver().').'.PHP_EOL;
