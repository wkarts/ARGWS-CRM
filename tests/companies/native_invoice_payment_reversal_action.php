<?php
require __DIR__.'/native_invoice_selected_reversal.php';
require_once APPPATH.'services/NativeCompanyInvoicePaymentReversal.php';
$action=new NativeCompanyInvoicePaymentReversal($db,$p);
$payment=$writer->recordCompanyManualWithMode($a,7,$invoiceId,['amount'=>'20.00','date'=>'2026-10-19','reference'=>'original-action-payment','note'=>'Synthetic'],hash('sha256','original-action-payment'),$mode['id'],$mode['revision'],false);
$post=['revision'=>$s->paymentRevision($a,7,$invoiceId),'request_key'=>hash('sha256','original-action-reversal'),'reversal'=>['payment_id'=>(string)$payment,'amount'=>'7.00','date'=>'2026-10-19','reference'=>'original-action-reversal','reason'=>'Synthetic manual correction']];
$snapshot=function()use($db,$p){$result=[];foreach(['invoices','invoicepaymentrecords','company_payment_receipts','company_credit_entries','company_financial_events','issuers_audit']as$table)$result[$table]=$db->query('SELECT * FROM '.$p.$table)->result_array();return$result;};
$before=$snapshot();$bad=$post;$bad['revision']=str_repeat('0',64);
essentialThrows(fn()=>$action->submit($a,7,$payment,$invoiceId,$bad),'company_invoice_stale','original removal checks financial revision inside the writer');
essentialThrows(fn()=>$action->submit($b,7,$payment,$invoiceId,$post),'company_invoice_access_denied','foreign original payment ID cannot be reversed');
essentialThrows(fn()=>$action->submit($a,12,$payment,$invoiceId,$post),'company_credit_access_denied','restricted actor cannot reverse a receipt');
essentialCheck($snapshot()===$before,'denied original commands have no financial or audit effects');
$action->submit($a,7,$payment,$invoiceId,$post);$after=$snapshot();
essentialCheck($s->position($a,7,$invoiceId)['received']==='13.00'&&$s->position($a,7,$invoiceId)['received_original']==='30.00','partial original command preserves all earlier receipts and reversals');
essentialCheck($s->paymentRevision($a,7,$invoiceId)!==$post['revision'],'partial reversal changes the revision even while the invoice remains partially paid');
$action->submit($a,7,$payment,$invoiceId,$post);essentialCheck($snapshot()===$after,'exact original POST replay has no duplicate effects');
$bad=$post;$bad['reversal']['reason']='Changed intent';essentialThrows(fn()=>$action->submit($a,7,$payment,$invoiceId,$bad),'company_credit_request_conflict','same key cannot change reversal intent');
$bad=$post;$bad['request_key']=hash('sha256','original-action-stale');essentialThrows(fn()=>$action->submit($a,7,$payment,$invoiceId,$bad),'company_invoice_stale','fresh key cannot reuse an obsolete partial balance');
$next=$post;$next['revision']=$s->paymentRevision($a,7,$invoiceId);$next['request_key']=hash('sha256','original-action-final');$next['reversal']['amount']='13.00';$next['reversal']['reference']='original-action-final';
$db->failOn='INSERT INTO '.$p.'issuers_audit';try{essentialThrows(fn()=>$action->submit($a,7,$payment,$invoiceId,$next),'issuer_storage_failed','late audit failure aborts the original action');}finally{$db->failOn=null;}
essentialCheck($snapshot()===$after,'late failure rolls back the receipt, balance, ledger and audit together');
$action->submit($a,7,$payment,$invoiceId,$next);essentialCheck($s->position($a,7,$invoiceId)['received']==='0.00','retry completes the remainder without deleting history');
echo 'Original payment reversal action: ACL, financial CAS, replay and rollback passed.'.PHP_EOL;
