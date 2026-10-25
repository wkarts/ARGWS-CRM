<?php
require __DIR__.'/invoices.php';
require_once APPPATH.'services/NativeCompanyInvoiceCustomColumns.php';
require __DIR__.'/native_invoice_table_schema_fixture.php';
NativeCompanyCustomFieldSchema::ensure($db,$p);
$fieldService=new NativeCompanyCustomFields($db,$p);
$definition=NativeCompanyCustomFields::definition(['fieldto'=>'invoice','name'=>'Synthetic invoice field','type'=>'input','active'=>1,'show_on_table'=>1]);
$ids=[];
foreach([$a,$b]as$issuer){$db->query('INSERT INTO '.$p.'company_custom_fields(issuer_id,fieldto,slug,definition_json,revision) VALUES(?,?,?,?,1)',[$issuer,'invoice','invoice_synthetic',json_encode($definition,JSON_THROW_ON_ERROR)]);$ids[$issuer]=NativeCompanyResourceId::encode('company_custom_field',(int)$db->insert_id());}
$columns=new NativeCompanyInvoiceCustomColumns($db,$p,$a,7);
essentialCheck(array_column($columns->definitions(true),'id')===[$ids[$a]],'native table custom columns come only from authorized active own definitions');
$invoice=$branded;$value='Synthetic literal <script> & 50%';
$tables=array_merge($fieldService->tableNames(),['invoices']);
IssuerTransaction::run($db,$p,$tables,fn()=>$fieldService->saveInOwnedTransaction($a,7,'invoice',$invoice,[$ids[$a]=>$value]));
$expression=$columns->expression($ids[$a]);
$projected=$db->query('SELECT '.$expression['sql'].' value FROM '.$p.'invoices i WHERE i.issuer_id=? AND i.id=?',array_merge($expression['params'],[$a,$invoice]))->row_array();
essentialCheck($projected['value']===$value&&$columns->values($invoice)[(string)$ids[$a]]===$value,'bound SQL projection equals canonical value reader without interpreting HTML');
essentialThrows(fn()=>$columns->expression($ids[$b]),'native_custom_field_denied','foreign field ID cannot become query SQL');
essentialThrows(fn()=>$columns->expression('1 OR 1=1'),'native_custom_field_denied','field syntax cannot become query SQL');
essentialThrows(fn()=>$columns->values($ib),'native_resource_reference_denied','foreign invoice denied before custom values hydration');
require_once APPPATH.'services/NativeCompanyInvoiceRead.php';
$reader=new NativeCompanyInvoiceRead($db,$p);
$page=$reader->page($a,7,['orders'=>[['field'=>'cf_'.$ids[$a],'direction'=>'desc']]]);
essentialCheck($page['documents'][0]['invoice_id']===$invoice,'custom SQL ordering executes on the canonical bound value, not a fake presentation field');
essentialThrows(fn()=>$reader->page($b,7,['orders'=>[['field'=>'cf_'.$ids[$a],'direction'=>'desc']]]),'native_custom_field_denied','foreign custom sort definition is denied before reading invoices');
$builder=['match_type'=>'and','rules'=>[['id'=>'invoice_synthetic','type'=>'TextRule','operator'=>'contains','value'=>'50%','has_dynamic_value'=>false]]];
$filtered=$reader->page($a,7,['filters'=>$builder]);
essentialCheck($filtered['recordsFiltered']===1&&$filtered['documents'][0]['invoice_id']===$invoice,'custom text filter executes with literal wildcard and bound scoped field provenance');
$miss=$builder;$miss['rules'][0]['value']='50_';essentialCheck($reader->page($a,7,['filters'=>$miss])['recordsFiltered']===0,'underscore filter is literal, not unbounded wildcard');
$customSearch=$reader->page($a,7,['search'=>'literal <script>']);essentialCheck($customSearch['recordsFiltered']===1&&$customSearch['documents'][0]['invoice_id']===$invoice,'global search includes authorized custom field text as data, never HTML/SQL');
$foreignRule=$builder;$foreignRule['rules'][0]['id']='unknown_definition';
essentialThrows(fn()=>$reader->page($a,7,['filters'=>$foreignRule]),'native_company_invoice_filter_invalid','unavailable custom field cannot enter SQL');
require_once APPPATH.'services/NativeCompanyInvoiceTable.php';if(!function_exists('_l')){function _l($s){return$s;}}
$catalogue=['sale_agent'=>[],'status'=>[],'year'=>[],'made_payment_by'=>[]];
$nativeTable=new NativeCompanyInvoiceTable($db,$p,$a,7,'invoices',$catalogue,[],$columns->definitions());
essentialCheck(count($nativeTable->rules())===13&&$nativeTable->normalizeBuilder($builder)===['match_type'=>'and','rules'=>[['id'=>'invoice_synthetic','value'=>'50%','has_dynamic_value'=>false,'operator'=>'contains','type'=>'TextRule']]],'same native table normalizes and presents actual custom rule in addition to twelve base rules');
$foreignDefinitions=(new NativeCompanyInvoiceCustomColumns($db,$p,$b,7))->definitions();
essentialThrows(fn()=>new NativeCompanyInvoiceTable($db,$p,$a,7,'invoices',$catalogue,[],$foreignDefinitions),'native_company_invoice_columns_changed','table A cannot hydrate custom rule catalogue from B even for same actor');
$cases=[
 ['number','NumberRule','20.50','greater','10'],
 ['select','SelectRule','One','equal','One'],
 ['multiselect','MultiSelectRule',['One','Two'],'in',['Two']],
 ['checkbox','CheckboxRule',['One'],'in',['One']],
 ['date_picker','DateRule','2026-10-25','equal','2026-10-25'],
 ['date_picker_time','DateRule','2026-10-25 14:15:00','equal','2026-10-25'],
];
foreach($cases as[$fieldType,$ruleType,$stored,$operator,$filterValue]){
 $def=NativeCompanyCustomFields::definition(['fieldto'=>'invoice','name'=>'Synthetic '.$fieldType,'type'=>$fieldType,'active'=>1,'show_on_table'=>1,'options'=>'One,Two']);
 $slug='invoice_'.$fieldType;$db->query('INSERT INTO '.$p.'company_custom_fields(issuer_id,fieldto,slug,definition_json,revision) VALUES(?,?,?,?,1)',[$a,'invoice',$slug,json_encode($def,JSON_THROW_ON_ERROR)]);
 $fid=NativeCompanyResourceId::encode('company_custom_field',(int)$db->insert_id());
 IssuerTransaction::run($db,$p,$tables,fn()=>$fieldService->saveInOwnedTransaction($a,7,'invoice',$invoice,[$fid=>$stored]));
 $rule=['match_type'=>'and','rules'=>[['id'=>$slug,'type'=>$ruleType,'operator'=>$operator,'value'=>$filterValue,'has_dynamic_value'=>false]]];
 $result=$reader->page($a,7,['filters'=>$rule]);essentialCheck($result['recordsFiltered']===1&&$result['documents'][0]['invoice_id']===$invoice,'custom '.$fieldType.' executes on canonical field values');
}
$db->query('UPDATE '.$p.'company_custom_fields SET revision=2 WHERE issuer_id=?',[$a]);
essentialThrows(fn()=>$columns->recheck(),'native_company_invoice_columns_changed','definition revision changed during render fails closed');
echo 'Native invoice custom column projection: '.$GLOBALS['essentialChecks']." cumulative checks PASS.\n";
