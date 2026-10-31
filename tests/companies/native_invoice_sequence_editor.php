<?php
require __DIR__.'/native_invoice_created_metadata.php';
require_once APPPATH.'services/NativeCompanyInvoiceSequencePreferences.php';
$sequence=new NativeCompanyInvoiceSequencePreferences($db,$p);$before=[];foreach(['issuers_sequences','invoices','issuers_audit']as$table)$before[$table]=$db->query('SELECT * FROM '.$p.$table)->result_array();
$read=$sequence->readForInvoiceCreation($a,12);
$stored=$db->query('SELECT next_number FROM '.$p.'issuers_sequences WHERE issuer_id=? AND series=?',[$a,'INV'])->row_array();
essentialCheck($read['next_number']===(string)$stored['next_number']&&strlen($read['revision'])===64,'invoice create-only actor reads the actual canonical allocator without settings permission');
essentialThrows(fn()=>$sequence->read($a,12),'native_invoice_sequence_denied','editor read does not grant settings read');
essentialThrows(fn()=>$sequence->save($a,12,'999',$read['revision']),'native_invoice_sequence_denied','editor read does not grant counter mutation');
essentialThrows(fn()=>$sequence->readForInvoiceCreation($b,12),'native_invoice_sequence_denied','editor cannot inspect another issuer counter');
essentialThrows(fn()=>$sequence->readForInvoiceCreation(1,7),'native_invoice_sequence_denied','principal keeps its original native counter');
foreach($before as$table=>$rows)essentialCheck($db->query('SELECT * FROM '.$p.$table)->result_array()===$rows,'editor number read leaves '.$table.' exactly unchanged');
$db->query("DELETE FROM {$p}issuers_resource_permissions WHERE issuer_id=? AND staff_id=12 AND feature='invoices' AND capability='create'",[$a]);
essentialThrows(fn()=>$sequence->readForInvoiceCreation($a,12),'native_invoice_sequence_denied','live create revocation denies counter read');
echo 'Native invoice editor next-number read: '.$GLOBALS['essentialChecks']." cumulative PASS.\n";
