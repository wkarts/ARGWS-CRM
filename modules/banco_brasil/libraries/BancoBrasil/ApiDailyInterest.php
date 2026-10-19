<?php

declare(strict_types=1);
namespace BancoBrasil;
require_once __DIR__.'/Fields.php';
/** Explicit fixed daily BRL or monthly percentage. Legacy class name preserves daily/exempt callers; no local interest accrual. */
final class ApiDailyInterest
{
    public static function declaration(array $input):array
    {
        if(!isset($input['interest_policy'])){
            if(array_key_exists('interest_policy_confirmed',$input)||($input['interest_exempt']??null)!=='1')throw new \DomainException('bb_api_contract_declaration_required');return [];
        }
        if(array_key_exists('interest_exempt',$input)||!in_array($input['interest_policy'],['exempt','daily_fixed','monthly_percentage'],true)||($input['interest_policy_confirmed']??null)!=='1')throw new \DomainException('bb_api_contract_declaration_required');
        return $input['interest_policy']==='exempt'?[]:['interest_policy'=>$input['interest_policy'],'interest_contract_confirmed'=>true];
    }
    private static function money($input):int
    {
        if(!is_string($input)||!preg_match('/^(0|[1-9][0-9]{0,12})(?:\.[0-9]{1,2})?$/D',$input))throw new \DomainException('bb_api_interest_invalid');
        $parts=explode('.',$input);return Fields::cents($parts[0].'.'.str_pad($parts[1]??'',2,'0'));
    }
    public static function amount($input):string
    {
        if(!is_string($input)||!preg_match('/^(0|[1-9][0-9]{0,7})(?:\.[0-9]{1,2})?$/D',$input))throw new \DomainException('bb_api_interest_invalid');
        $cents=self::money($input);if($cents<1||$cents>9999999999)throw new \DomainException('bb_api_interest_invalid');return Fields::amount($cents);
    }
    /** Local conservative profile: monthly0.01–100.00%, exactly two decimal places, no float or accrual formula. */
    public static function monthlyRate($input):string
    {
        if(!is_string($input)||!preg_match('/^(0|[1-9][0-9]{0,2})(?:\.[0-9]{1,2})?$/D',$input))throw new \DomainException('bb_api_interest_invalid');
        $value=self::money($input);if($value<1||$value>10000)throw new \DomainException('bb_api_interest_invalid');return Fields::amount($value);
    }
    public static function parameters(array $profile,array $input,int $nominal):array
    {
        if(($profile['interest_policy']??null)==='monthly_percentage'){
            if(isset($input['daily_interest'])||($profile['interest_contract_confirmed']??null)!==true||($profile['interest_exempt']??null)!==false)throw new \DomainException('bb_api_interest_profile_denied');
            return ['tipo'=>2,'porcentagem'=>self::monthlyRate($input['monthly_interest_percentage']??null)];
        }
        if(isset($input['monthly_interest_percentage']))throw new \DomainException('bb_api_interest_profile_denied');
        if(($profile['interest_policy']??null)!=='daily_fixed'){
            if(isset($input['daily_interest']))throw new \DomainException('bb_api_interest_profile_denied');return ['tipo'=>3];
        }
        if(($profile['interest_contract_confirmed']??null)!==true||($profile['interest_exempt']??null)!==false)throw new \DomainException('bb_api_interest_profile_denied');
        $daily=self::amount($input['daily_interest']??null);if(Fields::cents($daily)>$nominal)throw new \DomainException('bb_api_interest_exceeds_nominal');return ['tipo'=>1,'valor'=>$daily];
    }
    public static function confirm(array $detail,array $payload):void
    {
        if(($payload['jurosMora']['tipo']??null)==='2'){
            if(($detail['codigoTipoJuroMora']??null)!=='2'||!isset($detail['percentualJuroMoraTitulo'])||self::monthlyRate($detail['percentualJuroMoraTitulo'])!==self::monthlyRate($payload['jurosMora']['porcentagem'])||!isset($detail['valorJuroMoraTitulo'])||self::money($detail['valorJuroMoraTitulo'])!==0)throw new \DomainException('bb_api_interest_query_mismatch');return;
        }
        if(($payload['jurosMora']['tipo']??null)!=='1')return;
        if(($detail['codigoTipoJuroMora']??null)!=='1'||!isset($detail['valorJuroMoraTitulo'])||self::money($detail['valorJuroMoraTitulo'])!==self::money($payload['jurosMora']['valor'])||!isset($detail['percentualJuroMoraTitulo'])||self::money($detail['percentualJuroMoraTitulo'])!==0)throw new \DomainException('bb_api_interest_query_mismatch');
    }
    public static function encode(array $data):string
    {
        $json=json_encode($data,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);
        // Only the validated leaf below is emitted as a JSON number; no float conversion or general string coercion.
        if(($data['jurosMora']['tipo']??null)===1){$money=self::amount($data['jurosMora']['valor']);$needle='"jurosMora":{"tipo":1,"valor":"'.$money.'"}';if(substr_count($json,$needle)!==1)throw new \DomainException('bb_api_interest_invalid');$json=str_replace($needle,'"jurosMora":{"tipo":1,"valor":'.$money.'}',$json);}
        if(($data['jurosMora']['tipo']??null)===2){$rate=self::monthlyRate($data['jurosMora']['porcentagem']);$needle='"jurosMora":{"tipo":2,"porcentagem":"'.$rate.'"}';if(substr_count($json,$needle)!==1)throw new \DomainException('bb_api_interest_invalid');$json=str_replace($needle,'"jurosMora":{"tipo":2,"porcentagem":'.$rate.'}',$json);}
        return $json;
    }
}
