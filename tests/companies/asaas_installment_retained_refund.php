<?php
/** Retained historical principal: explicit refund, native reversal and unallocated balance guard. */
require __DIR__.'/asaas_installment_paid_succession.php';
$start=$GLOBALS['essentialChecks'];
require_once APP_MODULES_PATH.'asaas/libraries/Asaas/ConfirmedFullRefund.php';
CompanyAsaasRefundSchema::ensure($db,$p,true);$refundKey=hash('sha256','retained-refund');$refundPosts=0;
$refundTransport=function($env,$method,$path,$payload,$secret)use(&$payments,&$refundPosts,$paidTransport){
 if($method==='POST'&&$path==='payments/pay_group_one/refund'){$refundPosts++;$payments['pay_group_one']['status']='REFUND_IN_PROGRESS';$payments['pay_group_one']['refunds']=[['dateCreated'=>'2026-11-01 12:00:00','status'=>'PENDING','value'=>$payload['value'],'description'=>$payload['description'],'refundedSplits'=>[]]];return $payments['pay_group_one'];}
 return $paidTransport($env,$method,$path,$payload,$secret);
};
$refunds->dispatch($b,7,$refundKey,$dec,$refundTransport);$refunds->dispatch($b,7,$refundKey,$dec,$refundTransport);
essentialCheck($refundPosts===1,'retained historical refund sends once');
$payments['pay_group_one']['status']='REFUNDED';$payments['pay_group_one']['refunds'][0]['status']='DONE';
$observed=$refunds->refresh($b,7,$refundKey,$dec,$refundTransport);
$declaration=['external_refund_confirmed'=>'1','full_cash_return_confirmed'=>'1','returned_amount'=>'40.00','date'=>gmdate('Y-m-d'),'reference'=>'synthetic-retained-return','reason'=>'Independent synthetic proof','independent_evidence_hash'=>hash('sha256','synthetic retained evidence')];
$source=new \Asaas\ConfirmedFullRefund($db,$p,$refundKey,$observed['observation_id'],$declaration);
$before=$db->query('SELECT * FROM '.$p.'invoicepaymentrecords WHERE invoiceid=? ORDER BY id',[$invoice])->result_array();
$db->failOn='INSERT INTO '.$p.'issuers_audit';essentialThrows(fn()=>$writer->recordCompanyAsaasFullRefund($b,7,$source),'issuer_storage_failed','retained refund audit failure rolls back');$db->failOn=null;
$posting=$writer->recordCompanyAsaasFullRefund($b,7,$source);essentialCheck($writer->recordCompanyAsaasFullRefund($b,7,$source)===$posting,'historical refund posting replay is idempotent');
$position=$s->position($b,7,$invoice);essentialCheck($position['received_original']==='72.76'&&$position['received']==='32.76'&&$position['receivable']==='72.77','retained refund reopens only its principal without affecting successor receipt');
essentialCheck($before===$db->query('SELECT * FROM '.$p.'invoicepaymentrecords WHERE invoiceid=? ORDER BY id',[$invoice])->result_array(),'historical and current receipts remain byte-identical');
$n=$posts;essentialThrows(fn()=>$asaas->reviewInstallmentRecreation($b,7,$next,2,'2027-04-05',$dec,$refundTransport),'asaas_installment_recreation_allocation_review','reopened historical principal requires explicit allocation before another succession');essentialCheck($posts===$n,'unallocated reopened balance cannot create remote obligation');
echo 'Retained historical refund: '.($GLOBALS['essentialChecks']-$start).' additional MySQL checks; provider simulated.'.PHP_EOL;
