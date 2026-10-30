<?php
/** Strictly synthetic positive/negative source integration; no actual bank/file transmission. */
declare(strict_types=1);
require __DIR__.'/banco_brasil_cnab400_preparation_mysql.php';
require_once APP_MODULES_PATH.'banco_brasil/libraries/BancoBrasil/Cnab400ReviewedReceipt.php';
require_once APP_MODULES_PATH.'banco_brasil/libraries/BancoBrasil/Reconciliation.php';
$source400=$db->query('SELECT * FROM '.$p.'bb_origins WHERE id=?',[$reserved400['origin_id']])->row_array();
$batch400Full=$db->query('SELECT * FROM '.$p.'bb_batches WHERE id=?',[$reserved400['batch_id']])->row_array();
$sourceSnapshot=json_decode($db->query('SELECT snapshot_json FROM '.$p.'bb_origin_snapshots WHERE origin_id=?',[$source400['id']])->row()->snapshot_json,true,64,JSON_THROW_ON_ERROR);
$syntheticPayer=array_intersect_key($title,array_flip(['payer_name','payer_address','payer_district','payer_zip','payer_city','payer_state','species','acceptance']));
$syntheticTitle=$sourceSnapshot['title']+$syntheticPayer+['payer_tax_id'=>$sourceSnapshot['document_snapshot']['recipient']['tax_id']];
$baseAgreement=\BancoBrasil\ModuleStore::agreement($storedConnection);
$syntheticAgreement=new \BancoBrasil\Agreement($baseAgreement->scope,$baseAgreement->taxId,$sourceSnapshot['beneficiary_name'],$baseAgreement->variation,$baseAgreement->wallet);
$syntheticCandidate=(new \BancoBrasil\Cnab400Generator())->candidate($syntheticAgreement,(int)$batch400Full['file_sequence'],'2026-10-19',[$syntheticTitle]);
// Direct fixture writes create a hypothetical completed batch only in the disposable DB.
// Production does not have an endpoint that generates, submits or homologates this file.
$db->query("UPDATE {$p}bb_batches SET content=?,content_hash=?,generation_state='generated',delivery_state='reported_submitted' WHERE id=?",[$syntheticCandidate['content'],hash('sha256',$syntheticCandidate['content']),$reserved400['batch_id']]);
$db->query("UPDATE {$p}bb_origins SET state='generated_not_submitted' WHERE id=?",[$source400['id']]);
$amount=\BancoBrasil\Fields400::money($sourceSnapshot['title']['amount']);
$wire=bb400Fixture($syntheticAgreement,'06','01');
foreach([[39,str_pad($source400['control_reference'],25)],[64,$source400['remote_id']],[111,'201026'],[117,str_pad($sourceSnapshot['title']['document'],10)],[147,\BancoBrasil\Fields400::date($sourceSnapshot['title']['due_date'])],[153,$amount],[176,'211026'],[182,'0000000'],[254,$amount],[306,$amount]] as [$position,$value])$wire=bb400Mutate($wire,1,$position,$value);
$import400=$store->importReturn($storedConnection,7,$wire,$binding400);
$event400=(int)$db->query('SELECT return_event_id FROM '.$p.'bb_return_file_events WHERE return_file_id=?',[$import400['id']])->row()->return_event_id;
$review400=new \BancoBrasil\Reconciliation($db,$p);
essentialThrows(fn()=>$review400->link($a,7,$event400),'bb400_homologation_required','a generated-looking batch without the scoped declaration cannot be linked');
// Exercise the financial guard even against a pre-existing forged link in the isolated fixture.
$db->query('INSERT INTO '.$p.'bb_event_links(event_id,origin_id,issuer_id,linked_by,created_at) VALUES(?,?,?,?,?)',[$event400,$source400['id'],$a,7,date('Y-m-d H:i:s')]);
$proof400=['credit_confirmed'=>'1','currency_confirmed'=>'1','homologation_confirmed'=>'1','reason'=>'Synthetic bank review','reference'=>'synthetic independent credit','homologation_reference'=>'synthetic homologation reference','confirmed_amount'=>$sourceSnapshot['title']['amount']];
essentialCheck(empty($review400->summaries($a,7)[$event400]['cnab400_homologation_declared']),'no homologation form is available by default');
$paymentWriter=new \app\services\InvoicePaymentWriter($db);$countPayments=(int)$db->query('SELECT COUNT(*) n FROM '.$p.'invoicepaymentrecords WHERE invoiceid=?',[$invoice400])->row()->n;
essentialThrows(fn()=>$paymentWriter->recordCompanyProviderReceipt($a,7,new \BancoBrasil\Cnab400ReviewedReceipt($event400,$proof400)),'bb400_homologation_required','no homologation declaration means no financial posting');
essentialCheck((int)$db->query('SELECT COUNT(*) n FROM '.$p.'invoicepaymentrecords WHERE invoiceid=?',[$invoice400])->row()->n===$countPayments,'missing homologation gate leaves no payment');
$db->query('DELETE FROM '.$p.'bb_event_links WHERE event_id=?',[$event400]);
// Restore the isolated hypothetical batch, then exercise the real declaration and generation path.
$db->query("UPDATE {$p}bb_batches SET content='',content_hash=?,generation_state='awaiting_layout',delivery_state='not_submitted' WHERE id=?",[hash('sha256',''),$reserved400['batch_id']]);
$db->query("UPDATE {$p}bb_origins SET state='reserved_awaiting_layout' WHERE id=?",[$source400['id']]);
$approval400=['currency_reference'=>'Synthetic currency evidence','interest_reference'=>'Synthetic exemption evidence','homologation_reference'=>$proof400['homologation_reference'],'currency_confirmed'=>'1','interest_confirmed'=>'1','homologation_confirmed'=>'1'];
$prepare400->declareApproval($a,7,$one['id'],$approval400);
$generated400=$prepare400->generate($a,7,$one['id'],'2026-10-19',$syntheticPayer);essentialCheck($generated400['remittance_generated'],'explicit synthetic declaration permits local CBR641 generation');
$localFile400=$prepare400->download($a,7,$one['id']);essentialCheck(hash('sha256',$localFile400['content'])===$localFile400['hash'],'exact private 400 bytes verified for download');
$prepare400->reportTransmission($a,7,$one['id'],'Synthetic manual submission');
$db->query("CREATE TRIGGER bb400_link_revoke AFTER INSERT ON {$p}bb_audit FOR EACH ROW BEGIN IF NEW.action='event_origin_linked' THEN UPDATE {$p}staff SET active=0 WHERE staffid=7; END IF; END");
try{essentialThrows(fn()=>$review400->link($a,7,$event400),'bb_reconciliation_access_denied','late revocation aborts return linking');}
finally{$db->query('DROP TRIGGER bb400_link_revoke');}
essentialCheck((int)$db->query('SELECT active FROM '.$p.'staff WHERE staffid=7')->row()->active===1&&$db->query('SELECT * FROM '.$p.'bb_event_links WHERE event_id=?',[$event400])->result_array()===[],'late revocation rolls back link and staff mutation');
essentialCheck($review400->link($a,7,$event400)===(int)$source400['id'],'declared generated 400 source links only exact issuer/account/document/number');
essentialCheck($review400->summaries($a,7)[$event400]['cnab400_homologation_declared']===true,'only issuer-bound synthetic approval unlocks the guarded form');
foreach(['credit_confirmed','currency_confirmed','homologation_confirmed'] as $key){$invalid=$proof400;$invalid[$key]='0';essentialThrows(fn()=>new \BancoBrasil\Cnab400ReviewedReceipt($event400,$invalid),'bb400_credit_confirmation_required','explicit confirmation '.$key.' required');}
$db->query('UPDATE '.$p.'invoices SET duedate=? WHERE id=?',['2026-12-31',$invoice400]);
try{essentialThrows(fn()=>$paymentWriter->recordCompanyProviderReceipt($a,7,new \BancoBrasil\Cnab400ReviewedReceipt($event400,$proof400)),'bb400_credit_amount_divergence','changed invoice due date requires review before receipt');}
finally{$db->query('UPDATE '.$p.'invoices SET duedate=? WHERE id=?',[$beforeInvoice['duedate'],$invoice400]);}
$bad=$proof400;$bad['confirmed_amount']='1.00';essentialThrows(fn()=>$paymentWriter->recordCompanyProviderReceipt($a,7,new \BancoBrasil\Cnab400ReviewedReceipt($event400,$bad)),'bb400_credit_amount_divergence','independent amount must match observed credit');
$db->query("CREATE TRIGGER bb400_tamper_payment AFTER INSERT ON {$p}bb_audit FOR EACH ROW BEGIN IF NEW.action='assisted_400_credit_applied' THEN UPDATE {$p}invoicepaymentrecords SET amount=1.00 WHERE invoiceid={$invoice400}; END IF; END");
try{essentialThrows(fn()=>$paymentWriter->recordCompanyProviderReceipt($a,7,new \BancoBrasil\Cnab400ReviewedReceipt($event400,$proof400)),'issuer_bb_receipt_unconfirmed','late native payment tampering is detected');}
finally{$db->query('DROP TRIGGER bb400_tamper_payment');}
essentialCheck((int)$db->query('SELECT COUNT(*) n FROM '.$p.'invoicepaymentrecords WHERE invoiceid=?',[$invoice400])->row()->n===$countPayments,'late tampering rolls back payment and event receipt');
$db->query("CREATE TRIGGER bb400_revoke_staff AFTER INSERT ON {$p}bb_audit FOR EACH ROW BEGIN IF NEW.action='assisted_400_credit_applied' THEN UPDATE {$p}staff SET active=0 WHERE staffid=7; END IF; END");
try{essentialThrows(fn()=>$paymentWriter->recordCompanyProviderReceipt($a,7,new \BancoBrasil\Cnab400ReviewedReceipt($event400,$proof400)),'bb_reconciliation_access_denied','late authorization revocation is detected');}
finally{$db->query('DROP TRIGGER bb400_revoke_staff');}
essentialCheck((int)$db->query('SELECT active FROM '.$p.'staff WHERE staffid=7')->row()->active===1&&(int)$db->query('SELECT COUNT(*) n FROM '.$p.'invoicepaymentrecords WHERE invoiceid=?',[$invoice400])->row()->n===$countPayments,'late revocation rolls back the native payment and identity changes');
$paid400=$paymentWriter->recordCompanyProviderReceipt($a,7,new \BancoBrasil\Cnab400ReviewedReceipt($event400,$proof400));
essentialCheck($paid400>0&&(int)$db->query('SELECT COUNT(*) n FROM '.$p.'invoicepaymentrecords WHERE invoiceid=?',[$invoice400])->row()->n===$countPayments+1,'synthetic exact confirmed credit creates one native payment');
essentialCheck($paymentWriter->recordCompanyProviderReceipt($a,7,new \BancoBrasil\Cnab400ReviewedReceipt($event400,$proof400))===$paid400,'synthetic financial retry returns the exact same payment');
$changed=$proof400;$changed['reference']='different independent credit';essentialThrows(fn()=>$paymentWriter->recordCompanyProviderReceipt($a,7,new \BancoBrasil\Cnab400ReviewedReceipt($event400,$changed)),'bb_event_receipt_conflict','different evidence cannot overwrite the same credited event');
essentialThrows(fn()=>$paymentWriter->recordCompanyProviderReceipt($b,7,new \BancoBrasil\Cnab400ReviewedReceipt($event400,$proof400)),'bb_event_unavailable','another company cannot apply the source event');
echo 'CNAB400 isolated financial adapter: synthetic explicit gate, exact nominal, idempotence and company scope tested; no bank authentication or transmission.'.PHP_EOL;
