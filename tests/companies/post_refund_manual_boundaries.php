<?php
/** Recreated recovery scaffold for440 packets; original439 fixture bytes were unavailable.
 * All parent financial assertions execute; additional origins use actual public services only.
 */
register_shutdown_function(static function():void{if(isset($GLOBALS['db'],$GLOBALS['p'])){$GLOBALS['db']->query('DROP TABLE IF EXISTS '.$GLOBALS['p'].'company_invoice_copy_requests');unset($GLOBALS['db']->data_cache['table_names']);}});
require __DIR__.'/finance_resume_439_asaas.php';
$rfB=$resumeFull[$b]['fresh'];$mnew=$received;$mk=fn(string $tag)=>hash('sha256','resume-post-manual-'.$tag);$fullInput=$declaration;
function pmFresh(string $tag):array
{
 global $a,$ca,$rfFresh,$refunds,$rfKey,$dec,$rfMock,$refundReplies,$responses,$writer,$fullInput;
 $f=$rfFresh($a,$ca,'cus_a','resume440-'.$tag);$r=$refunds->prepare($a,7,$f['origin'],'105.53','Independent full return for local lifecycle',$rfKey('resume440-'.$tag),$dec,$rfMock);$refunds->dispatch($a,7,$r['request_key'],$dec,$rfMock);$refundReplies[$f['remote']][0]['status']='DONE';$responses[$f['remote']]['refunds']=$refundReplies[$f['remote']];$responses[$f['remote']]['status']='REFUNDED';$obs=$refunds->refresh($a,7,$r['request_key'],$dec,$rfMock);
 $writer->recordCompanyAsaasFullRefund($a,7,new \Asaas\ConfirmedFullRefund($GLOBALS['db'],$GLOBALS['p'],$r['request_key'],$obs['observation_id'],array_replace($fullInput,['reference'=>'Independent external return '.$tag,'independent_evidence_hash'=>hash('sha256','independent full return '.$tag)])));
 return $f+['request'=>$r['request_key'],'refund_request'=>$r['request_key'],'observation'=>$obs['observation_id']];
}
$pmRecord=static function(array $f)use($manualService,$a,$dec,$rfMock,$mk):int{
 $page=$manualService->review($a,7,$f['invoice'],$dec,$rfMock);
 return $manualService->record($a,7,$f['invoice'],$page['refund_request'],$page['observation_id'],$page['revision'],$mk('receipt-'.$f['invoice']),['amount'=>'105.53','date'=>gmdate('Y-m-d'),'reference'=>'Independent NEW manual funds '.$f['invoice'],'note'=>'Synthetic independent new funds'],$mk('independent-new-manual-'.$f['invoice']),'Independent newly received manual funds after full return',true);
};
