<?php
/** Included by the real original-form writer fixture; no catalogue grants or copied tax rows. */
$historyPayload=$draftInput;$historyPayload['items']=[['description'=>'Preserved billing tax','qty'=>'1.00','rate'=>'100.00','taxes'=>[['name'=>'Billing historical tax','rate'=>'5.00']]]];
$historyWriter=new CompanyInvoices($db,$p);$historyInvoice=$historyWriter->create($a,7,$historyPayload,hash('sha256','frozen-source-invoice'));
$historyRow=$db->query('SELECT * FROM '.$p.'invoices WHERE id=?',[$historyInvoice])->row_array();$historyLine=$historyWriter->previewItems($a,7,$historyInvoice)[0];
$historyWire=$wire;unset($historyWire['newitems']);$historyWire['items']=['1'=>['itemid'=>(string)$historyLine['id'],'order'=>'1','description'=>'Preserved billing tax','long_description'=>'','qty'=>'1','rate'=>'200','unit'=>'','taxname'=>['Billing historical tax|5.0000']]];
$historyWire['_native_invoice_id']=(string)$historyInvoice;$historyWire['merge_current_invoice']=(string)$historyInvoice;$historyWire['isedit']='true';$historyWire['number']=(string)$historyRow['number'];$historyWire['_native_invoice_revision']=$historyWriter->revision($a,7,$historyInvoice);$historyWire['_native_invoice_tags_revision']=(new NativeCompanyResourceTags($db,$p))->read($a,7,'invoice',$historyInvoice)['revision'];$historyWire['_native_invoice_request_key']=hash('sha256','preserve-frozen-tax-edit');$historyWire['discount_type']=$historyRow['discount_type'];$historyWire['subtotal']='200.00';$historyWire['total']='210.00';
$historyReceipt=$form->update($a,7,$historyInvoice,$historyWire);
essentialCheck(CompanyInvoiceCalculationContract::options($db,$p,$a,7,$historyInvoice)===[],'edit preserves the attested historical calculation contract instead of adopting the new-form algorithm');
essentialCheck($historyWriter->document($a,7,$historyInvoice)->data()['totals']['total']==='210.00','real form edits rate while retaining its own historical tax absent from catalogue');
essentialCheck($historyWriter->previewItems($a,7,$historyInvoice)[0]['taxes']===$historyLine['taxes'],'edit preserves the exact frozen tax name/rate');
essentialCheck($form->update($a,7,$historyInvoice,$historyWire)===$historyReceipt,'same original issued edit and tax snapshot remain durably retryable');
$changed=$historyWire;$changed['_native_invoice_revision']=$historyWriter->revision($a,7,$historyInvoice);$changed['_native_invoice_tags_revision']=$historyReceipt['result']['tags_revision'];$changed['_native_invoice_request_key']=hash('sha256','forged-frozen-tax-rate');$changed['items']['1']['taxname']=['Billing historical tax|6.0000'];$changed['total']='212.00';
essentialThrows(fn()=>$form->update($a,7,$historyInvoice,$changed),'company_catalog_invalid_tax_selection','historical name cannot authorize an invented rate');
$changed['items']['1']['taxname']=['Foreign invented tax|5.0000'];$changed['total']='210.00';essentialThrows(fn()=>$form->update($a,7,$historyInvoice,$changed),'company_catalog_invalid_tax_selection','historical line cannot authorize another name');
$changed['items']['1']['taxname']=['Billing historical tax|5.0000'];$changed['items']['1']['itemid']=(string)($historyLine['id']+1000000);essentialThrows(fn()=>$form->update($a,7,$historyInvoice,$changed),'company_catalog_invalid_tax_selection','unowned line ID cannot borrow a frozen tax');
$changed['items']=[];$changed['newitems']=['1'=>$historyWire['items']['1']];unset($changed['newitems']['1']['itemid']);essentialThrows(fn()=>$form->update($a,7,$historyInvoice,$changed),'company_catalog_invalid_tax_selection','new line cannot borrow the old line tax without catalogue authority');
essentialThrows(fn()=>$form->update($b,7,$historyInvoice,$historyWire),'company_invoice_access_denied','foreign issuer cannot borrow line snapshots');
essentialCheck($historyWriter->document($a,7,$historyInvoice)->data()['totals']['total']==='210.00','all forged tax attempts preserve the committed amount');
$forgedAlgorithm=$historyWire;$forgedAlgorithm['calculation_contract']='native_sales_v1';essentialThrows(fn()=>$form->update($a,7,$historyInvoice,$forgedAlgorithm),'native_invoice_form_invalid','wire cannot select a new algorithm for a historical invoice');
essentialThrows(fn()=>$form->update(1,7,42,[]),'native_invoice_editor_denied','primary NULL history remains outside the issuer-owned writer');
// Populate the original primary catalogue tables from the shipped installation DDL.
foreach(['taxes','items','items_groups','payment_modes','tasks'] as $legacyTable){
 if($db->table_exists($p.$legacyTable))continue;
 if(!preg_match('/CREATE TABLE `tbl'.preg_quote($legacyTable,'/').'` \(.*?\) ENGINE=InnoDB[^;]*;/s',$native,$match))throw new RuntimeException('Missing native primary catalogue schema');
 $ddl=str_replace('`tbl'.$legacyTable.'`','`'.$p.$legacyTable.'`',$match[0]);
 if($db->driver()==='sqlite'){$ddl=preg_replace('/ CHARACTER SET [A-Za-z0-9_]+ COLLATE [A-Za-z0-9_]+/','',$ddl);$ddl=preg_replace('/enum\([^)]*\)/','varchar(50)',$ddl);}
 $db->query($ddl);unset($db->data_cache['table_names']);
}
$settings->saveIdentity(1,7,['legal_name'=>'Synthetic primary invoice editor','tax_id'=>'39053344705','profile'=>['invoice_company_address'=>'Primary fixture address','invoice_company_city'=>'Synthetic city','company_state'=>'BA','invoice_company_country_code'=>'BR','invoice_company_postal_code'=>'00000-000','invoice_company_phonenumber'=>'']]);
$primaryClient=(new CompanyClients($db,$p))->create(1,7,['company'=>'Primary own invoice editor regression']);
$primaryPayload=$historyPayload;$primaryPayload['client_id']=$primaryClient;
$primaryInvoice=$historyWriter->create(1,7,$primaryPayload,hash('sha256','primary-owned-editor-regression'));
require_once APPPATH.'services/CompanyExpenseSchema.php';if($db->driver()==='mysql')CompanyExpenseSchema::ensure($db,$p);else foreach(CompanyExpenseSchema::definitions() as $table=>$fields){$parts=[];foreach($fields as $name=>$type)$parts[]='`'.$name.'` '.(str_contains($type,'AUTO_INCREMENT')?'INTEGER PRIMARY KEY AUTOINCREMENT':$type.' NOT NULL');if($table==='company_expense_requests')$parts[]='PRIMARY KEY(request_key)';$db->query('CREATE TABLE IF NOT EXISTS '.$p.$table.'('.implode(',',$parts).')');}
require_once APPPATH.'services/NativeCompanyExpenseBillabilitySchema.php';NativeCompanyExpenseBillabilitySchema::ensure($db,$p);
$primaryEdit=$form->editData(1,7,$primaryInvoice);
essentialCheck((int)$primaryEdit['invoice']->id===$primaryInvoice&&$primaryEdit['invoices_to_merge']===[],'primary own invoice opens its original editor without offering secondary-only merge');
essentialThrows(fn()=>(new NativeCompanyInvoiceMergeCandidates($db,$p))->forCustomer(1,7,$primaryClient,(string)$primaryInvoice),'native_invoice_merge_denied','primary editor does not grant secondary merge authority');
essentialThrows(fn()=>$form->editData($a,7,$primaryInvoice),'company_invoice_access_denied','secondary cannot open the primary owned invoice editor');
echo "Original invoice frozen tax/update boundaries: ".$GLOBALS['essentialChecks']." cumulative PASS.\n";
