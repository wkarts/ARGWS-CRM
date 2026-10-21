<?php
/** Test-only exact wire reconstruction from an already synthetic normalized final response. */
function financeCycleSyntheticResponse(array $source,array $safe):array
{
    if(($source['environment']??null)!=='sandbox'||($source['billing_type']??null)!=='PIX'||($source['nominal']??null)!=='100.00'||($safe['status']??null)!=='REFUNDED'||($safe['done_total']??null)!=='100.00'||($safe['pending_total']??null)!=='0.00'||($safe['unsupported']??null)!==[]||($safe['deleted']??null)!==false||($safe['not_deleted_confirmed']??null)!==true)throw new RuntimeException('Only the explicit synthetic full-return browser fixture is accepted');
    $refunds=[];foreach($safe['refunds'] as $refund){if($refund['has_splits'])throw new RuntimeException('Synthetic split fixture unsupported');$refunds[]=['dateCreated'=>$refund['date_created'],'status'=>$refund['status'],'value'=>$refund['value'],'description'=>$refund['description'],'endToEndIdentifier'=>$refund['end_to_end_identifier'],'transactionReceiptUrl'=>$refund['receipt_url'],'refundedSplits'=>[]];}
    $raw=['id'=>$source['remote_id'],'customer'=>$source['customer_id'],'externalReference'=>$source['invoice_hash'],'dueDate'=>$source['due_date'],'billingType'=>$source['billing_type'],'value'=>$source['nominal'],'deleted'=>false,'status'=>'REFUNDED','refunds'=>$refunds];
    if(\Asaas\CompanyRefundContract::normalize($raw,$source)!==$safe)throw new RuntimeException('Synthetic replay must preserve every normalized protocol field');
    return $raw;
}
