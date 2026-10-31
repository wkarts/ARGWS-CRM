<?php
require __DIR__.'/native_invoice_draft_creation_tags.php';
require_once APPPATH.'services/NativeCompanyInvoiceCommunicationState.php';
$writer=new CompanyInvoices($db,$p);$state=new NativeCompanyInvoiceCommunicationState($db,$p);$key=hash('sha256','created-native-reminder');
$guard=function()use($writer,$state,$db,$p,$a,$key){$row=$db->query('SELECT invoice_id FROM '.$p.'company_invoice_requests WHERE request_key=?',[$key])->row_array();if(!$row)return;$state->setCreatedPreferenceInOwned($a,12,(int)$row['invoice_id'],0,$writer);};
$id=$writer->create($a,12,$draftInput,$key,$guard);
essentialCheck((int)$db->query('SELECT cancel_overdue_reminders FROM '.$p.'invoices WHERE id=?',[$id])->row_array()['cancel_overdue_reminders']===0,'create-only staff saves explicit native preference in issuance transaction');
essentialThrows(fn()=>$state->setCreatedPreferenceInOwned($a,12,$id,1,$writer),'issuer_owned_transaction_required','cannot alter created preference after transaction');
essentialThrows(fn()=>IssuerTransaction::run($db,$p,['invoices','issuers_audit','issuers_lock'],fn()=>$state->setCreatedPreferenceInOwned($a,12,$id,1,$writer)),'company_invoice_creation_proof_required','new transaction cannot reuse expired creation proof');
essentialCheck($writer->create($a,12,$draftInput,$key)===$id,'writer replay remains idempotent');
echo 'Native invoice created reminder preference: '.$GLOBALS['essentialChecks']." cumulative SQLite checks; cron dispatch not claimed.\n";
