<?php
/** Build one fresh synthetic closed chain through the existing real services and fake transport. */
function cycleFreshReady(string$tag,bool$reverse=true):array
{
    global$cycle,$manualService,$a,$pmRecord,$mk,$rfMock,$dec;
    $f=pmFresh('manual-cycle-'.$tag);$payment=$pmRecord($f);if(!$reverse)return array_replace($f,['manual'=>$payment,'page'=>$cycle->review($a,7,$f['invoice'],$dec,$rfMock)]);
    $m=$manualService->page($a,7,$f['invoice'],true);$manualService->reverse($a,7,$f['invoice'],$m['manual_request_key'],$m['revision'],$mk('cycle-reverse-'.$f['invoice']),['amount'=>'105.53','date'=>gmdate('Y-m-d'),'reference'=>'Independent new-manual LOCAL correction cycle '.$f['invoice'],'reason'=>'Independent full local correction before explicit invoice closure'],$mk('cycle-reverse-evidence-'.$f['invoice']),true);
    return array_replace($f,['manual'=>$payment,'page'=>$cycle->review($a,7,$f['invoice'],$dec,$rfMock)]);
}
