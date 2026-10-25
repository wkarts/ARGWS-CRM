<?php
require __DIR__.'/native_invoice_editor_items.php';
require_once APPPATH.'services/NativeCompanyInvoiceNumberValidation.php';
$validator=new NativeCompanyInvoiceNumberValidation($db,$p);$writer=new CompanyInvoices($db,$p);
$record=$db->query('SELECT number,date FROM '.$p.'invoices WHERE id=?',[$branded])->row_array();
$wire=['number'=>(string)$record['number'],'isedit'=>'true','original_number'=>'999999','date'=>$record['date'],'invoice_id'=>$branded,'revision'=>$writer->revision($a,7,$branded)];
essentialCheck($validator->validate($a,7,$wire),'issued number is validated from persisted identity, not original_number browser claim');
$changed=$wire;$changed['number']='999999';$changed['original_number']='999999';essentialCheck(!$validator->validate($a,7,$changed),'forged original_number cannot authorize issued renumbering');
$changed=$wire;$changed['date']='2099-01-01';essentialCheck(!$validator->validate($a,7,$changed),'issued date cannot be changed through remote number validation');
$changed=$wire;$changed['revision']=str_repeat('0',64);essentialThrows(fn()=>$validator->validate($a,7,$changed),'company_invoice_stale','issued stale revision rejected');
essentialThrows(fn()=>$validator->validate($b,7,$wire),'native_resource_reference_denied','other issuer cannot validate foreign number with matching browser original');
$next=(new NativeCompanyInvoiceSequencePreferences($db,$p))->readForInvoiceCreation($a,7)['next_number'];
$draft=$drafts->document($a,7,$id);$draftWire=['number'=>$next,'isedit'=>'true','original_number'=>'0','date'=>$draftInput['date'],'invoice_id'=>$canonical,'revision'=>(string)$draft['revision']];
essentialCheck($validator->validate($a,7,$draftWire),'draft validates current allocator under actual persisted revision without reserving');
$draftWire['revision']='1';essentialThrows(fn()=>$validator->validate($a,7,$draftWire),'company_invoice_draft_stale','draft stale tab is rejected');
echo 'Native invoice number actual parents: '.$GLOBALS['essentialChecks']." cumulative PASS.\n";
