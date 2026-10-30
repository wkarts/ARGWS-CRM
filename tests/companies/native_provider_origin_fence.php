<?php
/** Real writer/SQLite proof of extracted provider discovery; no provider network. */
require __DIR__.'/invoices.php';
$start=$GLOBALS['essentialChecks'];
$names=['bb_origins','asaas_payment_origins','efi_company_origins'];
foreach($names as$n){
 if($db->table_exists($p.$n))throw new RuntimeException('Unexpected provider fixture');
 $db->query('CREATE TABLE '.$p.$n.' (id INT PRIMARY KEY,invoice_id INT NOT NULL)');
}
// A stale framework cache must not hide an origin added after bootstrap.
$db->data_cache['table_names']=[$p.'invoices'];
essentialCheck(CompanyInvoiceProviderTables::current($db,$p)===$names,'all three real provider tables discovered despite stale cache');
foreach($names as$index=>$table){
 $invoice=$s->create($a,7,$input,hash('sha256','native-provider-discovery-'.$table));
 $revision=$s->revision($a,7,$invoice);
 $db->query('INSERT INTO '.$p.$table.' (id,invoice_id) VALUES(?,?)',[$index+1,$invoice]);
 essentialThrows(fn()=>$s->change($a,7,$invoice,$revision),'company_invoice_provider_review_required','actual cancellation rejects '.$table.' origin');
 essentialCheck($s->revision($a,7,$invoice)===$revision,'denial preserves invoice and audit revision');
 $db->query('DELETE FROM '.$p.$table.' WHERE id=?',[$index+1]);
}
echo 'Provider origin discovery: '.($GLOBALS['essentialChecks']-$start)." real writer checks passed; all provider origins remain vetoes.\n";
