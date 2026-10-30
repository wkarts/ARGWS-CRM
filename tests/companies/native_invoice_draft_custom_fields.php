<?php
require __DIR__.'/native_invoice_draft_references.php';
require_once APPPATH.'services/NativeCompanyCustomFields.php';
$fields=new NativeCompanyCustomFields($db,$p);
$definition=NativeCompanyCustomFields::definition(['fieldto'=>'invoice','name'=>'Synthetic draft metadata','type'=>'input','active'=>1,'show_on_table'=>1]);
$db->query('INSERT INTO '.$p.'company_custom_fields(issuer_id,fieldto,slug,definition_json,revision) VALUES(?,?,?,?,1)',[$a,'invoice','invoice_draft_synthetic',json_encode($definition,JSON_THROW_ON_ERROR)]);
$field=NativeCompanyResourceId::encode('company_custom_field',$db->insert_id());
$tables=array_values(array_unique(array_merge($fields->tableNames(),$drafts->tables())));
$before=$db->query('SELECT * FROM '.$p.'invoices ORDER BY id')->result_array();
IssuerTransaction::run($db,$p,$tables,fn()=>$fields->saveInOwnedTransaction($a,7,'invoice',$encoded,[$field=>'Synthetic private value']));
essentialCheck($fields->values($a,7,'invoice',$encoded)[$field]==='Synthetic private value','actual custom-field service stores and reads canonical private draft value');
$row=$db->query('SELECT source_kind,source_id FROM '.$p.'company_custom_field_values WHERE issuer_id=? AND field_id=?',[$a,NativeCompanyResourceId::decode('custom_field',$field)['source_id']])->row_array();
essentialCheck($row['source_kind']==='company_invoice_draft'&&(int)$row['source_id']===$private,'private custom field never aliases an emitted invoice raw ID');
essentialThrows(fn()=>$fields->values($b,7,'invoice',$encoded),'company_invoice_draft_unavailable','B cannot hydrate A draft fields');
essentialThrows(fn()=>IssuerTransaction::run($db,$p,$fields->tableNames(),fn()=>$fields->saveInOwnedTransaction($a,7,'invoice',$encoded,[$field=>'wrong boundary'])),'issuer_outer_transaction_not_allowed','custom-field-only boundary cannot write private draft');
essentialCheck($fields->values($a,7,'invoice',$encoded)[$field]==='Synthetic private value','partial boundary failure preserves private value');
essentialCheck($db->query('SELECT * FROM '.$p.'invoices ORDER BY id')->result_array()===$before,'private custom fields never issue or modify financial rows');
echo 'Private draft custom-field composition: '.$GLOBALS['essentialChecks']." cumulative PASS.\n";
