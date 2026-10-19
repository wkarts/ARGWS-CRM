<?php

declare(strict_types=1);
namespace BancoBrasil;
require_once __DIR__.'/Fields.php';
/** Explicit opt-in contract for fixed BRL per overdue day. No percentages, compound interest or inherited rate. */
final class ApiDailyInterest
{
    public static function declaration(array $input):array
    {
        if(!isset($input['interest_policy'])){
            if(array_key_exists('interest_policy_confirmed',$input)||($input['interest_exempt']??null)!=='1')throw new \DomainException('bb_api_contract_declaration_required');return [];
        }
        if(array_key_exists('interest_exempt',$input)||!in_array($input['interest_policy'],['exempt','daily_fixed'],true)||($input['interest_policy_confirmed']??null)!=='1')throw new \DomainException('bb_api_contract_declaration_required');
        return $input['interest_policy']==='exempt'?[]:['interest_policy'=>'daily_fixed','interest_contract_confirmed'=>true];
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
    public static function parameters(array $profile,array $input,int $nominal):array
    {
        if(($profile['interest_policy']??null)!=='daily_fixed'){
            if(isset($input['daily_interest']))throw new \DomainException('bb_api_interest_profile_denied');return ['tipo'=>3];
        }
        if(($profile['interest_contract_confirmed']??null)!==true||($profile['interest_exempt']??null)!==false)throw new \DomainException('bb_api_interest_profile_denied');
        $daily=self::amount($input['daily_interest']??null);if(Fields::cents($daily)>$nominal)throw new \DomainException('bb_api_interest_exceeds_nominal');return ['tipo'=>1,'valor'=>$daily];
    }
    public static function confirm(array $detail,array $payload):void
    {
        if(($payload['jurosMora']['tipo']??null)!=='1')return;
        if(($detail['codigoTipoJuroMora']??null)!=='1'||!isset($detail['valorJuroMoraTitulo'])||self::money($detail['valorJuroMoraTitulo'])!==self::money($payload['jurosMora']['valor'])||!isset($detail['percentualJuroMoraTitulo'])||self::money($detail['percentualJuroMoraTitulo'])!==0)throw new \DomainException('bb_api_interest_query_mismatch');
    }
    public static function encode(array $data):string
    {
        $json=json_encode($data,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);
        // Only the validated leaf below is emitted as a JSON number; no float conversion or general string coercion.
        if(($data['jurosMora']['tipo']??null)===1){$money=self::amount($data['jurosMora']['valor']);$needle='"jurosMora":{"tipo":1,"valor":"'.$money.'"}';if(substr_count($json,$needle)!==1)throw new \DomainException('bb_api_interest_invalid');$json=str_replace($needle,'"jurosMora":{"tipo":1,"valor":'.$money.'}',$json);}
        return $json;
    }
}
