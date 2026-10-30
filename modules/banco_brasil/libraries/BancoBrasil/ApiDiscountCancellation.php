<?php
declare(strict_types=1);
namespace BancoBrasil;
require_once __DIR__.'/ApiFixedDiscount.php';
/** First-tier operational offer only; registration and actual received reductions remain separate contracts. */
final class ApiDiscountCancellation
{
    public const CONTRACT='bb_api_cancel_registered_first_discount_patch_v1';
    public static function input(array $input):array
    {
        $keys=['contract_confirmed','reference','reason'];if(array_diff(array_keys($input),$keys)||array_diff($keys,array_keys($input))||($input['contract_confirmed']??null)!=='1')throw new \DomainException('bb_api_discount_cancel_contract_required');$out=['contract_confirmed'=>'1'];
        foreach(['reference'=>191,'reason'=>500]as$field=>$max){$value=$input[$field];if(!is_string($value)||trim($value)===''||strlen(trim($value))>$max||preg_match('/[\x00-\x1f\x7f]/',$value)||preg_match('//u',$value)!==1)throw new \DomainException('bb_api_discount_cancel_invalid');$out[$field]=trim($value);}return$out;
    }

    public static function offer(array $input):array
    {
        self::input($input);return ['tipo'=>'0'];
    }

    public static function hash(array $intent):string{return hash('sha256',json_encode(self::offer($intent),JSON_THROW_ON_ERROR));}
    public static function fingerprint(array $origin,array $snapshot,array $intent):string{return hash('sha256',json_encode([self::CONTRACT,$origin['payload_hash'],$snapshot['api_stage'],$snapshot['profile_hash'],self::input($intent)],JSON_THROW_ON_ERROR));}
    public static function matches(array $detail,array $intent):bool
    {
        self::input($intent);foreach(['','Segundo','Terceiro']as$ordinal)foreach(['codigo','valor','percentual']as$field){$value=$detail[$field.$ordinal.'DescontoTitulo']??null;if(!is_string($value)||preg_match('/^0+(?:\.0+)?$/D',$value)!==1)return false;}return true;
    }

    public static function projectedMatches(array $result,array $intent):bool
    {
        try{self::input($intent);return ($result['bank_discount_type']??null)==='0'&&($result['bank_discount_offer_hash']??null)===self::hash($intent)&&($result['bank_discount_absent_confirmed']??null)==='1';}catch(\Throwable$error){return false;}
    }

    public static function payload(string $agreement,array $intent):string
    {
        $intent=self::input($intent);if(!preg_match('/^[1-9][0-9]{6}$/D',$agreement))throw new \DomainException('bb_api_origin_invalid');$flags=['indicadorAlterarAbatimento','indicadorAlterarDataDesconto','indicadorAlterarDesconto','indicadorAlterarEnderecoPagador','indicadorAlterarPrazoBoletoVencido','indicadorAlterarSeuNumero','indicadorAtribuirDesconto','indicadorCancelarProtesto','indicadorCobrarJuros','indicadorCobrarMulta','indicadorDispensarJuros','indicadorDispensarMulta','indicadorIncluirAbatimento','indicadorNegativar','indicadorNovaDataVencimento','indicadorProtestar','indicadorSustacaoProtesto','indicadorNovoValorNominal'];
        $data=['numeroConvenio'=>(int)$agreement]+array_fill_keys($flags,'N');$data['indicadorAlterarDesconto']='S';$data['alteracaoDesconto']=['tipoPrimeiroDesconto'=>0];return json_encode($data,JSON_THROW_ON_ERROR);
    }

    public static function acknowledged(array $data,array $snapshot,array $intent,string $fingerprint):array
    {
        $contract=$data['numeroContratoCobranca']??null;$date=$data['dataAtualizacao']??null;$time=$data['horarioAtualizacao']??null;if(!is_string($contract)||$contract!==($snapshot['accepted_contract']??null)||!is_string($date)||!preg_match('/^([0-9]{2})[.\/]([0-9]{2})[.\/]([0-9]{4})$/D',$date,$m)||!is_string($time)||!preg_match('/^(?:[01][0-9]|2[0-3]):[0-5][0-9]:[0-5][0-9]$/D',$time))throw new \DomainException('bb_api_discount_amend_response_invalid');Fields::date($m[3].'-'.$m[2].'-'.$m[1]);return['numeroContratoCobranca'=>$contract,'dataAtualizacao'=>$date,'horarioAtualizacao'=>$time,'bank_state'=>'discount_requested','discount_instruction'=>self::input($intent),'discount_contract'=>self::CONTRACT,'discount_fingerprint'=>$fingerprint];
    }
}
