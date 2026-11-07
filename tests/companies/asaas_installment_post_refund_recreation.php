<?php
/** Mixed fully refunded/deleted predecessor, actual SQL/journals/writer and simulated provider. */
require __DIR__.'/asaas_installment_recreation.php';
$start=$GLOBALS['essentialChecks'];$db->query('UPDATE '.$p.'modules SET installed_version=? WHERE module_name=?',['2.0.4','asaas']);
$secondRoot=$next;$replacement=[];$replacementGroup=[];$replacementPosts=0;
$closedTransport=function($env,$method,$path,$payload,$secret)use(&$created,&$replacement,&$replacementGroup,&$replacementPosts,$recreateTransport){
 if($method==='DELETE'&&$path==='payments/pay_successor_2'){$created[1]['deleted']=true;return ['id'=>'pay_successor_2','deleted'=>true];}
 if($method==='POST'&&$path==='payments'){$replacementPosts++;foreach([1,2] as $n)$replacement[$n-1]=array_replace($created[$n-1],['id'=>'pay_after_refund_'.$n,'installment'=>'grp_after_refund','status'=>'PENDING','deleted'=>false,'refunds'=>[],'paymentDate'=>null,'creditDate'=>null,'dueDate'=>$n===1?$payload['dueDate']:'2027-04-05','externalReference'=>$payload['externalReference']]);$replacementGroup=['id'=>'grp_after_refund','customer'=>'cus_b','billingType'=>'PIX','installmentCount'=>2,'value'=>'105.53','deleted'=>false];throw new RuntimeException('synthetic response lost after creation');}
 if(str_starts_with($path,'payments?externalReference='))return ['data'=>$replacement,'hasMore'=>false,'totalCount'=>count($replacement)];
 if($path==='installments/grp_after_refund')return $replacementGroup;
 if($path==='installments/grp_after_refund/payments?limit=100&offset=0')return ['data'=>$replacement,'hasMore'=>false,'totalCount'=>count($replacement)];
 foreach($replacement as $n=>$member)if($path==='payments/'.$member['id']){if($method==='PUT'){$replacement[$n]['dueDate']=$payload['dueDate'];$replacement[$n]['value']=$payload['value'];}return $replacement[$n];}
 return $recreateTransport($env,$method,$path,$payload,$secret);
};
$db->query('UPDATE '.$p.'asaas_payment_origins SET state=?,last_code=? WHERE id=?',['review_required','company_adverse_event',$paidOrigin]);
essentialThrows(fn()=>$asaas->observeInstallment($b,7,$secondRoot,'pay_successor_2',$dec,$closedTransport),'asaas_installment_refund_closure_conflict','unexplained adverse state blocks the group');
$db->query('INSERT INTO '.$p.'asaas_company_events(account_row,event_id,event_type,remote_id,payload_hash,state,created_at) VALUES(?,?,?,?,?,?,?)',[$accounts[$b],'synthetic-full-refund-event','PAYMENT_REFUNDED','pay_successor_1',hash('sha256','synthetic-full-refund-event'),'review_required',gmdate('Y-m-d H:i:s',time()-1)]);
$refunds->refresh($b,7,$refundKey,$dec,$closedTransport); // New event requires a newer canonical query before closure.
$asaas->removeInstallment($b,7,$secondRoot,'pay_successor_2',hash('sha256','remove-after-sibling-refund'),$dec,$closedTransport);
$beforeReceipts=$db->query('SELECT * FROM '.$p.'invoicepaymentrecords WHERE invoiceid=? ORDER BY id',[$invoice])->result_array();$beforePostings=$db->query('SELECT * FROM '.$p.'company_asaas_refund_postings WHERE invoice_id=? ORDER BY payment_id',[$invoice])->result_array();
$proposal=$asaas->reviewInstallmentRecreation($b,7,$secondRoot,2,'2027-03-05',$dec,$closedTransport);
essentialCheck($proposal['amount']==='105.53'&&$replacementPosts===0,'full refund plus deleted remainder permits review without remote creation');
essentialThrows(fn()=>$asaas->reviewInstallmentRecreation($a,7,$secondRoot,2,'2027-03-05',$dec,$closedTransport),'asaas_installment_origin_denied','foreign company cannot reuse refund closure');
$posting=$beforePostings[0];$db->query('UPDATE '.$p.'company_asaas_refund_postings SET payload_hash=? WHERE request_key=?',[str_repeat('0',64),$posting['request_key']]);
essentialThrows(fn()=>$asaas->recreateInstallmentGroup($b,7,$secondRoot,2,'2027-03-05',$proposal['key'],true,$dec,$closedTransport),'company_asaas_full_refund_proof_review','corrupt native refund prevents new financial obligation');$db->query('UPDATE '.$p.'company_asaas_refund_postings SET payload_hash=? WHERE request_key=?',[$posting['payload_hash'],$posting['request_key']]);essentialCheck($replacementPosts===0,'no POST while full reversal evidence is invalid');
$result=$asaas->recreateInstallmentGroup($b,7,$secondRoot,2,'2027-03-05',$proposal['key'],true,$dec,$closedTransport);$thirdRoot=$result['origin_id'];
essentialCheck($result['complete']&&$thirdRoot!==$secondRoot&&$replacementPosts===1,'mixed closed group binds a third generation after one unknown-response POST');
essentialCheck($asaas->recoverInstallmentRecreation($b,7,$secondRoot,$proposal['key'],$dec,$closedTransport)['origin_id']===$thirdRoot&&$replacementPosts===1,'recreation replay resolves same successor');
essentialCheck($beforeReceipts===$db->query('SELECT * FROM '.$p.'invoicepaymentrecords WHERE invoiceid=? ORDER BY id',[$invoice])->result_array()&&$beforePostings===$db->query('SELECT * FROM '.$p.'company_asaas_refund_postings WHERE invoice_id=? ORDER BY payment_id',[$invoice])->result_array(),'all original payments and reversal postings remain byte-identical');
essentialCheck($writer->recordCompanyAsaasFullRefund($b,7,$refundSource)===$refundPosting,'historical full refund replay remains idempotent after retirement');
$linkRow=$db->query("SELECT * FROM {$p}asaas_company_proofs WHERE origin_id=? AND operation='group_next'",[$secondRoot])->row_array();$frozen=json_decode($linkRow['result_json'],true,32,JSON_THROW_ON_ERROR);
essentialCheck($frozen['retired']['pay_successor_1']['type']==='refunded'&&$frozen['retired']['pay_successor_1']['posting_hash']===$posting['payload_hash']&&!isset($frozen['retired']['pay_successor_2']['type']),'succession freezes full reversal and deletion as distinct evidence');
$replacement[0]['status']='RECEIVED';$replacement[0]['paymentDate']=$originalResponse['paymentDate'];$replacement[0]['creditDate']=$originalResponse['creditDate'];$proof=$asaas->observeInstallment($b,7,$thirdRoot,'pay_after_refund_1',$dec,$closedTransport);$writer->recordCompanyProviderReceipt($b,7,new \Asaas\CompanyInstallmentReceipt($thirdRoot,'pay_after_refund_1',$proof));
$position=$s->position($b,7,$invoice);essentialCheck($position['received_original']==='105.52'&&$position['received']==='52.76'&&$position['receivable']==='52.77','new generation receives only reinstated principal, preserving original and reversed totals');
$changes=['pay_after_refund_2'=>['value'=>'52.77','due_date'=>'2027-05-05']];$edit=$asaas->reviewInstallmentEdit($b,7,$thirdRoot,$changes,$dec,$closedTransport);essentialCheck($asaas->executeInstallmentEdit($b,7,$thirdRoot,$changes,$edit['key'],true,$dec,$closedTransport)['complete'],'unpaid new member remains editable alongside fully reversed historical receipt');
$db->query('INSERT INTO '.$p.'asaas_company_events(account_row,event_id,event_type,remote_id,payload_hash,state,created_at) VALUES(?,?,?,?,?,?,?)',[$accounts[$b],'synthetic-late-paid-event','PAYMENT_RECEIVED','pay_successor_1',hash('sha256','synthetic-late-paid-event'),'review_required',gmdate('Y-m-d H:i:s',time()+1)]);$lateId=(int)$db->insert_id();
essentialThrows(fn()=>$asaas->observeInstallment($b,7,$thirdRoot,'pay_after_refund_2',$dec,$closedTransport),'asaas_installment_refund_closure_conflict','new event on retired refunded member blocks current operations for review');
$db->query('DELETE FROM '.$p.'asaas_company_events WHERE id=?',[$lateId]);
echo 'Installment post-refund recreation: '.($GLOBALS['essentialChecks']-$start).' additional MySQL checks; provider simulated.'.PHP_EOL;
