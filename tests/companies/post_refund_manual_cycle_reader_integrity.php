<?php
require __DIR__.'/post_refund_manual_cycle_readers.php';
$perm=$access->page($b,7,$customerB)[$contact];$access->set($b,7,$customerB,$contact,$perm['revision'],'1');
$token=$tokenService->issue($b,7,'Closed-chain read integrity',['invoices:read','invoices:pdf','reports:read'],1,hash('sha256','closed-cycle-integrity-token'));
$record=$db->query('SELECT * FROM '.$p.'company_post_refund_cancellations WHERE issuer_id=? AND invoice_id=?',[$b,$rfB['invoice']])->row_array();
$posting=$db->query('SELECT * FROM '.$p.'company_asaas_refund_postings WHERE issuer_id=? AND invoice_id=?',[$b,$rfB['invoice']])->row_array();
$input=json_decode($record['input_json'],true,32,JSON_THROW_ON_ERROR);
$audit=$db->query('SELECT * FROM '.$p.'issuers_audit WHERE issuer_id=? AND subject_id=? AND event=?',[$b,$posting['origin_id'],'company_post_refund_invoice_cancelled'])->row_array();
$eventKey=hash('sha256','company-journal:invoice-cancelled:'.$b.':'.$rfB['invoice'].':'.$input['revision']);
$event=$db->query('SELECT * FROM '.$p.'company_financial_events WHERE evidence_key=?',[$eventKey])->row_array();
foreach(['proof_resealed','proof_removed_resealed','audit','journal']as$kind){
 if(str_starts_with($kind,'proof')){$bad=$input;if($kind==='proof_resealed')$bad['manual_reversal_proof']['reversal_record_hash']=str_repeat('0',64);else unset($bad['manual_reversal_proof']);$r=$record;$r['input_json']=json_encode($bad,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);$r['input_hash']=hash('sha256',$r['input_json']);$r['record_hash']=CompanyPostRefundAsaasSource::receiptHeader($r);$db->query('UPDATE '.$p.'company_post_refund_cancellations SET input_json=?,input_hash=?,record_hash=? WHERE request_key=?',[$r['input_json'],$r['input_hash'],$r['record_hash'],$r['request_key']]);}
 elseif($kind==='audit')$db->query('UPDATE '.$p.'issuers_audit SET metadata_json=? WHERE id=?',['{}',$audit['id']]);
 else$db->query('UPDATE '.$p.'company_financial_events SET kind=? WHERE evidence_key=?',['invoice_issued',$eventKey]);
 try{
  essentialThrows(fn()=>$s->document($b,7,$rfB['invoice']),'company_post_refund_manual_cycle_conflict','staff closed history denies '.$kind);
  essentialThrows(fn()=>$portalInvoices->document($session,$b,$rfB['invoice']),'company_post_refund_manual_cycle_conflict','private portal denies closed-chain '.$kind);
  essentialThrows(fn()=>$apiService->invoice($token['secret'],$rfB['invoice']),'company_post_refund_manual_cycle_conflict','API JSON denies closed-chain '.$kind);
  essentialThrows(fn()=>$apiService->invoicePdf($token['secret'],$rfB['invoice']),'company_post_refund_manual_cycle_conflict','private PDF rejects closed-chain '.$kind);
  essentialThrows(fn()=>$apiService->financial($token['secret']),'company_report_balance_review','report rejects closed-chain '.$kind);
 }finally{
  $db->query('UPDATE '.$p.'company_post_refund_cancellations SET input_json=?,input_hash=?,record_hash=? WHERE request_key=?',[$record['input_json'],$record['input_hash'],$record['record_hash'],$record['request_key']]);
  $db->query('UPDATE '.$p.'issuers_audit SET metadata_json=? WHERE id=?',[$audit['metadata_json'],$audit['id']]);
  $db->query('UPDATE '.$p.'company_financial_events SET kind=? WHERE evidence_key=?',[$event['kind'],$eventKey]);
 }
}
essentialCheck($portalInvoices->document($session,$b,$rfB['invoice'])->data()['status']===5&&$apiService->invoice($token['secret'],$new)['document']['status']===1,'restored old closed-chain and independent new invoice remain readable');
$tokenService->revoke($b,7,$token['token_id']);
echo 'Recorded two-history proof/audit/journal tamper in staff/portal/API/PDF/reports: '.$GLOBALS['essentialChecks'].' cumulative checks ('.$db->driver().').'.PHP_EOL;
