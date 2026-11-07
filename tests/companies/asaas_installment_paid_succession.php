<?php
require __DIR__.'/asaas_installment_review.php';
require_once APP_MODULES_PATH.'asaas/libraries/Asaas/CompanyInstallmentReceipt.php';
$start=$GLOBALS['essentialChecks'];$db->query('UPDATE '.$p.'modules SET installed_version=? WHERE module_name=?',['2.0.6','asaas']);
$root=$asaas->reviewLegacyInstallments($b,7,$invoice,$accounts[$b],'pay_group_one',$dec,$transport,$review()['review_hash'])['origin_id'];
$payments=['pay_group_one'=>$first,'pay_group_two'=>$second];$posts=0;$new=[];$newGroup=[];$onPost=null;
$paidTransport=function($env,$method,$path,$payload,$secret)use(&$payments,&$posts,&$new,&$newGroup,&$onPost,$mock){
 if($method==='POST'&&$path==='payments'){$posts++;essentialCheck($payload['totalValue']==='65.53','only unpaid remainder is submitted');foreach([1,2] as $n)$new[$n-1]=array_replace($payments['pay_group_two'],['id'=>'pay_retained_'.$n,'installment'=>'grp_retained','installmentNumber'=>$n,'value'=>$n===1?'32.76':'32.77','netValue'=>$n===1?'31.76':'31.77','dueDate'=>$n===1?$payload['dueDate']:'2027-02-05','externalReference'=>$payload['externalReference'],'deleted'=>false]);$newGroup=['id'=>'grp_retained','customer'=>'cus_b','billingType'=>'PIX','installmentCount'=>2,'value'=>'65.53','deleted'=>false];if($onPost){$f=$onPost;$onPost=null;$f();}throw new RuntimeException('synthetic lost response');}
 if(str_starts_with($path,'payments?externalReference='))return ['data'=>$new,'totalCount'=>count($new),'hasMore'=>false];
 if($path==='installments/grp_retained')return $newGroup;
 if($path==='installments/grp_retained/payments?limit=100&offset=0')return ['data'=>$new,'totalCount'=>count($new),'hasMore'=>false];
 foreach($new as $n=>$member)if($path==='payments/'.$member['id']){if($method==='PUT'){$new[$n]['value']=$payload['value'];$new[$n]['dueDate']=$payload['dueDate'];}if($method==='DELETE')$new[$n]['deleted']=true;return $new[$n];}
 if(str_starts_with($path,'payments/')){$id=substr($path,9);if(isset($payments[$id])){if($method==='DELETE')$payments[$id]['deleted']=true;return $payments[$id];}}
 return $mock($env,$method,$path,$payload,$secret);
};
$payments['pay_group_one']['status']='RECEIVED';$payments['pay_group_one']['paymentDate']=$originalResponse['paymentDate'];$payments['pay_group_one']['creditDate']=$originalResponse['creditDate'];
$proof=$asaas->observeInstallment($b,7,$root,'pay_group_one',$dec,$paidTransport);$payment=$writer->recordCompanyProviderReceipt($b,7,new \Asaas\CompanyInstallmentReceipt($root,'pay_group_one',$proof));
// A legitimate update after the original receipt must be covered by a distinct canonical query.
$oldProofTime=gmdate('Y-m-d H:i:s',time()-10);$db->query('UPDATE '.$p.'asaas_company_proofs SET created_at=? WHERE id=?',[$oldProofTime,$proof]);
$db->query('INSERT INTO '.$p.'asaas_company_events(account_row,event_id,event_type,remote_id,payload_hash,state,created_at) VALUES(?,?,?,?,?,?,?)',[$accounts[$b],'retained-update','PAYMENT_UPDATED','pay_group_one',hash('sha256','synthetic-updated'),'observed',gmdate('Y-m-d H:i:s',time()-2)]);
$asaas->removeInstallment($b,7,$root,'pay_group_two',hash('sha256','delete-unpaid-retain-paid'),$dec,$paidTransport);
$before=$db->query('SELECT * FROM '.$p.'invoicepaymentrecords WHERE invoiceid=?',[$invoice])->result_array();
$proposal=$asaas->reviewInstallmentRecreation($b,7,$root,2,'2027-01-05',$dec,$paidTransport);
essentialCheck($proposal['amount']==='65.53'&&$proposal['retained']==='40.00'&&$posts===0,'review shows retained principal and exact eligible remainder');
essentialThrows(fn()=>$asaas->reviewInstallmentRecreation($a,7,$root,2,'2027-01-05',$dec,$paidTransport),'asaas_installment_origin_denied','foreign actor context rejected');
$db->failOn='INSERT INTO '.$p.'issuers_audit';essentialThrows(fn()=>$asaas->recreateInstallmentGroup($b,7,$root,2,'2027-01-05',$proposal['key'],true,$dec,$paidTransport),'issuer_storage_failed','audit failure cannot dispatch successor');$db->failOn=null;essentialCheck($posts===0,'no POST on failed reservation');
$db->query('CREATE TABLE '.$p.'recreate_calls(id INT AUTO_INCREMENT PRIMARY KEY,reference_key CHAR(64) NOT NULL) ENGINE=InnoDB');register_shutdown_function(fn()=>$db->query('DROP TABLE IF EXISTS '.$p.'recreate_calls'));
$onPost=function()use($db,$p,$b,$root,$proposal,&$payments){
 $observer=new EssentialTestDb(new PDO(getenv('ISSUERS_TEST_DSN'),getenv('ISSUERS_TEST_USER'),getenv('ISSUERS_TEST_PASSWORD')),$p);$blocker=(int)$db->query('SELECT CONNECTION_ID() id')->row()->id;$workers=[];$db->trans_begin();$db->query('SELECT id FROM '.$p.'issuers_lock WHERE id=1 FOR UPDATE');
 try{for($n=0;$n<2;$n++){$pipes=[];$proc=proc_open([PHP_BINARY,__DIR__.'/asaas_installment_recreation.php','recreate-worker',$p],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);fwrite($pipes[0],json_encode(['issuer'=>$b,'group'=>$root,'key'=>$proposal['key'],'payments'=>$payments]));fclose($pipes[0]);$workers[]=[$proc,$pipes];}
  $until=microtime(true)+15;do{$waiters=essentialLockWaiters($observer,$blocker);if($waiters>=2)break;usleep(20000);}while(microtime(true)<$until);essentialCheck($waiters>=2,'two recovery processes wait under actual InnoDB lock with retained paid principal');$db->trans_commit();
  foreach($workers as [$proc,$pipes]){$out=stream_get_contents($pipes[1]);$error=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);essentialCheck(proc_close($proc)===0&&$error==='','concurrent retained-principal recovery completes without diagnostics');essentialCheck(json_decode($out,true,32,JSON_THROW_ON_ERROR)['complete']===false,'in-flight remote absence remains uncertain');}
  essentialCheck((int)$db->query('SELECT COUNT(*) n FROM '.$p.'recreate_calls')->row()->n===0,'concurrent recoverers cannot repeat the already claimed POST');
 }finally{if($db->pdo->inTransaction())$db->trans_rollback();}
};
$result=$asaas->recreateInstallmentGroup($b,7,$root,2,'2027-01-05',$proposal['key'],true,$dec,$paidTransport);$next=$result['origin_id'];
essentialCheck($result['complete']&&$posts===1,'lost response recovers one successor with paid predecessor retained');
essentialCheck($asaas->recoverInstallmentRecreation($b,7,$root,$proposal['key'],$dec,$paidTransport)['origin_id']===$next&&$posts===1,'replay cannot duplicate residual obligation');
essentialCheck($before===$db->query('SELECT * FROM '.$p.'invoicepaymentrecords WHERE invoiceid=?',[$invoice])->result_array(),'paid native receipt remains byte-identical');
$position=$s->position($b,7,$invoice);essentialCheck($position['received']==='40.00'&&$position['receivable']==='65.53','succession does not change paid principal or balance');
$db->query('UPDATE '.$p.'invoicepaymentrecords SET note=? WHERE id=?',['tampered',$payment]);essentialThrows(fn()=>$asaas->observeInstallment($b,7,$next,'pay_retained_1',$dec,$paidTransport),'asaas_installment_retained_receipt_conflict','retained receipt alteration blocks active successor');$db->query('UPDATE '.$p.'invoicepaymentrecords SET note=? WHERE id=?',[$before[0]['note'],$payment]);
$db->query('INSERT INTO '.$p.'asaas_company_events(account_row,event_id,event_type,remote_id,payload_hash,state,created_at) VALUES(?,?,?,?,?,?,?)',[$accounts[$b],'retained-chargeback','PAYMENT_CHARGEBACK_REQUESTED','pay_group_one',hash('sha256','synthetic-chargeback'),'review_required',gmdate('Y-m-d H:i:s')]);$adverse=(int)$db->insert_id();
essentialThrows(fn()=>$asaas->observeInstallment($b,7,$next,'pay_retained_1',$dec,$paidTransport),'asaas_installment_retained_financial_review','new adverse event on retained predecessor blocks successor');$db->query('DELETE FROM '.$p.'asaas_company_events WHERE id=?',[$adverse]);
$changes=['pay_retained_2'=>['value'=>'32.77','due_date'=>'2027-03-05']];$edit=$asaas->reviewInstallmentEdit($b,7,$next,$changes,$dec,$paidTransport);essentialCheck($asaas->executeInstallmentEdit($b,7,$next,$changes,$edit['key'],true,$dec,$paidTransport)['complete'],'unpaid successor can be edited alongside retained paid predecessor');
$new[0]['status']='RECEIVED';$new[0]['paymentDate']=$originalResponse['paymentDate'];$new[0]['creditDate']=$originalResponse['creditDate'];$proof=$asaas->observeInstallment($b,7,$next,'pay_retained_1',$dec,$paidTransport);$writer->recordCompanyProviderReceipt($b,7,new \Asaas\CompanyInstallmentReceipt($next,'pay_retained_1',$proof));essentialCheck($s->position($b,7,$invoice)['received']==='72.76','receipt adds only new member principal to retained amount');
$asaas->removeInstallment($b,7,$next,'pay_retained_2',hash('sha256','delete-current-retained-remainder'),$dec,$paidTransport);essentialCheck($new[1]['deleted']===true,'remaining member deletion accepts proved paid predecessor');
require_once APP_MODULES_PATH.'asaas/libraries/Asaas/CompanyRefunds.php';\Asaas\CompanyRefundSchema::ensure($db,$p,true);$refunds=new \Asaas\CompanyRefunds($db,$p);$refunds->setGrant($b,7,$accounts[$b],7,str_repeat('0',64),true);$origin=(int)$db->query('SELECT id FROM '.$p.'asaas_payment_origins WHERE invoice_id=? AND remote_id=?',[$invoice,'pay_group_one'])->row()->id;
$refund=$refunds->prepare($b,7,$origin,'40.00','Synthetic retained refund',hash('sha256','retained-refund'),$dec,$paidTransport);essentialCheck($refund['source']['nominal']==='40.00'&&$refund['source']['payment_id']===$payment,'retained historical member remains eligible for explicit refund review');
echo 'Paid installment succession: '.($GLOBALS['essentialChecks']-$start).' additional MySQL checks; provider simulated.'.PHP_EOL;
