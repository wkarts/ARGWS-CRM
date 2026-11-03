<?php
require __DIR__.'/native_invoice_draft_creation_tags.php';
require_once APPPATH.'services/NativeCompanyInvoiceSequencePreferences.php';
$reader=new NativeCompanyInvoiceSequencePreferences($db,$p);$before=$reader->readForInvoiceCreation($a,12);
$db->query("DELETE FROM {$p}issuers_resource_permissions WHERE issuer_id=? AND staff_id=12 AND feature='invoices' AND capability='create'",[$a]);
$db->query("INSERT INTO {$p}issuers_resource_permissions(issuer_id,staff_id,feature,capability) VALUES(?,12,'invoices','edit')",[$a]);
try{
 require_once APPPATH.'services/NativeCompanyInvoiceNumberValidation.php';
 $state=[];foreach(['invoices','issuers_sequences','company_invoice_drafts','company_invoice_draft_events','issuers_audit']as$table)$state[$table]=$db->query('SELECT * FROM '.$p.$table)->result_array();
 essentialCheck($reader->readForDraftEditor($a,12,$canonical)===$before,'edit-only actor reads allocator for its real open draft without create/settings grant');
 essentialCheck((new NativeCompanyInvoiceNumberValidation($db,$p))->validate($a,12,['number'=>$before['next_number'],'isedit'=>'true','original_number'=>'0','date'=>$draftInput['date'],'invoice_id'=>$canonical,'revision'=>'1']),'original remote validator supports edit-only open draft without implying issue capability');
 essentialThrows(fn()=>$reader->readForInvoiceCreation($a,12),'native_invoice_sequence_denied','draft display does not grant permission for a new issued invoice');
 essentialThrows(fn()=>$creator->issue($a,12,$id,1,hash('sha256','editonly-cannot-issue')),'company_invoice_draft_denied','draft number display does not authorize emission');
 essentialThrows(fn()=>$reader->readForDraftEditor($b,12,$canonical),'native_invoice_sequence_denied','draft sequence cannot cross request issuer');
 essentialThrows(fn()=>$reader->readForDraftEditor($a,12,$branded),'native_invoice_sequence_denied','issued invoice cannot be substituted for a draft authority proof');
 foreach($state as$table=>$rows)essentialCheck($db->query('SELECT * FROM '.$p.$table)->result_array()===$rows,'edit-only sequence reads preserve '.$table);
}finally{
 $db->query("DELETE FROM {$p}issuers_resource_permissions WHERE issuer_id=? AND staff_id=12 AND feature='invoices' AND capability='edit'",[$a]);
 $db->query("INSERT INTO {$p}issuers_resource_permissions(issuer_id,staff_id,feature,capability) VALUES(?,12,'invoices','create')",[$a]);
}
echo 'Draft edit-only sequence display: '.$GLOBALS['essentialChecks']." cumulative PASS; no allocation or emission.\n";
