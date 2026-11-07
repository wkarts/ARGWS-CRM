<?php
/** Native delete semantics retain financial history; provider transport is simulated. */
require __DIR__.'/asaas_invoice_cancellation.php';
$db->queries=[];$deleteStart=$GLOBALS['essentialChecks'];
foreach(['success','uncertain','revoked'] as $name){
 [$id,$origin,$remote]=asaasUnpaid($b,$customerB,'cus_b','native-delete-'.$name);
 $revision=$invoiceService->revision($b,7,$id);$before=$deletes;$deleteMode=$name==='uncertain'?'timeout':'normal';
 if($name==='revoked'){
  $db->query("UPDATE {$p}staff SET active=0 WHERE staffid=7");
  try{essentialThrows(fn()=>$cancel->delete($b,7,$id,$revision,$dec,$removeTransport),'company_invoice_access_denied','delete authority revoked before remote request');}finally{$db->query("UPDATE {$p}staff SET active=1 WHERE staffid=7");}
  essentialCheck($deletes===$before,'denied deletion performs no provider mutation');continue;
 }
 if($name==='uncertain'){
  essentialThrows(fn()=>$cancel->delete($b,7,$id,$revision,$dec,$removeTransport),'asaas_invoice_cancellation_pending','native deletion cannot claim success on uncertain result');
  essentialCheck((int)$db->query("SELECT status FROM {$p}invoices WHERE id=?",[$id])->row()->status!==5,'unconfirmed deletion leaves invoice active');$responses[$remote]['deleted']=true;
 }
 $cancel->delete($b,7,$id,$revision,$dec,$removeTransport);$cancel->delete($b,7,$id,$revision,$dec,$removeTransport);
 essentialCheck($deletes===$before+1,'native delete and replay dispatch one provider DELETE');
 essentialCheck((int)$db->query("SELECT status FROM {$p}invoices WHERE id=?",[$id])->row()->status===5,'native deletion reports retained cancellation semantics');
 essentialCheck((int)$db->query("SELECT COUNT(*) n FROM {$p}asaas_payment_origins WHERE id=? AND invoice_id=?",[$origin,$id])->row()->n===1,'deletion preserves immutable origin and invoice relationship');
}
echo 'Native Asaas deletion: '.($GLOBALS['essentialChecks']-$deleteStart).' additional SQL checks passed; transport simulated.'.PHP_EOL;
