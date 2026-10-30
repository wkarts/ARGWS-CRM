<?php
declare(strict_types=1);
namespace Asaas;
/** Bounded plain-text instruments only: never remote HTML, arbitrary links or images. */
final class CompanyInstruments
{
    public static function parse(string $type,array $response):array
    {
        if($type==='PIX'){
            $payload=$response['payload']??null;$expiration=$response['expirationDate']??null;
            if(!is_string($payload)||strlen($payload)<20||strlen($payload)>2048||preg_match('/[\x00-\x1f\x7f]/',$payload)||preg_match('//u',$payload)!==1||!is_string($expiration))throw new \DomainException('asaas_instrument_invalid');
            $date=\DateTimeImmutable::createFromFormat('!Y-m-d H:i:s',$expiration,new \DateTimeZone('America/Sao_Paulo'));
            if(!$date||$date->format('Y-m-d H:i:s')!==$expiration||$date->getTimestamp()<=time())throw new \DomainException('asaas_instrument_expired');
            return ['billing_type'=>'PIX','payload'=>$payload,'expiration_date'=>$expiration];
        }
        if($type==='BOLETO'){
            $line=$response['identificationField']??null;$barcode=$response['barCode']??null;$number=$response['nossoNumero']??null;
            if(!is_string($line)||!preg_match('/^[0-9 .-]{44,80}$/D',$line)||!is_string($barcode)||!preg_match('/^[0-9]{44}$/D',$barcode)||!is_string($number)||!preg_match('/^[A-Za-z0-9.-]{1,64}$/D',$number))throw new \DomainException('asaas_instrument_invalid');
            $line=preg_replace('/[ .-]/','',$line);if(strlen($line)!==47)throw new \DomainException('asaas_instrument_invalid');
            return ['billing_type'=>'BOLETO','identification_field'=>$line,'bar_code'=>$barcode,'nosso_numero'=>$number];
        }
        throw new \DomainException('asaas_instrument_profile_unavailable');
    }
}
