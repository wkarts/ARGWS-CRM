<?php
require __DIR__.'/asaas_installment_review.php';
require_once APP_MODULES_PATH.'asaas/libraries/Asaas/CompanyInstallmentReceipt.php';
$start=$GLOBALS['essentialChecks'];$db->query('UPDATE '.$p.'modules SET installed_version=? WHERE module_name=?',['1.8.3','asaas']);
$bound=$asaas->reviewLegacyInstallments($b,7,$invoice,$accounts[$b],'pay_group_one',$dec,$transport,$review()['review_hash']);$root=$bound['origin_id'];
$first['status']='RECEIVED';$first['paymentDate']=$originalResponse['paymentDate'];$first['creditDate']=$originalResponse['creditDate'];
$deletes=0;$lost=true;$inFlight=null;
$remote=function($env,$method,$path,$payload,$secret)use(&$first,&$second,&$deletes,&$lost,&$inFlight,$transport){
 if($method==='DELETE'){essentialCheck($path==='payments/pay_group_two'&&$payload===null,'DELETE targets only selected member with no body');$deletes++;$second['deleted']=true;if($inFlight){$fn=$inFlight;$inFlight=null;$fn();}if($lost)throw new RuntimeException('synthetic lost response');return ['id'=>$second['id'],'deleted'=>true];}
 if($path==='payments/pay_group_two')return $second;return $transport($env,$method,$path,$payload,$secret);
};
$proof=$asaas->observeInstallment($b,7,$root,'pay_group_one',$dec,$remote);
$payment=$writer->recordCompanyProviderReceipt($b,7,new \Asaas\CompanyInstallmentReceipt($root,'pay_group_one',$proof));
$before=$db->query('SELECT * FROM '.$p.'invoicepaymentrecords WHERE invoiceid=?',[$invoice])->result_array();
$bindings=$db->query('SELECT * FROM '.$p.'asaas_company_origin_meta WHERE origin_id IN (SELECT id FROM '.$p.'asaas_payment_origins WHERE invoice_id=?) ORDER BY origin_id',[$invoice])->result_array();
$key=hash('sha256','remove-group-member');$remove=fn()=>$asaas->removeInstallment($b,7,$root,'pay_group_two',$key,$dec,$remote);
$n=count($calls);essentialThrows(fn()=>$asaas->removeInstallment($a,7,$root,'pay_group_two',$key,$dec,$remote),'asaas_installment_origin_denied','foreign company cannot delete member');essentialCheck($deletes===0&&count($calls)===$n,'foreign company rejected before HTTP');
essentialThrows(fn()=>$asaas->removeInstallment($b,7,$root,'pay_group_one',$key,$dec,$remote),'asaas_company_remote_financial_history','paid member cannot be deleted');
$db->failOn='INSERT INTO '.$p.'issuers_audit';essentialThrows($remove,'issuer_storage_failed','failed intention audit prevents DELETE');$db->failOn=null;essentialCheck($deletes===0,'no dispatch before durable intent');
$inFlight=function()use($asaas,$b,$root,$dec,$remote,$writer,$remove,$db,$p){essentialCheck($remove()['state']==='sending','concurrent retry observes sending without a second DELETE');$proof=$asaas->observeInstallment($b,7,$root,'pay_group_two',$dec,$remote);essentialThrows(fn()=>$writer->recordCompanyProviderReceipt($b,7,new \Asaas\CompanyInstallmentReceipt($root,'pay_group_two',$proof)),'asaas_installment_removal_pending','receipt racing in-flight deletion is denied');$db->query('UPDATE '.$p.'staff SET active=0 WHERE staffid=7');};
$result=$remove();essentialCheck($result['state']==='uncertain'&&$deletes===1,'lost DELETE response preserves uncertain intent');
essentialThrows($remove,'asaas_company_access_denied','revoked actor cannot inspect or dispatch');$db->query('UPDATE '.$p.'staff SET active=1 WHERE staffid=7');
essentialCheck($remove()['state']==='uncertain'&&$deletes===1,'retry never repeats DELETE');
essentialThrows(fn()=>$asaas->removeInstallment($b,7,$root,'pay_group_two',hash('sha256','other-key'),$dec,$remote),'asaas_company_remote_request_conflict','different idempotency key cannot repeat DELETE');
$origin=(int)$result['origin_id'];$now=gmdate('Y-m-d H:i:s');
$db->query('INSERT INTO '.$p.'asaas_company_events(account_row,event_id,event_type,remote_id,payload_hash,state,created_at) VALUES(?,?,?,?,?,?,?)',[$accounts[$b],'evt_member_deleted','PAYMENT_DELETED','pay_group_two',hash('sha256','synthetic-deleted'),'review_required',$now]);
$db->query("UPDATE {$p}asaas_payment_origins SET state='review_required',last_code='company_adverse_event' WHERE id=?",[$origin]);
$confirmed=$asaas->refreshInstallmentRemoval($b,7,$root,'pay_group_two',$dec,$remote);essentialCheck($confirmed['state']==='deleted'&&$deletes===1,'canonical GET confirms deletion after adverse webhook without dispatch');
essentialCheck($db->query('SELECT * FROM '.$p.'invoicepaymentrecords WHERE invoiceid=?',[$invoice])->result_array()===$before&&$s->position($b,7,$invoice)['receivable']==='65.53','sibling receipt and remaining native balance preserved');
essentialCheck($db->query('SELECT * FROM '.$p.'asaas_company_origin_meta WHERE origin_id IN (SELECT id FROM '.$p.'asaas_payment_origins WHERE invoice_id=?) ORDER BY origin_id',[$invoice])->result_array()===$bindings,'original binding snapshots never rewritten');
$second['deleted']=false;essentialCheck($asaas->refreshInstallmentRemoval($b,7,$root,'pay_group_two',$dec,$remote)['state']==='review_required','canonical contradiction withdraws confirmation');$second['deleted']=true;
$db->query('UPDATE '.$p.'asaas_company_events SET event_type=? WHERE event_id=?',['PAYMENT_REFUNDED','evt_member_deleted']);
essentialThrows(fn()=>$asaas->refreshInstallmentRemoval($b,7,$root,'pay_group_two',$dec,$remote),'asaas_installment_binding_conflict','refund webhook is never reinterpreted as deletion');
echo 'Installment removal: '.($GLOBALS['essentialChecks']-$start).' additional MySQL checks; all provider calls simulated.'.PHP_EOL;
