<?php

declare(strict_types=1);
namespace Asaas;
require_once APPPATH.'services/CompanyInvoiceAmounts.php';require_once __DIR__.'/CompanyCardSettlement.php';

/** Refund observations are not individual refund identities or a financial posting authority. */
final class CompanyRefundContract
{
    public static function money($value):string
    {
        if(is_float($value)||!is_int($value)&&!is_string($value))throw new \DomainException('asaas_refund_amount_requires_review');
        try{return \CompanyInvoiceAmounts::decimal(\CompanyInvoiceAmounts::minor((string)$value));}
        catch(\Throwable $e){throw new \DomainException('asaas_refund_amount_requires_review');}
    }
    public static function encode(array $value):string{return json_encode($value,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);}
    public static function hash(array $value):string{return hash('sha256',self::encode($value));}
    private static function text($value,int $max,bool $nullable=false):?string
    {
        if($nullable&&$value===null)return null;
        if(!is_string($value)||strlen($value)>$max||preg_match('//u',$value)!==1||preg_match('/[\x00-\x1f\x7f]/',$value))throw new \DomainException('asaas_refund_response_conflict');
        return $value;
    }
    private static function receipt($value):?string
    {
        $value=self::text($value,2048,true);if($value===null||$value==='')return null;$u=parse_url($value);
        if(!$u||($u['scheme']??'')!=='https'||!in_array($u['host']??'',['www.asaas.com','asaas.com','sandbox.asaas.com'],true)||isset($u['user'])||isset($u['pass'])||isset($u['port']))return null;
        return $value;
    }
    public static function normalize(array $response,array $source):array
    {
        foreach(['id'=>'remote_id','customer'=>'customer_id','externalReference'=>'invoice_hash','dueDate'=>'due_date','billingType'=>'billing_type'] as $remote=>$field)if(($response[$remote]??null)!==$source[$field])throw new \DomainException('asaas_refund_response_conflict');
        if(self::money($response['value']??null)!==$source['nominal']||isset($response['deleted'])&&!is_bool($response['deleted']))throw new \DomainException('asaas_refund_response_conflict');
        $status=self::text($response['status']??null,32);if(!preg_match('/^[A-Z_]+$/D',$status))throw new \DomainException('asaas_refund_response_conflict');
        $unsupported=[];foreach(['chargeback','split','installment','subscription','anticipated','escrow','custody'] as $field)if(!empty($response[$field]))$unsupported[]=$field;
        foreach(['fine','interest','discount'] as $field)if(isset($response[$field]['value'])&&self::money($response[$field]['value'])!=='0.00')$unsupported[]=$field;
        foreach(['interestValue','originalValue'] as $field)if(isset($response[$field])&&self::money($response[$field])!==($field==='originalValue'?$source['nominal']:'0.00'))$unsupported[]=$field;
        if($source['billing_type']==='CREDIT_CARD'&&CompanyCardSettlement::observe($response)!==['profile'=>'hosted_single_v1','single_charge'=>true,'unanticipated'=>true,'not_deleted'=>true,'unencumbered'=>true])$unsupported[]='card_profile_contract_required';
        $refunds=$response['refunds']??[];if(!is_array($refunds)||!array_is_list($refunds)||count($refunds)>100)throw new \DomainException('asaas_refund_response_conflict');
        $items=[];$done=0;$pending=0;$cancelled=0;
        foreach($refunds as $row){
            if(!is_array($row)||array_is_list($row)||!is_string($row['status']??null)||!preg_match('/^[A-Z_]{1,64}$/D',$row['status']))throw new \DomainException('asaas_refund_response_conflict');
            if(!in_array($row['status'],['PENDING','CANCELLED','DONE'],true))$unsupported[]='refund_status_contract_required';$amount=self::money($row['value']??null);$minor=\CompanyInvoiceAmounts::minor($amount);if($minor<1)throw new \DomainException('asaas_refund_amount_requires_review');
            $date=self::text($row['dateCreated']??null,19);$parsed=\DateTimeImmutable::createFromFormat('!Y-m-d H:i:s',$date);if(!$parsed||$parsed->format('Y-m-d H:i:s')!==$date)throw new \DomainException('asaas_refund_response_conflict');
            $splits=$row['refundedSplits']??[];if(!is_array($splits)||!array_is_list($splits))throw new \DomainException('asaas_refund_response_conflict');if($splits)$unsupported[]='refundedSplits';
            $item=['date_created'=>$date,'status'=>$row['status'],'value'=>$amount,'description'=>self::text($row['description']??null,1000,true),'end_to_end_identifier'=>self::text($row['endToEndIdentifier']??null,128,true),'receipt_url'=>self::receipt($row['transactionReceiptUrl']??null),'has_splits'=>(bool)$splits];
            $items[]=$item;
            if($row['status']==='DONE')$done+=$minor;elseif($row['status']==='CANCELLED')$cancelled+=$minor;else $pending+=$minor;
            if(max($done,$pending,$cancelled)>\CompanyInvoiceAmounts::MAX)throw new \DomainException('asaas_refund_amount_requires_review');
        }
        if($done+$pending>\CompanyInvoiceAmounts::minor($source['nominal']))throw new \DomainException('asaas_refund_amount_requires_review');
        // Array order and optional E2E are deliberately not converted into IDs of partial refunds.
        return ['payment_id'=>$source['remote_id'],'status'=>$status,'billing_type'=>$source['billing_type'],'nominal'=>$source['nominal'],'deleted'=>($response['deleted']??false)!==false,'not_deleted_confirmed'=>array_key_exists('deleted',$response)&&$response['deleted']===false,'unsupported'=>array_values(array_unique($unsupported)),'refunds'=>$items,'done_total'=>\CompanyInvoiceAmounts::decimal($done),'pending_total'=>\CompanyInvoiceAmounts::decimal($pending),'cancelled_total'=>\CompanyInvoiceAmounts::decimal($cancelled),'identity_basis'=>'payment_aggregate_only','individual_refund_identity_confirmed'=>false];
    }
    public static function state(array $safe):array
    {
        if($safe['deleted']||!$safe['not_deleted_confirmed']||$safe['unsupported'])return ['review_required','unsupported_payment_profile'];
        if($safe['pending_total']!=='0.00')return ['pending_observed','remote_refund_processing'];
        if($safe['done_total']===$safe['nominal']&&$safe['status']==='REFUNDED')return ['full_observed','full_aggregate_observed_not_posted'];
        if($safe['done_total']!=='0.00')return ['partial_observed','partial_identity_contract_required'];
        if($safe['cancelled_total']!=='0.00')return ['cancelled_observed','remote_cancellation_observed'];
        return ['uncertain','refund_not_yet_observed'];
    }
}
