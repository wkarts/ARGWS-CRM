<?php

declare(strict_types=1);
namespace Asaas;

/** Canonical single hosted-card settlement facts, never cardholder data or webhook money. */
final class CompanyCardSettlement
{
    public static function observe(array $response):array
    {
        // The provider documents installment/subscription as optional for their respective products.
        // A number, false, empty string or an unexpected installment field is not proof of absence.
        $single=true;
        foreach(['installment','subscription','installmentNumber','installmentCount','installmentValue','totalValue'] as $field){
            if(array_key_exists($field,$response)&&$response[$field]!==null)$single=false;
        }
        return ['profile'=>'hosted_single_v1','single_charge'=>$single,
            'unanticipated'=>array_key_exists('anticipated',$response)&&$response['anticipated']===false,
            'not_deleted'=>array_key_exists('deleted',$response)&&$response['deleted']===false,
            'unencumbered'=>(!array_key_exists('escrow',$response)||$response['escrow']===null)&&(!array_key_exists('custody',$response)||$response['custody']===null)];
    }
    public static function receiptDate(array $safe,array $meta,string $invoiceDate,string $today):string
    {
        if(($meta['billing_type']??null)!=='CREDIT_CARD'||($safe['billing_type']??null)!=='CREDIT_CARD'
            ||($safe['status']??null)!=='RECEIVED'||($safe['card_settlement']??null)!==[
                'profile'=>'hosted_single_v1','single_charge'=>true,'unanticipated'=>true,'not_deleted'=>true,'unencumbered'=>true])
            throw new \DomainException('asaas_company_card_settlement_requires_review');
        $paid=self::date($safe['payment_date']??null);$credited=self::date($safe['credit_date']??null);
        // Neither confirmation nor estimatedCreditDate proves that funds became available.
        if($credited>$today||$paid>$today||$paid<$invoiceDate||$credited<$paid||$credited<$invoiceDate)
            throw new \DomainException('asaas_company_card_settlement_requires_review');
        return $credited;
    }
    private static function date($value):string
    {
        $date=is_string($value)?\DateTimeImmutable::createFromFormat('!Y-m-d',$value):false;
        if(!$date||$date->format('Y-m-d')!==$value)throw new \DomainException('asaas_company_card_settlement_requires_review');
        return $value;
    }
}
