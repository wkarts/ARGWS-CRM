<?php
declare(strict_types=1);
namespace BancoBrasil;
require_once __DIR__.'/ApiClient.php';
/** Explicit positive receipt window. Zero would inherit an unknown agreement and is never accepted. */
final class ApiReceiptWindow
{
    public const CONTRACT='bb_api_explicit_receipt_window_v1';
    public static function days($value):string{if(!is_string($value)||!preg_match('/^[1-9][0-9]{0,3}$/D',$value)||(int)$value>2500)throw new \DomainException('bb_api_receipt_window_invalid');return(string)(int)$value;}
    public static function declaration(array$input):array
    {
        if(!array_key_exists('receipt_window_policy',$input)){if(array_key_exists('receipt_window_days',$input)||array_key_exists('receipt_window_confirmed',$input))throw new \DomainException('bb_api_receipt_window_contract_required');return[];}
        if($input['receipt_window_policy']==='until_due'){if(isset($input['receipt_window_days'])&&$input['receipt_window_days']!=='')throw new \DomainException('bb_api_receipt_window_invalid');return[];}
        if($input['receipt_window_policy']!=='explicit_after_due'||($input['receipt_window_confirmed']??null)!=='1')throw new \DomainException('bb_api_receipt_window_contract_required');return['receipt_window_policy'=>'explicit_after_due','receipt_window_days'=>self::days($input['receipt_window_days']??null),'receipt_window_contract'=>self::CONTRACT,'receipt_window_contract_confirmed'=>true];
    }
    public static function registration(array$profile):array
    {
        if(!isset($profile['receipt_window_policy']))return[];if($profile['receipt_window_policy']!=='explicit_after_due'||($profile['receipt_window_contract']??null)!==self::CONTRACT||($profile['receipt_window_contract_confirmed']??null)!==true)throw new \DomainException('bb_api_receipt_window_profile_denied');return['indicadorAceiteTituloVencido'=>'S','numeroDiasLimiteRecebimento'=>(int)self::days($profile['receipt_window_days']??null)];
    }
    public static function input(array$input):array
    {
        $keys=['days','contract_confirmed','reference','reason'];if(array_diff(array_keys($input),$keys)||array_diff($keys,array_keys($input))||($input['contract_confirmed']??null)!=='1')throw new \DomainException('bb_api_receipt_window_contract_required');$out=['days'=>self::days($input['days']??null),'contract_confirmed'=>'1'];foreach(['reference'=>191,'reason'=>500]as$f=>$max){$v=$input[$f];if(!is_string($v)||trim($v)===''||strlen(trim($v))>$max||preg_match('/[\x00-\x1f\x7f]/',$v)||preg_match('//u',$v)!==1)throw new \DomainException('bb_api_receipt_window_invalid');$out[$f]=trim($v);}return$out;
    }
    public static function payload(string$agreement,array$intent):string
    {
        $intent=self::input($intent);if(!preg_match('/^[1-9][0-9]{6}$/D',$agreement))throw new \DomainException('bb_api_origin_invalid');$flags=['indicadorAlterarAbatimento','indicadorAlterarDataDesconto','indicadorAlterarDesconto','indicadorAlterarEnderecoPagador','indicadorAlterarPrazoBoletoVencido','indicadorAlterarSeuNumero','indicadorAtribuirDesconto','indicadorCancelarProtesto','indicadorCobrarJuros','indicadorCobrarMulta','indicadorDispensarJuros','indicadorDispensarMulta','indicadorIncluirAbatimento','indicadorNegativar','indicadorNovaDataVencimento','indicadorProtestar','indicadorSustacaoProtesto','indicadorNovoValorNominal'];$data=['numeroConvenio'=>(int)$agreement]+array_fill_keys($flags,'N');$data['indicadorAlterarPrazoBoletoVencido']='S';$data['alteracaoPrazo']=['quantidadeDiasAceite'=>(int)$intent['days']];return json_encode($data,JSON_THROW_ON_ERROR);
    }
    public static function fingerprint(array$origin,array$snapshot,array$intent):string{return hash('sha256',json_encode([self::CONTRACT,$origin['payload_hash'],$snapshot['api_stage'],$snapshot['profile_hash'],self::input($intent)],JSON_THROW_ON_ERROR));}
    public static function acknowledged(array$data,array$snapshot,array$intent,string$fingerprint):array
    {
        $date=$data['dataAtualizacao']??null;$time=$data['horarioAtualizacao']??null;if(($data['numeroContratoCobranca']??null)!==($snapshot['accepted_contract']??null)||!is_string($date)||!preg_match('/^([0-9]{2})[.\/]([0-9]{2})[.\/]([0-9]{4})$/D',$date,$m)||!is_string($time)||!preg_match('/^(?:[01][0-9]|2[0-3]):[0-5][0-9]:[0-5][0-9]$/D',$time))throw new \DomainException('bb_api_receipt_window_response_invalid');Fields::date($m[3].'-'.$m[2].'-'.$m[1]);return['numeroContratoCobranca'=>$data['numeroContratoCobranca'],'dataAtualizacao'=>$date,'horarioAtualizacao'=>$time,'bank_state'=>'window_requested','window_instruction'=>self::input($intent),'window_contract'=>self::CONTRACT,'window_fingerprint'=>$fingerprint];
    }
}
