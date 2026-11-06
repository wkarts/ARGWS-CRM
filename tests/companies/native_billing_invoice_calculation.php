<?php
/** Real canonical writer and lifecycle receipt reconciliation; no replacement amount implementation. */
require __DIR__.'/native_expense_original_form.php';
$start=$GLOBALS['essentialChecks'];
$lifecycle=new CompanyBillingInvoiceLifecycle($db,$p);
$readIssue=static function(int $invoice,string $key,array $payload)use($db,$p,$lifecycle,$a):array{
    return IssuerTransaction::run($db,$p,$lifecycle->tables(),static fn()=>$lifecycle->inOwned($a,7,$invoice,$key,7,$payload));
};
$legacyPayload=['client_id'=>$ca,'date'=>'2026-11-05','due_date'=>'2026-12-05','currency'=>1,
    'items'=>[['description'=>'Historical two-tax calculation','qty'=>'1.00','rate'=>'0.05','taxes'=>[['name'=>'First','rate'=>'10.00'],['name'=>'Second','rate'=>'10.00']]]],
    'note'=>''];
$invoiceWriter=new CompanyInvoices($db,$p);
$legacyKey=hash('sha256','native-expense-lifecycle-historical-contract');
$legacyInvoice=$invoiceWriter->create($a,7,$legacyPayload,$legacyKey);
$legacyReceipt=$db->query('SELECT * FROM '.$p.'company_invoice_requests WHERE request_key=?',[$legacyKey])->row_array();
$legacyPosition=$readIssue($legacyInvoice,$legacyKey,$legacyPayload);
essentialCheck($legacyPosition['original_total']==='0.07'&&$legacyPosition['total']==='0.07','Absent contract retains the actual historical independent-tax rounding');
$legacyAmounts=CompanyInvoiceAmounts::calculate($legacyPayload['items']);
$legacyCanonical=array_merge($legacyAmounts,['client_id'=>$ca,'date'=>$legacyPayload['date'],'due_date'=>$legacyPayload['due_date'],'currency'=>1,'note'=>'']);
essentialCheck(hash('sha256',json_encode($legacyCanonical,JSON_THROW_ON_ERROR))===$legacyReceipt['input_hash'],'Absent contract retains the pre-change canonical receipt hash bytes');
essentialCheck($invoiceWriter->create($a,7,$legacyPayload,$legacyKey)===$legacyInvoice&&$db->query('SELECT * FROM '.$p.'company_invoice_requests WHERE request_key=?',[$legacyKey])->row_array()===$legacyReceipt,'Historical request replay preserves the same invoice and exact receipt');
$nativePayload=$legacyPayload;$nativePayload['calculation_contract']='native_sales_v1';
$nativePayload['items'][0]['long_description']='Immutable line detail';$nativePayload['items'][0]['unit']='un';
$nativeKey=hash('sha256','native-expense-lifecycle-original-calculation');
$nativeInvoice=$invoiceWriter->create($a,7,$nativePayload,$nativeKey);
$nativeReceipt=$db->query('SELECT * FROM '.$p.'company_invoice_requests WHERE request_key=?',[$nativeKey])->row_array();
$nativePosition=$readIssue($nativeInvoice,$nativeKey,$nativePayload);
essentialCheck($nativePosition['original_total']==='0.06'&&$nativePosition['total']==='0.06','Explicit native contract reconciles base 0.05 plus two 10-percent taxes with the actual writer');
essentialCheck($invoiceWriter->create($a,7,$nativePayload,$nativeKey)===$nativeInvoice&&$db->query('SELECT * FROM '.$p.'company_invoice_requests WHERE request_key=?',[$nativeKey])->row_array()===$nativeReceipt,'Native request replay preserves invoice, metadata and immutable receipt');
$tampered=$nativePayload;$tampered['items'][0]['rate']='0.06';
essentialThrows(fn()=>$readIssue($nativeInvoice,$nativeKey,$tampered),'issuer_billing_invoice_unconfirmed','Changing the original monetary payload cannot reconcile with the genuine issue receipt');
$tampered=$nativePayload;unset($tampered['calculation_contract']);
essentialThrows(fn()=>$readIssue($nativeInvoice,$nativeKey,$tampered),'issuer_billing_invoice_unconfirmed','Removing the issue calculation contract cannot reinterpret an existing receipt');
$tampered=$legacyPayload;$tampered['calculation_contract']='native_sales_v1';
essentialThrows(fn()=>$readIssue($legacyInvoice,$legacyKey,$tampered),'issuer_billing_invoice_unconfirmed','Adding a native contract cannot reinterpret a historical issue');
$tampered=$nativePayload;$tampered['items'][0]['unit']='changed';
essentialThrows(fn()=>$readIssue($nativeInvoice,$nativeKey,$tampered),'issuer_billing_invoice_unconfirmed','Line metadata remains part of the canonical immutable receipt');
$db->query('UPDATE '.$p.'company_invoice_requests SET input_hash=? WHERE request_key=?',[str_repeat('0',64),$nativeKey]);
try{essentialThrows(fn()=>$readIssue($nativeInvoice,$nativeKey,$nativePayload),'issuer_billing_invoice_unconfirmed','Tampered receipt hashes fail closed without rewriting the receipt');}
finally{$db->query('UPDATE '.$p.'company_invoice_requests SET input_hash=? WHERE request_key=?',[$nativeReceipt['input_hash'],$nativeKey]);}
essentialCheck($readIssue($nativeInvoice,$nativeKey,$nativePayload)['original_total']==='0.06'&&$legacyReceipt===$db->query('SELECT * FROM '.$p.'company_invoice_requests WHERE request_key=?',[$legacyKey])->row_array(),'Read verification never upgrades or modifies older receipts');
echo 'Native billing calculation reconciliation: '.($GLOBALS['essentialChecks']-$start).' added actual writer/receipt checks passed ('.$db->driver().').'.PHP_EOL;
