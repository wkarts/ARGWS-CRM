<?php

declare(strict_types=1);
namespace BancoBrasil;
require_once __DIR__.'/Fields.php';require_once __DIR__.'/ApiPercentageDiscount.php';
/** Contracted first fixed discount offer. Publication never posts a financial reduction or calculates received cash. */
final class ApiFixedDiscount
{
    public static function declaration(array $input):array
    {
        if(($input['discount_policy']??null)==='percentage_until')return ApiPercentageDiscount::declaration($input);
        if(!array_key_exists('discount_policy',$input)){if(array_key_exists('discount_policy_confirmed',$input))throw new \DomainException('bb_api_discount_contract_required');return [];}
        if($input['discount_policy']==='none'){if(isset($input['discount_policy_confirmed'])&&$input['discount_policy_confirmed']!=='1')throw new \DomainException('bb_api_discount_contract_required');return [];}
        if($input['discount_policy']!=='fixed_until'||($input['discount_policy_confirmed']??null)!=='1')throw new \DomainException('bb_api_discount_contract_required');return ['discount_policy'=>'fixed_until','discount_contract_confirmed'=>true];
    }
    public static function amount($input):string
    {
        if(!is_string($input)||!preg_match('/^(0|[1-9][0-9]{0,7})(?:\.[0-9]{1,2})?$/D',$input))throw new \DomainException('bb_api_discount_invalid');$parts=explode('.',$input);$value=Fields::cents($parts[0].'.'.str_pad($parts[1]??'',2,'0'));if($value<1||$value>9999999999)throw new \DomainException('bb_api_discount_invalid');return Fields::amount($value);
    }
    private static function zero($input):bool
    {
        return is_string($input)&&preg_match('/^0+(?:\.0+)?$/D',$input)===1;
    }
    public static function date($input):string
    {
        if(!is_string($input)||!preg_match('/^[0-9]{4}-[0-9]{2}-[0-9]{2}$/D',$input))throw new \DomainException('bb_api_discount_invalid');try{Fields::date($input);}catch(\Throwable $e){throw new \DomainException('bb_api_discount_invalid');}return $input;
    }
    public static function parameters(array $profile,array $input,array $invoice,string $today,int $nominal):array
    {
        if(($profile['discount_policy']??null)==='percentage_until')return ApiPercentageDiscount::parameters($profile,$input,$invoice,$today);if(array_key_exists('discount_percentage',$input))throw new \DomainException('bb_api_discount_profile_denied');
        if(($profile['discount_policy']??null)!=='fixed_until'){if(array_key_exists('fixed_discount',$input)||array_key_exists('discount_until',$input))throw new \DomainException('bb_api_discount_profile_denied');return ['tipo'=>0];}
        if(($profile['discount_contract_confirmed']??null)!==true)throw new \DomainException('bb_api_discount_profile_denied');$amount=self::amount($input['fixed_discount']??null);$until=self::date($input['discount_until']??null);
        // Conservative local offer: positive cash remains payable; its window is inside the issued title, not retroactive.
        if(Fields::cents($amount)>=$nominal)throw new \DomainException('bb_api_discount_exceeds_nominal');if($until<$today||$until<$invoice['date']||$until>$invoice['duedate'])throw new \DomainException('bb_api_discount_date_requires_review');return ['tipo'=>1,'dataExpiracao'=>(new \DateTimeImmutable($until))->format('d.m.Y'),'valor'=>$amount];
    }
    public static function encode(string $json,array $data):string
    {
        if(($data['desconto']['tipo']??null)===2)return ApiPercentageDiscount::encode($json,$data);if(($data['desconto']['tipo']??null)!==1)return $json;$amount=self::amount($data['desconto']['valor']??null);$until=$data['desconto']['dataExpiracao']??'';$parsed=\DateTimeImmutable::createFromFormat('!d.m.Y',$until);if(!$parsed||$parsed->format('d.m.Y')!==$until)throw new \DomainException('bb_api_discount_invalid');$needle='"desconto":{"tipo":1,"dataExpiracao":"'.$until.'","valor":"'.$amount.'"}';if(substr_count($json,$needle)!==1)throw new \DomainException('bb_api_discount_invalid');return str_replace($needle,'"desconto":{"tipo":1,"dataExpiracao":"'.$until.'","valor":'.$amount.'}',$json);
    }
    public static function projection(array $payload):array
    {
        if(($payload['desconto']['tipo']??null)==='2')return ApiPercentageDiscount::projection($payload);if(($payload['desconto']['tipo']??null)!=='1')return [];$until=\DateTimeImmutable::createFromFormat('!d.m.Y',$payload['desconto']['dataExpiracao']??'');if(!$until||$until->format('d.m.Y')!==($payload['desconto']['dataExpiracao']??null))throw new \DomainException('bb_api_discount_query_mismatch');return ['bank_discount_amount'=>self::amount($payload['desconto']['valor']??null),'bank_discount_until'=>$until->format('Y-m-d')];
    }
    public static function confirm(array $detail,array $payload):void
    {
        if(($payload['desconto']['tipo']??null)==='2'){ApiPercentageDiscount::confirm($detail,$payload);return;}if(($payload['desconto']['tipo']??null)!=='1')return;
        try{$until=$detail['dataDescontoTitulo']??null;if(!is_string($until)||!preg_match('/^[0-9]{2}[.\/][0-9]{2}[.\/][0-9]{4}$/D',$until))throw new \DomainException();$until=str_replace('/','.',$until);$date=\DateTimeImmutable::createFromFormat('!d.m.Y',$until);if(!$date||$date->format('d.m.Y')!==$until||$until!==$payload['desconto']['dataExpiracao']||($detail['codigoDescontoTitulo']??null)!=='1'||self::amount($detail['valorDescontoTitulo']??null)!==self::amount($payload['desconto']['valor'])||!self::zero($detail['percentualDescontoTitulo']??null))throw new \DomainException();foreach(['Segundo','Terceiro'] as $ordinal){if(!self::zero($detail['codigo'.$ordinal.'DescontoTitulo']??null)||!self::zero($detail['valor'.$ordinal.'DescontoTitulo']??null)||!self::zero($detail['percentual'.$ordinal.'DescontoTitulo']??null))throw new \DomainException();}}
        catch(\Throwable $e){throw new \DomainException('bb_api_discount_query_mismatch');}
    }
}
