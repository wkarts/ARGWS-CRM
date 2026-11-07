<?php
declare(strict_types=1);
namespace Asaas;

/** Documented token contracts. No PAN/CVV/cardholder collection or persistent token storage. */
final class CompanyCardPaymentRequest
{
    public static function payload(string $operation,array $input):array
    {
        $expected=$operation==='payWithCreditCard'?['creditCardToken']:($operation==='payWithCard'?['cardType','cardToken']:[]);
        $keys=array_keys($input);sort($keys);$ordered=$expected;sort($ordered);
        if(!$expected||$keys!==$ordered)throw new \DomainException('asaas_card_request_invalid');
        $token=$input[$operation==='payWithCreditCard'?'creditCardToken':'cardToken'];
        if(!is_string($token)||!preg_match('/^[A-Za-z0-9_-]{1,512}$/D',$token))throw new \DomainException('asaas_card_request_invalid');
        if($operation==='payWithCard'&&!in_array($input['cardType'],['CREDIT','VOUCHER'],true))throw new \DomainException('asaas_card_type_unsupported');
        return array_replace(array_fill_keys($expected,null),$input);
    }
}
