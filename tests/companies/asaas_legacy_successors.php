<?php
require __DIR__.'/asaas_legacy_lifecycle.php';
require_once APPPATH.'services/CompanyAsaasInvoiceCancellation.php';
require_once APPPATH.'services/CompanyAsaasRecreationSource.php';
\Asaas\CompanyOriginGenerations::ensure($db,$p);\Asaas\CompanyRecreationSchema::ensure($db,$p);
$db->query('UPDATE '.$p.'modules SET installed_version=? WHERE module_name=?',['1.7.9','asaas']);
$start=$GLOBALS['essentialChecks'];
$invoice=$s->create($b,7,array_replace($input,['client_id'=>$customerB]),hash('sha256','legacy-successor-full'));
$legacyRemote='pay_legacy_successor_root';$legacyResponse=array_replace($originalResponse,['id'=>$legacyRemote,'externalReference'=>$db->query('SELECT hash FROM '.$p.'invoices WHERE id=?',[$invoice])->row()->hash,'value'=>'105.53','netValue'=>'104.53','status'=>'PENDING','paymentDate'=>'','creditDate'=>'']);
$review=$asaas->reviewLegacyBinding($b,7,$invoice,$accounts[$b],$legacyRemote,$dec,$legacyTransport);
$root=$asaas->reviewLegacyBinding($b,7,$invoice,$accounts[$b],$legacyRemote,$dec,$legacyTransport,$review['review_hash'])['origin_id'];
$chainResponses=[$legacyRemote=>$legacyResponse];$postCount=0;$deleteCount=0;$putCount=0;$losePut=true;$newRemote=null;$losePost=true;
$chainTransport=function($env,$method,$path,$payload,$secret)use(&$chainResponses,&$postCount,&$deleteCount,&$newRemote,&$losePost,&$putCount,&$losePut,$mock){
 if($method==='POST'&&$path==='payments'){$postCount++;$newRemote='pay_legacy_successor_'.$postCount;$chainResponses[$newRemote]=$payload+['id'=>$newRemote,'status'=>'PENDING','netValue'=>$payload['value'],'paymentDate'=>'','creditDate'=>'','deleted'=>false];if($losePost)throw new RuntimeException('synthetic accepted POST, lost response');return $chainResponses[$newRemote];}
 if(str_starts_with($path,'payments/')){$id=substr($path,9);if($method==='PUT'){$putCount++;$chainResponses[$id]=array_replace($chainResponses[$id],$payload);$chainResponses[$id]['netValue']=$payload['value'];if($losePut)throw new RuntimeException('synthetic accepted PUT, lost response');}if($method==='DELETE'){$deleteCount++;$chainResponses[$id]['deleted']=true;}return $chainResponses[$id]??[];}
 return $mock($env,$method,$path,$payload,$secret);
};
$cancel=new CompanyAsaasInvoiceCancellation($db,$p);$intents=new \Asaas\CompanyRecreationIntents($db,$p);$successors=new \Asaas\CompanyRecreationSuccessors($db,$p);$current=$root;
for($generation=1;$generation<=2;$generation++){
 $cancel->cancel($b,7,$invoice,$s->revision($b,7,$invoice),$dec,$chainTransport);
 $revision=$s->revision($b,7,$invoice);$key=hash('sha256','legacy-successor-'.$generation);
 $intents->reserve($b,7,$invoice,$current,$revision,$key);
 $old=$db->query('SELECT * FROM '.$p.'asaas_payment_origins WHERE id=?',[$current])->row_array();
 if($generation===1){$db->failOn='INSERT INTO '.$p.\Asaas\CompanyOriginPolicy::TABLE;essentialThrows(fn()=>$successors->prepare($b,7,$invoice,$current,$revision,$key),'issuer_storage_failed','failed inherited manual policy rolls successor back');$db->failOn=null;essentialCheck($db->query('SELECT * FROM '.$p.'asaas_payment_origins WHERE id=?',[$current])->row_array()===$old,'failed policy leaves predecessor active and unchanged');}
 if($generation===1){$db->query('UPDATE '.$p.'modules SET installed_version=? WHERE module_name=?',['1.7.8','asaas']);essentialThrows(fn()=>$successors->prepare($b,7,$invoice,$current,$revision,$key),'asaas_module_179_required','legacy recreation requires installed tracking');$db->query('UPDATE '.$p.'modules SET installed_version=? WHERE module_name=?',['1.7.9','asaas']);}
 $next=$successors->prepare($b,7,$invoice,$current,$revision,$key);
 essentialCheck($next!==$current&&\Asaas\CompanyOriginPolicy::isManual($db,$p,$next),'each successor preserves manual-only receipt authority');
 essentialCheck($successors->prepare($b,7,$invoice,$current,$revision,$key)===$next,'successor preparation replay preserves same identity');
 if($generation===1){essentialThrows(fn()=>$asaas->dispatchRecreation($b,7,$current,$key,$dec,$chainTransport),'asaas_recreation_post_unconfirmed','lost legacy recreation response remains uncertain');essentialThrows(fn()=>$asaas->dispatchRecreation($b,7,$current,$key,$dec,$chainTransport),'asaas_recreation_recovery_required','uncertain legacy retry never repeats POST');$losePost=false;}
 $asaas->dispatchRecreation($b,7,$current,$key,$dec,$chainTransport,$generation===1?($newRemote??''):'');
 $source=new CompanyAsaasRecreationSource($db,$p,$b,7,$current,$key);$s->reopen($b,7,$invoice,$revision,$source);
 essentialCheck($postCount===$generation&&$deleteCount===$generation,'one DELETE and one POST per explicit generation');
 essentialCheck((int)$db->query('SELECT status FROM '.$p.'invoices WHERE id=?',[$invoice])->row()->status!==5,'native reopening follows confirmed successor only');
 if($generation===1){
  $revision=$s->revision($b,7,$invoice);$payload=array_replace($input,['client_id'=>$customerB,'due_date'=>'2026-12-20']);$payload['items'][0]['rate']='180.00';
  $lines=$db->query("SELECT id FROM {$p}itemable WHERE rel_id=? AND rel_type='invoice' ORDER BY item_order,id",[$invoice])->result_array();
  $metadata=['invoice_fields'=>[],'line_fields'=>array_fill(0,count($lines),[]),'tags'=>[],'tags_revision'=>(new NativeCompanyResourceTags($db,$p))->read($b,7,'invoice',$invoice)['revision'],'line_sources'=>array_map('intval',array_column($lines,'id')),'removed_line_ids'=>[]];
  $editKey=hash('sha256','legacy-successor-edit');
  essentialThrows(fn()=>$edits->update($b,7,$invoice,$revision,$payload,$metadata,$editKey,$dec,$chainTransport),'asaas_module_180_required','successor edit requires tracking180');
  $db->query('UPDATE '.$p.'modules SET installed_version=? WHERE module_name=?',['1.8.0','asaas']);
  essentialThrows(fn()=>$edits->update($b,7,$invoice,$revision,$payload,$metadata,$editKey,$dec,$chainTransport),'asaas_invoice_edit_remote_unconfirmed','successor PUT uncertainty preserves native invoice');
  $losePut=false;$edits->recover($b,7,$editKey,$dec,$chainTransport);
  essentialCheck($putCount===1&&\Asaas\CompanyOriginPolicy::isManual($db,$p,$next),'edited successor remains manual and recovery never resends PUT');
  essentialCheck($db->query('SELECT total FROM '.$p.'invoices WHERE id=?',[$invoice])->row()->total===$chainResponses[$newRemote]['value'],'successor edit applies audited remote amount to native invoice');
 }
 $old['active_slot']=null;essentialCheck($db->query('SELECT * FROM '.$p.'asaas_payment_origins WHERE id=?',[$current])->row_array()===$old,'retirement preserves all predecessor evidence');$current=$next;
}
$chainResponses[$newRemote]['status']='RECEIVED';$chainResponses[$newRemote]['paymentDate']=$originalResponse['paymentDate'];$chainResponses[$newRemote]['creditDate']=$originalResponse['creditDate'];
$proof=$asaas->observe($b,7,$current,$dec,$chainTransport);
essentialThrows(fn()=>$writer->recordCompanyProviderReceipt($b,7,new \Asaas\CompanyReceivedReceipt($current,$proof)),'asaas_origin_manual_reconciliation_required','recreation never grants automatic settlement');
$db->query("UPDATE {$p}asaas_company_recreations SET state='uncertain' WHERE origin_id=?",[$root]);
essentialThrows(fn()=>$batch->item($b,7,$current,true,$dec,$chainTransport),'company_post_refund_adverse_history','unconfirmed historical link cannot authorize explicit successor receipt');
$db->query("UPDATE {$p}asaas_company_recreations SET state='reopened' WHERE origin_id=?",[$root]);
$paid=$batch->item($b,7,$current,true,$dec,$chainTransport);
essentialCheck($paid['payment_id']>0&&$s->position($b,7,$invoice)['receivable']==='0.00','explicit reviewed successor settles canonical full principal');
essentialCheck($batch->item($b,7,$current,true,$dec,$chainTransport)['payment_id']===$paid['payment_id'],'explicit successor retry preserves one native receipt');
// A reviewed partial charge must not silently become a full-invoice successor.
$partialInvoice=$s->create($b,7,array_replace($input,['client_id'=>$customerB]),hash('sha256','legacy-successor-partial'));
$legacyRemote='pay_legacy_successor_partial';$legacyResponse=array_replace($originalResponse,['id'=>$legacyRemote,'externalReference'=>$db->query('SELECT hash FROM '.$p.'invoices WHERE id=?',[$partialInvoice])->row()->hash,'status'=>'PENDING','paymentDate'=>'','creditDate'=>'']);
$review=$asaas->reviewLegacyBinding($b,7,$partialInvoice,$accounts[$b],$legacyRemote,$dec,$legacyTransport);
$partialOrigin=$asaas->reviewLegacyBinding($b,7,$partialInvoice,$accounts[$b],$legacyRemote,$dec,$legacyTransport,$review['review_hash'])['origin_id'];
$chainResponses[$legacyRemote]=$legacyResponse;
$cancel->cancel($b,7,$partialInvoice,$s->revision($b,7,$partialInvoice),$dec,$chainTransport);
$revision=$s->revision($b,7,$partialInvoice);$key=hash('sha256','partial-recreation-denied');
$intents->reserve($b,7,$partialInvoice,$partialOrigin,$revision,$key);
$before=$db->query('SELECT * FROM '.$p.'asaas_payment_origins WHERE id=?',[$partialOrigin])->row_array();$postsBefore=$postCount;
essentialThrows(fn()=>$successors->prepare($b,7,$partialInvoice,$partialOrigin,$revision,$key),'asaas_legacy_recreation_allocation_required','partial allocation cannot be recreated as entire invoice');
essentialCheck($db->query('SELECT * FROM '.$p.'asaas_payment_origins WHERE id=?',[$partialOrigin])->row_array()===$before&&$postCount===$postsBefore,'partial denial preserves origin and sends no POST');
echo 'Reviewed legacy successors: '.($GLOBALS['essentialChecks']-$start).' additional MySQL checks; provider simulated.'.PHP_EOL;
