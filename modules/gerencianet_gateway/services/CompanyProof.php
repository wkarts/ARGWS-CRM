<?php
declare(strict_types=1);
namespace Efi;
require_once __DIR__.'/EfiChargeLedger.php';
/** Sanitized query projection, not a callback-payload authorization mechanism. */
final class CompanyProof
{
    public static function hash(array $value):string{return hash('sha256',json_encode($value,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR));}
    public static function provider(string $kind,$value):string
    {
        if((!is_string($value)&&!is_int($value))||!preg_match($kind==='pix'?'/^[A-Za-z0-9]{26,35}$/D':'/^[1-9][0-9]{0,19}$/D',(string)$value))throw new \DomainException('efi_company_provider_invalid');
        return (string)$value;
    }
    private static function date($value):string
    {
        if(!is_string($value)||!preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:\.\d{1,6})?(?:Z|[+-]\d{2}:\d{2})$/D',$value))throw new \DomainException('efi_company_receipt_date_unproven');
        $date=new \DateTimeImmutable($value);$errors=\DateTimeImmutable::getLastErrors();
        if($errors!==false&&($errors['warning_count']||$errors['error_count']))throw new \DomainException('efi_company_receipt_date_unproven');
        return $date->setTimezone(new \DateTimeZone('America/Sao_Paulo'))->format('Y-m-d');
    }
    public static function query(array $origin,array $response):array
    {
        $kind=$origin['kind'];$id=self::provider($kind,$kind==='pix'?($response['txid']??null):($response['data']['charge_id']??null));
        if($id!==$origin['provider_id'])throw new \DomainException('efi_company_provider_conflict');
        $amount=$kind==='pix'?\EfiChargeLedger::minor($response['valor']['original']??null):($response['data']['total']??null);
        if((!is_int($amount)&&!is_string($amount))||!preg_match('/^[1-9][0-9]{0,14}$/D',(string)$amount)||(int)$amount!==(int)$origin['amount_minor'])throw new \DomainException('efi_company_amount_conflict');
        $status=$kind==='pix'?($response['status']??null):($response['data']['status']??null);
        if(!is_string($status)||!preg_match('/^[A-Za-z_]{1,32}$/D',$status))throw new \DomainException('efi_company_status_invalid');
        $safe=['kind'=>$kind,'provider_id'=>$id,'amount_minor'=>(int)$amount,'currency'=>'BRL','status'=>$status,'date'=>null,'settlement_hash'=>null,'receipt_eligible'=>false,'adverse'=>in_array($status,['refunded','partially_refunded','contested','canceled','unpaid','REMOVIDA_PELO_USUARIO_RECEBEDOR','REMOVIDA_PELO_PSP'],true)];
        try{
            \EfiChargeLedger::assertPaid($origin,$response);
            // detailCharge status/total does not prove bank receipt date/value or card liquidation.
            // Official paid_at/paid_value examples are from listCharges and do not establish
            // this endpoint's units. Do not invent a cross-endpoint financial contract.
            if($kind!=='pix'||$safe['adverse'])return $safe;
            $safe['date']=self::date($response['pix'][0]['horario']??null);
            $safe['settlement_hash']=hash('sha256',$response['pix'][0]['endToEndId']);
            $safe['receipt_eligible']=true;
        }catch(\DomainException $error){if($kind==='pix'&&$status==='CONCLUIDA')$safe['adverse']=true;/* Incomplete/composite/refunded Pix requires review, never silently healed by a later projection. */}
        return $safe;
    }
}
