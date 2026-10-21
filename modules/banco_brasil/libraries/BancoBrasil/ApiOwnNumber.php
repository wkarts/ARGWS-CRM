<?php
declare(strict_types=1);
namespace BancoBrasil;
require_once __DIR__.'/Fields.php';
/** Bank-only beneficiary reference. Aliases never route a payment or replace the canonical fiscal identity. */
final class ApiOwnNumber
{
    public const CONTRACT='bb_api_own_number_alias_v1';
    public static function reference($value,bool$new=false):string
    {
        // Deliberately narrow local profile; OpenAPI does not publish a universal length for codigoSeuNumero.
        if(!is_string($value)||!preg_match($new?'/^[A-Z0-9][A-Z0-9-]{0,14}$/D':'/^[A-Z0-9][A-Z0-9-]{0,39}$/D',$value))throw new \DomainException('bb_api_reference_invalid');return$value;
    }
    public static function input(array$input):array
    {
        $keys=['previous_reference','new_reference','contract_confirmed','reference','reason'];if(array_diff(array_keys($input),$keys)||array_diff($keys,array_keys($input))||($input['contract_confirmed']??null)!=='1')throw new \DomainException('bb_api_reference_contract_required');$out=['previous_reference'=>self::reference($input['previous_reference']),'new_reference'=>self::reference($input['new_reference'],true),'contract_confirmed'=>'1'];if($out['previous_reference']===$out['new_reference'])throw new \DomainException('bb_api_reference_unchanged');
        foreach(['reference'=>191,'reason'=>500]as$field=>$max){$value=$input[$field];if(!is_string($value)||trim($value)===''||strlen(trim($value))>$max||preg_match('/[\x00-\x1f\x7f]/',$value)||preg_match('//u',$value)!==1)throw new \DomainException('bb_api_reference_invalid');$out[$field]=trim($value);}return$out;
    }
    public static function fingerprint(array$origin,array$snapshot,array$intent):string{return hash('sha256',json_encode([self::CONTRACT,$origin['payload_hash'],$snapshot['api_stage'],$snapshot['profile_hash'],self::input($intent)],JSON_THROW_ON_ERROR));}
    public static function payload(string$agreement,array$intent):string
    {
        $intent=self::input($intent);if(!preg_match('/^[1-9][0-9]{6}$/D',$agreement))throw new \DomainException('bb_api_origin_invalid');$flags=['indicadorAlterarAbatimento','indicadorAlterarDataDesconto','indicadorAlterarDesconto','indicadorAlterarEnderecoPagador','indicadorAlterarPrazoBoletoVencido','indicadorAlterarSeuNumero','indicadorAtribuirDesconto','indicadorCancelarProtesto','indicadorCobrarJuros','indicadorCobrarMulta','indicadorDispensarJuros','indicadorDispensarMulta','indicadorIncluirAbatimento','indicadorNegativar','indicadorNovaDataVencimento','indicadorProtestar','indicadorSustacaoProtesto','indicadorNovoValorNominal'];$data=['numeroConvenio'=>(int)$agreement]+array_fill_keys($flags,'N');$data['indicadorAlterarSeuNumero']='S';$data['alteracaoSeuNumero']=['codigoSeuNumero'=>$intent['new_reference']];return json_encode($data,JSON_THROW_ON_ERROR);
    }
    public static function acknowledged(array$data,array$snapshot,array$intent,string$fingerprint):array
    {
        $contract=$data['numeroContratoCobranca']??null;$date=$data['dataAtualizacao']??null;$time=$data['horarioAtualizacao']??null;if(!is_string($contract)||$contract!==($snapshot['accepted_contract']??null)||!is_string($date)||!preg_match('/^([0-9]{2})[.\/]([0-9]{2})[.\/]([0-9]{4})$/D',$date,$m)||!is_string($time)||!preg_match('/^(?:[01][0-9]|2[0-3]):[0-5][0-9]:[0-5][0-9]$/D',$time))throw new \DomainException('bb_api_reference_response_invalid');try{Fields::date($m[3].'-'.$m[2].'-'.$m[1]);}catch(\InvalidArgumentException$error){throw new \DomainException('bb_api_reference_response_invalid');}        return ['numeroContratoCobranca'=>$contract,'dataAtualizacao'=>$date,'horarioAtualizacao'=>$time]+['bank_state'=>'reference_requested','reference_instruction'=>self::input($intent),'reference_contract'=>self::CONTRACT,'reference_fingerprint'=>$fingerprint];
    }
}
