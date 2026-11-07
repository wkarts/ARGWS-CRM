<?php
require __DIR__.'/asaas_installment_retained_refund.php';
$start=$GLOBALS['essentialChecks'];$db->query('UPDATE '.$p.'modules SET installed_version=? WHERE module_name=?',['2.0.7','asaas']);
$reopened=[];$reopenedGroup=[];$reopenedPosts=0;$allocationDuringPost=null;
$allocationTransport=function($env,$method,$path,$payload,$secret)use(&$reopened,&$reopenedGroup,&$reopenedPosts,&$allocationDuringPost,&$new,$refundTransport){
 if($method==='POST'&&$path==='payments'){$reopenedPosts++;essentialCheck($payload['totalValue']==='72.77','only exact reopened plus unallocated remainder is submitted');foreach([1,2] as $n)$reopened[$n-1]=array_replace($new[1],['id'=>'pay_reopened_'.$n,'installment'=>'grp_reopened','installmentNumber'=>$n,'value'=>$n===1?'36.38':'36.39','netValue'=>$n===1?'35.38':'35.39','status'=>'PENDING','deleted'=>false,'paymentDate'=>null,'creditDate'=>null,'refunds'=>[],'dueDate'=>$n===1?$payload['dueDate']:'2027-05-05','externalReference'=>$payload['externalReference']]);$reopenedGroup=['id'=>'grp_reopened','customer'=>'cus_b','billingType'=>'PIX','installmentCount'=>2,'value'=>'72.77','deleted'=>false];if($allocationDuringPost){$f=$allocationDuringPost;$allocationDuringPost=null;$f();}throw new RuntimeException('synthetic response lost');}
 if(str_starts_with($path,'payments?externalReference='))return ['data'=>$reopened,'hasMore'=>false,'totalCount'=>count($reopened)];
 if($path==='installments/grp_reopened')return $reopenedGroup;
 if($path==='installments/grp_reopened/payments?limit=100&offset=0')return ['data'=>$reopened,'hasMore'=>false,'totalCount'=>count($reopened)];
 foreach($reopened as $n=>$row)if($path==='payments/'.$row['id']){if($method==='DELETE')$reopened[$n]['deleted']=true;return $reopened[$n];}
 return $refundTransport($env,$method,$path,$payload,$secret);
};
$review=$asaas->reviewInstallmentRecreation($b,7,$next,2,'2027-04-05',$dec,$allocationTransport);
essentialCheck($review['amount']==='72.77'&&$review['retained']==='32.76'&&count($review['reopened'])===1&&$review['reopened'][0]['value']==='40.00','review separates retained paid principal and historical refund allocation');
essentialThrows(fn()=>$asaas->reviewInstallmentRecreation($a,7,$next,2,'2027-04-05',$dec,$allocationTransport),'asaas_installment_origin_denied','foreign issuer cannot allocate refund');
$receiptsBefore=$db->query('SELECT * FROM '.$p.'invoicepaymentrecords WHERE invoiceid=? ORDER BY id',[$invoice])->result_array();$reversalsBefore=$db->query('SELECT * FROM '.$p.'company_asaas_refund_postings WHERE invoice_id=? ORDER BY payment_id',[$invoice])->result_array();
$db->failOn='INSERT INTO '.$p.'issuers_audit';essentialThrows(fn()=>$asaas->recreateInstallmentGroup($b,7,$next,2,'2027-04-05',$review['key'],true,$dec,$allocationTransport),'issuer_storage_failed','failed allocation audit cannot dispatch');$db->failOn=null;essentialCheck($reopenedPosts===0,'allocation audit failure sends nothing');
$allocationDuringPost=function()use($db,$p,$b,$next,$review,&$payments,&$new){
 $observer=new EssentialTestDb(new PDO(getenv('ISSUERS_TEST_DSN'),getenv('ISSUERS_TEST_USER'),getenv('ISSUERS_TEST_PASSWORD')),$p);$blocker=(int)$db->query('SELECT CONNECTION_ID() id')->row()->id;$workers=[];$db->trans_begin();$db->query('SELECT id FROM '.$p.'issuers_lock WHERE id=1 FOR UPDATE');$remote=$payments;foreach($new as $member)$remote[$member['id']]=$member;
 try{for($n=0;$n<2;$n++){$pipes=[];$proc=proc_open([PHP_BINARY,__DIR__.'/asaas_installment_recreation.php','recreate-worker',$p],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);fwrite($pipes[0],json_encode(['issuer'=>$b,'group'=>$next,'key'=>$review['key'],'payments'=>$remote]));fclose($pipes[0]);$workers[]=[$proc,$pipes];}
  $until=microtime(true)+15;do{$waiters=essentialLockWaiters($observer,$blocker);if($waiters>=2)break;usleep(20000);}while(microtime(true)<$until);essentialCheck($waiters>=2,'two allocation recoverers contend under actual InnoDB lock');$db->trans_commit();
  foreach($workers as [$proc,$pipes]){$out=stream_get_contents($pipes[1]);$error=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);essentialCheck(proc_close($proc)===0&&$error==='','concurrent allocation recovery finishes without diagnostics');essentialCheck(json_decode($out,true,32,JSON_THROW_ON_ERROR)['complete']===false,'remote absence while creation is in flight remains uncertain');}
  essentialCheck((int)$db->query('SELECT COUNT(*) n FROM '.$p.'recreate_calls')->row()->n===0,'recoverers cannot send another financial allocation');
 }finally{if($db->pdo->inTransaction())$db->trans_rollback();}
};
$result=$asaas->recreateInstallmentGroup($b,7,$next,2,'2027-04-05',$review['key'],true,$dec,$allocationTransport);$third=$result['origin_id'];essentialCheck($result['complete']&&$reopenedPosts===1,'reopened allocation recovers lost POST result');
essentialCheck($asaas->recoverInstallmentRecreation($b,7,$next,$review['key'],$dec,$allocationTransport)['origin_id']===$third&&$reopenedPosts===1,'allocation retry binds same successor without another POST');
essentialCheck($receiptsBefore===$db->query('SELECT * FROM '.$p.'invoicepaymentrecords WHERE invoiceid=? ORDER BY id',[$invoice])->result_array()&&$reversalsBefore===$db->query('SELECT * FROM '.$p.'company_asaas_refund_postings WHERE invoice_id=? ORDER BY payment_id',[$invoice])->result_array(),'allocation never rewrites receipts or reversal postings');
foreach($reopened as $row)$asaas->removeInstallment($b,7,$third,$row['id'],hash('sha256','remove-'.$row['id']),$dec,$allocationTransport);
$again=$asaas->reviewInstallmentRecreation($b,7,$third,2,'2027-06-05',$dec,$allocationTransport);essentialCheck($again['amount']==='72.77'&&$again['reopened']===[],'historical refund cannot be allocated twice across later generations');
essentialCheck($s->position($b,7,$invoice)['receivable']==='72.77','allocation preserves native receivable');
echo 'Reopened historical allocation: '.($GLOBALS['essentialChecks']-$start).' additional MySQL checks; provider simulated.'.PHP_EOL;
