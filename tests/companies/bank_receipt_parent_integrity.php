<?php
/** Independent receipt foreign keys must not substitute the canonical invoice/payment parent relation. */
require __DIR__.'/bank_components_writer_core.php';
$victim=$s->create($a,7,$inputCore,$ck('receipt-parent-victim'));$corruptKey=$ck('receipt-parent-corrupt');$foreignReceipt=$db->query('SELECT input_hash FROM '.$p.'company_payment_receipts WHERE payment_id=?',[$payment])->row_array();$beforeNative=(int)$db->query('SELECT COUNT(*) n FROM '.$p.'invoicepaymentrecords')->row()->n;
// All individual foreign keys remain valid: this row links one existing A invoice to another A invoice's existing payment.
$db->query('INSERT INTO '.$p.'company_payment_receipts(request_key,issuer_id,actor_id,input_hash,invoice_id,payment_id,created_at) VALUES(?,?,?,?,?,?,?)',[$corruptKey,$a,7,$foreignReceipt['input_hash'],$victim,$payment,gmdate('Y-m-d H:i:s')]);
try{
    essentialThrows(fn()=>$s->position($a,7,$victim),'company_invoice_balance_review','a receipt claiming invoiceA with payment parentB cannot disappear from invoiceA position');
    essentialThrows(fn()=>$s->document($a,7,$victim),'company_invoice_balance_review','authorized PDF DTO denies the entire mismatched receipt relation');
    essentialThrows(fn()=>$writer->recordCompanyManual($a,7,$victim,['amount'=>'100.00','date'=>'2026-10-19','reference'=>'receipt-parent-extra-cash'],$ck('receipt-parent-extra-cash')),'company_invoice_balance_review','a new receipt cannot ignore a cross-parent canonical receipt already attached to this invoice');
    essentialCheck((int)$db->query('SELECT COUNT(*) n FROM '.$p.'invoicepaymentrecords')->row()->n===$beforeNative,'receipt-parent veto leaves native cash/status and journal without new effects');
}finally{$db->query('DELETE FROM '.$p.'company_payment_receipts WHERE request_key=?',[$corruptKey]);}
essentialCheck($s->position($a,7,$victim)['receivable']==='100.00','removing the injected mismatched proof restores the original verified open principal');
echo 'Receipt parent integrity: '.$GLOBALS['essentialChecks'].' cumulative SQL checks; no external provider call.'.PHP_EOL;
