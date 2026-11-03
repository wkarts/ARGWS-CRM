<?php
require __DIR__.'/native_payment_notice_delivery.php';
$beforeChecks=$GLOBALS['essentialChecks'];$batchWriter=new \app\services\InvoicePaymentWriter($db);
$makeBatch=function(string $suffix)use($s,$a,$input,$db,$p,$mode):array{
    $items=[];
    foreach([1,2]as$n){$id=$s->create($a,7,$input,hash('sha256','batch-invoice-'.$suffix.$n));$db->query('UPDATE '.$p.'invoices SET allowed_payment_modes=? WHERE id=?',[serialize([$mode['id']]),$id]);
        $items[]=['invoice_id'=>$id,'input'=>['amount'=>'2.50','date'=>'2026-10-29','reference'=>'batch-'.$suffix.$n,'note'=>'Actual batch note'],'mode'=>$mode['id'],'mode_revision'=>$mode['revision'],'invoice_revision'=>$s->revision($a,7,$id),'notice'=>false];
    }return$items;
};
$items=$makeBatch('valid');$key=hash('sha256','batch-valid');$ids=$batchWriter->recordCompanyManualBatch($a,7,$items,$key);
essentialCheck(count($ids)===2&&$ids[0]!==$ids[1],'Batch stores two distinct real payments');
foreach($ids as$n=>$id){$row=$db->query('SELECT invoiceid,amount,note FROM '.$p.'invoicepaymentrecords WHERE id=?',[$id])->row_array();essentialCheck((int)$row['invoiceid']===$items[$n]['invoice_id']&&\app\services\InvoicePaymentWriter::minorUnits($row['amount'])===250&&$row['note']==='Actual batch note','Batch preserves exact amount, note and invoice binding');}
essentialCheck($batchWriter->recordCompanyManualBatch($a,7,$items,$key)===$ids,'Exact batch replay is idempotent after invoice balances changed');
$changed=$items;$changed[1]['input']['amount']='3.00';essentialThrows(fn()=>$batchWriter->recordCompanyManualBatch($a,7,$changed,$key),'company_payment_request_conflict','Changed batch cannot reuse a request key');
$bad=$makeBatch('rollback');$bad[1]['input']['amount']='999999.00';
$counts=fn()=>[$db->query('SELECT COUNT(*) n FROM '.$p.'invoicepaymentrecords')->row()->n,$db->query('SELECT COUNT(*) n FROM '.$p.'company_payment_receipts')->row()->n,$db->query('SELECT COUNT(*) n FROM '.$p.'company_payment_notice_intents')->row()->n,$db->query('SELECT COUNT(*) n FROM '.$p.'issuers_audit')->row()->n];$before=$counts();
try{$batchWriter->recordCompanyManualBatch($a,7,$bad,hash('sha256','batch-rollback'));throw new RuntimeException('Overpayment accepted');}catch(DomainException $error){essentialCheck(in_array($error->getMessage(),['company_payment_exceeds_balance','company_payment_exceeds_due','company_payment_amount_exceeds_balance'],true),'Invalid second amount fails on financial guard: '.$error->getMessage());}
essentialCheck($counts()===$before,'Failed second payment rolls back first payment, receipts, notices and audit');
essentialThrows(fn()=>$batchWriter->recordCompanyManualBatch($b,7,$items,hash('sha256','batch-cross')),'native_payment_mode_denied','Foreign issuer cannot record the batch');
$stale=$makeBatch('stale');$stale[1]['invoice_revision']=str_repeat('0',64);$before=$counts();essentialThrows(fn()=>$batchWriter->recordCompanyManualBatch($a,7,$stale,hash('sha256','batch-stale')),'company_invoice_stale','Second stale invoice aborts batch');essentialCheck($counts()===$before,'Stale invoice rolls back all preceding writes');
if($db->driver()==='sqlite'){
    $rollback=$makeBatch('audit');$before=$counts();$db->query("CREATE TRIGGER payment_batch_audit_failure BEFORE INSERT ON {$p}issuers_audit WHEN NEW.event='company_payment_batch_recorded' BEGIN SELECT RAISE(ABORT,'synthetic batch audit failure'); END");
    try{essentialThrows(fn()=>$batchWriter->recordCompanyManualBatch($a,7,$rollback,hash('sha256','batch-audit')),'issuer_storage_failed','Late batch audit failure rolls back whole batch');}finally{$db->query('DROP TRIGGER payment_batch_audit_failure');}
    essentialCheck($counts()===$before,'Audit failure preserves all original balances and history');
}
echo 'Original batch payment writer: '.($GLOBALS['essentialChecks']-$beforeChecks).' additional checks passed; no SMTP or bank transport.'.PHP_EOL;
