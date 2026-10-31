<?php
require __DIR__.'/native_invoice_created_metadata.php';
require_once APPPATH.'services/NativeCompanyInvoiceCommands.php';
NativeCompanyInvoiceCommandSchema::ensure($db,$p);$commands=new NativeCompanyInvoiceCommands($db,$p);
$metadata=['invoice_fields'=>[],'line_fields'=>[[$field=>'Issued complete form'],[]],'tags'=>['Issued complete tag']];$key=hash('sha256','native-issued-complete-command');
$draftsBefore=$db->query('SELECT * FROM '.$p.'company_invoice_drafts ORDER BY id')->result_array();
$result=$commands->createIssued($a,12,$draftInput,$metadata,$key);
essentialCheck($result['invoice_id']>0&&count($result['line_ids'])===2,'direct issue returns actual invoice and inserted line IDs');
essentialCheck($fields->values($a,12,'items',$result['line_ids'][0])[$field]==='Issued complete form','direct issue persists full line metadata');
essentialCheck(array_column($tags->read($a,12,'invoice',$result['invoice_id'])['tags'],'name')===['Issued complete tag'],'direct issue persists native tags');
essentialCheck($db->query('SELECT * FROM '.$p.'company_invoice_drafts ORDER BY id')->result_array()===$draftsBefore,'direct issue creates no intermediate draft');
$invoicesBefore=$db->query('SELECT * FROM '.$p.'invoices ORDER BY id')->result_array();$auditBefore=$db->query('SELECT * FROM '.$p.'issuers_audit ORDER BY id')->result_array();
essentialCheck($commands->createIssued($a,12,$draftInput,$metadata,$key)===$result,'direct issue replays complete durable receipt');
essentialCheck($db->query('SELECT * FROM '.$p.'invoices ORDER BY id')->result_array()===$invoicesBefore&&$db->query('SELECT * FROM '.$p.'issuers_audit ORDER BY id')->result_array()===$auditBefore,'replay does not allocate, rewrite metadata or append audit');
$changed=$metadata;$changed['tags']=['Divergent tag'];essentialThrows(fn()=>$commands->createIssued($a,12,$draftInput,$changed,$key),'native_invoice_command_conflict','same financial body cannot replay different tags');
$changed=$metadata;$changed['line_fields'][0][$field]='Divergent line';essentialThrows(fn()=>$commands->createIssued($a,12,$draftInput,$changed,$key),'native_invoice_command_conflict','same financial body cannot replay different line metadata');
essentialThrows(fn()=>$commands->createIssued($a,7,$draftInput,$metadata,$key),'native_invoice_command_conflict','another actor cannot borrow complete receipt');
if($db->driver()==='sqlite'){
 $metadataBefore=[];foreach(['company_custom_field_values','company_work_tags','company_work_tag_links','company_invoice_requests','itemable','item_tax']as$table)$metadataBefore[$table]=$db->query('SELECT * FROM '.$p.$table)->result_array();
 $sequence=$db->query('SELECT * FROM '.$p.'issuers_sequences ORDER BY issuer_id,series')->result_array();
 $failedKey=hash('sha256','native-issued-complete-no-ack');
 $db->query('CREATE TRIGGER ignore_issued_command_ack BEFORE UPDATE ON '.$p.NativeCompanyInvoiceCommandSchema::TABLE.' BEGIN SELECT RAISE(IGNORE); END');
 essentialThrows(fn()=>$commands->createIssued($a,12,$draftInput,$metadata,$failedKey),'issuer_native_invoice_command_unconfirmed','late command receipt failure rolls back whole issue');
 $db->query('DROP TRIGGER ignore_issued_command_ack');
 essentialCheck($db->query('SELECT * FROM '.$p.'invoices ORDER BY id')->result_array()===$invoicesBefore&&$db->query('SELECT * FROM '.$p.'issuers_audit ORDER BY id')->result_array()===$auditBefore,'late failure rolls back invoice, metadata and audit');
 essentialCheck($db->query('SELECT * FROM '.$p.'issuers_sequences ORDER BY issuer_id,series')->result_array()===$sequence&&!$db->query('SELECT request_key FROM '.$p.NativeCompanyInvoiceCommandSchema::TABLE.' WHERE request_key=?',[$failedKey])->row_array(),'late failure restores sequence and leaves no pending command');
 foreach($metadataBefore as$table=>$rows)essentialCheck($db->query('SELECT * FROM '.$p.$table)->result_array()===$rows,'late issue failure preserves exact '.$table.' rows');
 $retry=$commands->createIssued($a,12,$draftInput,$metadata,$failedKey);essentialCheck($retry['invoice_id']>0,'exact failed request can retry after complete rollback');
}
$uncomposedKey=hash('sha256','existing-plain-financial-receipt');$uncomposed=(new CompanyInvoices($db,$p))->create($a,12,$draftInput,$uncomposedKey);
$beforeExisting=$db->query('SELECT * FROM '.$p.'invoices WHERE id=?',[$uncomposed])->row_array();
essentialThrows(fn()=>$commands->createIssued($a,12,$draftInput,$metadata,$uncomposedKey),'company_invoice_creation_proof_required','old financial receipt cannot be adopted as a newly composed native form');
essentialCheck(!$db->query('SELECT request_key FROM '.$p.NativeCompanyInvoiceCommandSchema::TABLE.' WHERE request_key=?',[$uncomposedKey])->row_array()&&$db->query('SELECT * FROM '.$p.'invoices WHERE id=?',[$uncomposed])->row_array()===$beforeExisting,'failed adoption leaves historical financial receipt intact without pending command');
echo 'Direct native invoice complete issue commands: '.$GLOBALS['essentialChecks']." cumulative PASS.\n";
