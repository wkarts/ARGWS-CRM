<?php

declare(strict_types=1);
namespace BancoBrasil;
/** A first percentage discount offer; no monetary calculation or financial settlement authority. */
final class ApiPercentageDiscount
{
    public static function declaration(array $input):array
    {
        if(($input['discount_policy_confirmed']??null)!=='1')throw new \DomainException('bb_api_discount_contract_required');return ['discount_policy'=>'percentage_until','discount_contract_confirmed'=>true];
    }
    public static function percentage($input):string
    {
        if(!is_string($input)||!preg_match('/^(0|[1-9][0-9]?)(?:\.[0-9]{1,2})?$/D',$input))throw new \DomainException('bb_api_discount_percentage_invalid');$parts=explode('.',$input);$minor=((int)$parts[0])*100+(int)str_pad($parts[1]??'',2,'0');if($minor<1||$minor>9999)throw new \DomainException('bb_api_discount_percentage_invalid');return $parts[0].'.'.str_pad($parts[1]??'',2,'0');
    }
    public static function parameters(array $profile,array $input,array $invoice,string $today):array
    {
        if(($profile['discount_contract_confirmed']??null)!==true||array_key_exists('fixed_discount',$input))throw new \DomainException('bb_api_discount_profile_denied');$percentage=self::percentage($input['discount_percentage']??null);$until=ApiFixedDiscount::date($input['discount_until']??null);if($until<$today||$until<$invoice['date']||$until>$invoice['duedate'])throw new \DomainException('bb_api_discount_date_requires_review');return ['tipo'=>2,'dataExpiracao'=>(new \DateTimeImmutable($until))->format('d.m.Y'),'porcentagem'=>$percentage];
    }
    public static function encode(string $json,array $data):string
    {
        $percentage=self::percentage($data['desconto']['porcentagem']??null);$until=$data['desconto']['dataExpiracao']??'';$date=\DateTimeImmutable::createFromFormat('!d.m.Y',$until);if(!$date||$date->format('d.m.Y')!==$until)throw new \DomainException('bb_api_discount_invalid');$needle='"desconto":{"tipo":2,"dataExpiracao":"'.$until.'","porcentagem":"'.$percentage.'"}';if(substr_count($json,$needle)!==1)throw new \DomainException('bb_api_discount_invalid');return str_replace($needle,'"desconto":{"tipo":2,"dataExpiracao":"'.$until.'","porcentagem":'.$percentage.'}',$json);
    }
    private static function zero($input):bool{return is_string($input)&&preg_match('/^0+(?:\.0+)?$/D',$input)===1;}
    public static function confirm(array $detail,array $payload):void
    {
        try{$until=$detail['dataDescontoTitulo']??null;if(!is_string($until)||!preg_match('/^[0-9]{2}[.\/][0-9]{2}[.\/][0-9]{4}$/D',$until))throw new \DomainException();$until=str_replace('/','.',$until);$date=\DateTimeImmutable::createFromFormat('!d.m.Y',$until);if(!$date||$date->format('d.m.Y')!==$until||$until!==$payload['desconto']['dataExpiracao']||($detail['codigoDescontoTitulo']??null)!=='2'||self::percentage($detail['percentualDescontoTitulo']??null)!==self::percentage($payload['desconto']['porcentagem']??null)||!self::zero($detail['valorDescontoTitulo']??null))throw new \DomainException();foreach(['Segundo','Terceiro'] as $ordinal)if(!self::zero($detail['codigo'.$ordinal.'DescontoTitulo']??null)||!self::zero($detail['valor'.$ordinal.'DescontoTitulo']??null)||!self::zero($detail['percentual'.$ordinal.'DescontoTitulo']??null))throw new \DomainException();}catch(\Throwable $e){throw new \DomainException('bb_api_discount_query_mismatch');}
    }
    public static function projection(array $payload):array
    {
        $until=\DateTimeImmutable::createFromFormat('!d.m.Y',$payload['desconto']['dataExpiracao']??'');if(!$until||$until->format('d.m.Y')!==($payload['desconto']['dataExpiracao']??null))throw new \DomainException('bb_api_discount_query_mismatch');return ['bank_discount_percentage'=>self::percentage($payload['desconto']['porcentagem']??null),'bank_discount_until'=>$until->format('Y-m-d')];
    }
}
