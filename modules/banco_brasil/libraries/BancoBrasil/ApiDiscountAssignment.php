<?php
declare(strict_types=1);
namespace BancoBrasil;
require_once __DIR__.'/ApiFixedDiscount.php';
/** First-tier operational offer only; registration and actual received reductions remain separate contracts. */
final class ApiDiscountAssignment
{
    public const CONTRACT='bb_api_assign_registered_first_discount_patch_v1';
    public static function declaration(array$input):array
    {
        if(!array_key_exists('discount_assignment_policy',$input)){if(array_key_exists('discount_assignment_confirmed',$input))throw new \DomainException('bb_api_discount_assignment_contract_required');return[];}
        if($input['discount_assignment_policy']==='disabled')return[];if($input['discount_assignment_policy']!=='first_fixed_or_percentage'||($input['discount_assignment_confirmed']??null)!=='1')throw new \DomainException('bb_api_discount_assignment_contract_required');return['discount_assignment_policy'=>'first_fixed_or_percentage','discount_assignment_contract'=>self::CONTRACT,'discount_assignment_contract_confirmed'=>true];
    }
    public static function input(array $input):array
    {
        $keys=['type','value','until','contract_confirmed','reference','reason'];if(array_diff(array_keys($input),$keys)||array_diff($keys,array_keys($input))||!in_array($input['type']??null,['1','2'],true)||($input['contract_confirmed']??null)!=='1')throw new \DomainException('bb_api_discount_amend_contract_required');
        $value=$input['type']==='1'?ApiFixedDiscount::amount($input['value']??null):ApiPercentageDiscount::percentage($input['value']??null);$out=['type'=>$input['type'],'value'=>$value,'until'=>ApiFixedDiscount::date($input['until']??null),'contract_confirmed'=>'1'];
        foreach(['reference'=>191,'reason'=>500]as$field=>$max){$value=$input[$field];if(!is_string($value)||trim($value)===''||strlen(trim($value))>$max||preg_match('/[\x00-\x1f\x7f]/',$value)||preg_match('//u',$value)!==1)throw new \DomainException('bb_api_discount_amend_invalid');$out[$field]=trim($value);}return$out;
    }
    public static function offer(array $input):array
    {
        $intent=self::input($input);return['tipo'=>$intent['type'],'dataExpiracao'=>(new \DateTimeImmutable($intent['until']))->format('d.m.Y'),($intent['type']==='1'?'valor':'porcentagem')=>$intent['value']];
    }
    public static function hash(array $intent):string{return hash('sha256',json_encode(self::offer($intent),JSON_THROW_ON_ERROR));}
    public static function fingerprint(array $origin,array $snapshot,array $intent):string{return hash('sha256',json_encode([self::CONTRACT,$origin['payload_hash'],$snapshot['api_stage'],$snapshot['profile_hash'],self::input($intent)],JSON_THROW_ON_ERROR));}
    public static function matches(array $detail,array $intent):bool
    {
        try{ApiFixedDiscount::confirm($detail,['desconto'=>self::offer($intent)]);return true;}catch(\Throwable$error){return false;}
    }
    public static function projectedMatches(array $result,array $intent):bool
    {
        try{$intent=self::input($intent);$projection=ApiFixedDiscount::projection(['desconto'=>self::offer($intent)]);foreach($projection as$key=>$value)if(($result[$key]??null)!==$value)return false;return($result['bank_discount_type']??null)===$intent['type']&&($result['bank_discount_offer_hash']??null)===self::hash($intent);}catch(\Throwable$error){return false;}
    }
    public static function payload(string $agreement,array $intent):string
    {
        $intent=self::input($intent);if(!preg_match('/^[1-9][0-9]{6}$/D',$agreement))throw new \DomainException('bb_api_origin_invalid');$flags=['indicadorAlterarAbatimento','indicadorAlterarDataDesconto','indicadorAlterarDesconto','indicadorAlterarEnderecoPagador','indicadorAlterarPrazoBoletoVencido','indicadorAlterarSeuNumero','indicadorAtribuirDesconto','indicadorCancelarProtesto','indicadorCobrarJuros','indicadorCobrarMulta','indicadorDispensarJuros','indicadorDispensarMulta','indicadorIncluirAbatimento','indicadorNegativar','indicadorNovaDataVencimento','indicadorProtestar','indicadorSustacaoProtesto','indicadorNovoValorNominal'];
        $data=['numeroConvenio'=>(int)$agreement]+array_fill_keys($flags,'N');$data['indicadorAtribuirDesconto']='S';$field=$intent['type']==='1'?'valorPrimeiroDesconto':'percentualPrimeiroDesconto';$data['desconto']=['tipoPrimeiroDesconto'=>(int)$intent['type'],$field=>$intent['value'],'dataPrimeiroDesconto'=>(new \DateTimeImmutable($intent['until']))->format('d.m.Y')];$json=json_encode($data,JSON_THROW_ON_ERROR);$needle='"'.$field.'":"'.$intent['value'].'"';if(substr_count($json,$needle)!==1)throw new \DomainException('bb_api_discount_amend_invalid');return str_replace($needle,'"'.$field.'":'.$intent['value'],$json);
    }
    public static function acknowledged(array $data,array $snapshot,array $intent,string $fingerprint):array
    {
        $contract=$data['numeroContratoCobranca']??null;$date=$data['dataAtualizacao']??null;$time=$data['horarioAtualizacao']??null;if(!is_string($contract)||$contract!==($snapshot['accepted_contract']??null)||!is_string($date)||!preg_match('/^([0-9]{2})[.\/]([0-9]{2})[.\/]([0-9]{4})$/D',$date,$m)||!is_string($time)||!preg_match('/^(?:[01][0-9]|2[0-3]):[0-5][0-9]:[0-5][0-9]$/D',$time))throw new \DomainException('bb_api_discount_amend_response_invalid');Fields::date($m[3].'-'.$m[2].'-'.$m[1]);return['numeroContratoCobranca'=>$contract,'dataAtualizacao'=>$date,'horarioAtualizacao'=>$time,'bank_state'=>'discount_requested','discount_instruction'=>self::input($intent),'discount_contract'=>self::CONTRACT,'discount_fingerprint'=>$fingerprint];
    }
}
