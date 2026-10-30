<?php
declare(strict_types=1);
namespace BancoBrasil;
require_once __DIR__.'/Fields.php';
/** Official PATCH registered abatement, distinct from financial valorAbatimentoTotal. */
final class ApiAbatement
{
    public const CONTRACT='bb_api_registered_abatement_patch_v1';
    public static function input(array $input):array
    {
        $keys=['action','amount','contract_confirmed','reference','reason'];if(array_diff(array_keys($input),$keys)||array_diff($keys,array_keys($input))||!in_array($input['action']??null,['include','alter'],true)||($input['contract_confirmed']??null)!=='1')throw new \DomainException('bb_api_abatement_contract_required');
        if(!is_string($input['amount'])||!preg_match('/^(0|[1-9][0-9]{0,7})(?:\.[0-9]{1,2})?$/D',$input['amount']))throw new \DomainException('bb_api_abatement_invalid');$n=explode('.',$input['amount']);$amount=Fields::amount(Fields::cents($n[0].'.'.str_pad($n[1]??'',2,'0')));if(Fields::cents($amount)<1)throw new \DomainException('bb_api_abatement_invalid');
        $out=['action'=>$input['action'],'amount'=>$amount,'contract_confirmed'=>'1'];foreach(['reference'=>191,'reason'=>500]as$field=>$max){$value=$input[$field];if(!is_string($value)||trim($value)===''||strlen(trim($value))>$max||preg_match('/[\x00-\x1f\x7f]/',$value)||preg_match('//u',$value)!==1)throw new \DomainException('bb_api_abatement_invalid');$out[$field]=trim($value);}return$out;
    }
    public static function observed(array $detail):?string
    {
        if(!array_key_exists('valorAbatimentoTituloCobranca',$detail))return null;
        try{return Fields::amount(\app\services\InvoicePaymentWriter::minorUnits($detail['valorAbatimentoTituloCobranca']));}catch(\Throwable$error){throw new \DomainException('bb_api_abatement_query_requires_review');}
    }
    public static function payload(string $agreement,array $intent):string
    {
        $intent=self::input($intent);if(!preg_match('/^[1-9][0-9]{6}$/D',$agreement))throw new \DomainException('bb_api_origin_invalid');
        $flags=['indicadorAlterarAbatimento','indicadorAlterarDataDesconto','indicadorAlterarDesconto','indicadorAlterarEnderecoPagador','indicadorAlterarPrazoBoletoVencido','indicadorAlterarSeuNumero','indicadorAtribuirDesconto','indicadorCancelarProtesto','indicadorCobrarJuros','indicadorCobrarMulta','indicadorDispensarJuros','indicadorDispensarMulta','indicadorIncluirAbatimento','indicadorNegativar','indicadorNovaDataVencimento','indicadorProtestar','indicadorSustacaoProtesto','indicadorNovoValorNominal'];
        $data=['numeroConvenio'=>(int)$agreement]+array_fill_keys($flags,'N');$include=$intent['action']==='include';$data[$include?'indicadorIncluirAbatimento':'indicadorAlterarAbatimento']='S';$field=$include?'valorAbatimento':'novoValorAbatimento';$data[$include?'abatimento':'alteracaoAbatimento']=[$field=>$intent['amount']];$json=json_encode($data,JSON_THROW_ON_ERROR);$quoted='"'.$field.'":"'.$intent['amount'].'"';if(substr_count($json,$quoted)!==1)throw new \DomainException('bb_api_abatement_invalid');return str_replace($quoted,'"'.$field.'":'.$intent['amount'],$json);
    }
    public static function fingerprint(array $origin,array $snapshot,array $intent):string{return hash('sha256',json_encode([self::CONTRACT,$origin['payload_hash'],$snapshot['api_stage'],$snapshot['profile_hash'],self::input($intent)],JSON_THROW_ON_ERROR));}
    public static function acknowledged(array $data,array $snapshot,array $intent,string $fingerprint):array
    {
        $contract=$data['numeroContratoCobranca']??null;$date=$data['dataAtualizacao']??null;$time=$data['horarioAtualizacao']??null;if(!is_string($contract)||$contract!==($snapshot['accepted_contract']??null)||!is_string($date)||!preg_match('/^([0-9]{2})[.\/]([0-9]{2})[.\/]([0-9]{4})$/D',$date,$m)||!is_string($time)||!preg_match('/^(?:[01][0-9]|2[0-3]):[0-5][0-9]:[0-5][0-9]$/D',$time))throw new \DomainException('bb_api_abatement_response_invalid');Fields::date($m[3].'-'.$m[2].'-'.$m[1]);return['numeroContratoCobranca'=>$contract,'dataAtualizacao'=>$date,'horarioAtualizacao'=>$time,'bank_state'=>'abatement_requested','abatement'=>self::input($intent),'abatement_contract'=>self::CONTRACT,'abatement_fingerprint'=>$fingerprint];
    }
}
