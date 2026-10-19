<?php
/** Unsupported anticipation history remains sticky even when a later canonical GET clears its flag. */
require __DIR__.'/asaas_card_settlement.php';$anticipationStart=$GLOBALS['essentialChecks'];
$legacyAnticipationBefore=$db->query("SELECT * FROM {$p}invoices WHERE id=42")->result_array();
foreach(['before-receipt','after-receipt'] as $mode){
 $config=$asaas->accounts($b,7)[0];$hook=$asaas->rotateWebhook($b,7,$accounts[$b],$config['revision']);[$invoice,$origin,$proof,$remote]=asaasFreshHostedSettlement($b,'anticipated-history-'.$mode);$payment=null;$receiptBefore=null;$paymentBefore=null;
 if($mode==='after-receipt'){$payment=$writer->recordCompanyProviderReceipt($b,7,new \Asaas\CompanyReceivedReceipt($origin,$proof));$receiptBefore=$db->query("SELECT * FROM {$p}asaas_company_receipts WHERE origin_id=?",[$origin])->row_array();$paymentBefore=$db->query("SELECT * FROM {$p}invoicepaymentrecords WHERE id=?",[$payment])->row_array();}
 $otherOriginsBefore=$db->query("SELECT id,state FROM {$p}asaas_payment_origins WHERE id<>? ORDER BY id",[$origin])->result_array();
 $headers=['Content-Type'=>'application/json','asaas-access-token'=>$hook['one_time_token']];$event='evt_anticipated_sticky_'.str_replace('-','_',$mode);$body=json_encode(['id'=>$event,'event'=>'PAYMENT_ANTICIPATED','payment'=>['id'=>$remote,'anticipated'=>true]],JSON_THROW_ON_ERROR);
 essentialCheck($asaas->receive($hook['public_id'],$headers,$body)['status']===200,'authenticated anticipation history persists: '.$mode);
 essentialCheck($db->query("SELECT state FROM {$p}asaas_company_events WHERE account_row=? AND event_id=?",[$accounts[$b],$event])->row()->state==='review_required','anticipation callback immediately becomes adverse history: '.$mode);
 essentialCheck($db->query("SELECT state FROM {$p}asaas_payment_origins WHERE id=?",[$origin])->row()->state==='review_required','anticipation marks only exact immutable origin in review: '.$mode);
 essentialCheck($db->query("SELECT id,state FROM {$p}asaas_payment_origins WHERE id<>? ORDER BY id",[$origin])->result_array()===$otherOriginsBefore,'A/B other origins and PIX states stay unchanged: '.$mode);
 // The simulated canonical response still claims non-anticipated RECEIVED; history must win.
 $responses[$remote]['anticipated']=false;$proof=$asaas->query($b,7,$origin,$dec,$checkoutTransport);$stored=$db->query("SELECT state,result_json FROM {$p}asaas_company_proofs WHERE id=?",[$proof])->row_array();$safe=json_decode($stored['result_json'],true);
 essentialCheck($safe['status']==='RECEIVED'&&$safe['card_settlement']['unanticipated']===true&&$stored['state']==='review_required','later canonical non-anticipated GET does not clear persisted adverse state: '.$mode);
 essentialThrows(fn()=>$writer->recordCompanyProviderReceipt($b,7,new \Asaas\CompanyReceivedReceipt($origin,$proof)),'asaas_company_receipt_requires_review','anticipation cannot silently reenter ordinary nominal receipt: '.$mode);
 essentialCheck($asaas->receive($hook['public_id'],$headers,$body)['duplicate']===true,'anticipation redelivery is idempotent: '.$mode);
 $count=(int)$db->query("SELECT COUNT(*) n FROM {$p}invoicepaymentrecords WHERE invoiceid=?",[$invoice])->row()->n;essentialCheck($count===($payment===null?0:1),'adverse history never creates a receipt or an automatic reversal: '.$mode);
 if($payment!==null){essentialCheck($db->query("SELECT * FROM {$p}asaas_company_receipts WHERE origin_id=?",[$origin])->row_array()===$receiptBefore&&$db->query("SELECT * FROM {$p}invoicepaymentrecords WHERE id=?",[$payment])->row_array()===$paymentBefore,'already committed canonical key/evidence/payment remain byte-identical after later adverse event');}
}
essentialCheck($db->query("SELECT * FROM {$p}invoices WHERE id=42")->result_array()===$legacyAnticipationBefore,'anticipation policy never modifies legacy NULL invoice');
echo 'Asaas151 sticky anticipation history: '.($GLOBALS['essentialChecks']-$anticipationStart).' added / '.$GLOBALS['essentialChecks'].' cumulative checks passed ('.$db->driver().'). Provider simulated.'.PHP_EOL;
