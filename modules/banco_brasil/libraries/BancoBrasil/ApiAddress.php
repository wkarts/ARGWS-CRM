<?php
declare(strict_types=1);
namespace BancoBrasil;
require_once __DIR__.'/Fields.php';
/** Operational bank title address; never replaces the fiscal recipient identity/address snapshot. */
final class ApiAddress
{
    public const CONTRACT='bb_api_registered_address_patch_v1';
    private const ADDRESS=['address'=>60,'district'=>30,'city'=>30,'state'=>2,'zip'=>8];
    public static function address(array$input):array
    {
        if(array_diff(array_keys($input),array_keys(self::ADDRESS))||array_diff(array_keys(self::ADDRESS),array_keys($input)))throw new \DomainException('bb_api_address_invalid');$out=[];
        foreach(self::ADDRESS as$field=>$length){$value=$input[$field];if(!is_string($value))throw new \DomainException('bb_api_address_invalid');if($field==='zip'){if(!preg_match('/^[0-9]{8}$/D',$value)||$value==='00000000')throw new \DomainException('bb_api_address_invalid');}elseif($field==='state'){if(!in_array($value,['AC','AL','AP','AM','BA','CE','DF','ES','GO','MA','MT','MS','MG','PA','PB','PR','PE','PI','RJ','RN','RS','RO','RR','SC','SP','SE','TO'],true))throw new \DomainException('bb_api_address_invalid');}else{if($value===''||trim($value)!==$value||strlen($value)>$length||preg_match('/[\x00-\x1f\x7f]/',$value)||preg_match('//u',$value)!==1)throw new \DomainException('bb_api_address_invalid');}$out[$field]=$value;}return$out;
    }
    public static function input(array$input):array
    {
        $keys=array_merge(array_keys(self::ADDRESS),['contract_confirmed','reference','reason']);if(array_diff(array_keys($input),$keys)||array_diff($keys,array_keys($input))||($input['contract_confirmed']??null)!=='1')throw new \DomainException('bb_api_address_contract_required');$out=self::address(array_intersect_key($input,self::ADDRESS))+['contract_confirmed'=>'1'];foreach(['reference'=>191,'reason'=>500]as$field=>$max){$value=$input[$field];if(!is_string($value)||trim($value)===''||strlen(trim($value))>$max||preg_match('/[\x00-\x1f\x7f]/',$value)||preg_match('//u',$value)!==1)throw new \DomainException('bb_api_address_invalid');$out[$field]=trim($value);}return$out;
    }
    public static function observed(array$detail):?array
    {
        $map=['address'=>'textoEnderecoSacadoCobranca','district'=>'nomeBairroSacadoCobranca','city'=>'nomeMunicipioSacadoCobranca','state'=>'siglaUnidadeFederacaoSacadoCobranca','zip'=>'numeroCepSacadoCobranca'];$out=[];
        foreach($map as$field=>$source){if(!is_string($detail[$source]??null))return null;$value=$detail[$source];if($field==='zip'){if(!preg_match('/^[0-9]{1,8}(?:\.0+)?$/D',$value))return null;$value=str_pad(explode('.',$value)[0],8,'0',STR_PAD_LEFT);}$out[$field]=$value;}
        try{return self::address($out);}catch(\Throwable$error){return null;}
    }
    public static function addressHash(array$address):string{return hash('sha256',json_encode(self::address(array_intersect_key($address,self::ADDRESS)),JSON_THROW_ON_ERROR));}
    public static function fingerprint(array$origin,array$snapshot,array$intent):string{return hash('sha256',json_encode([self::CONTRACT,$origin['payload_hash'],$snapshot['api_stage'],$snapshot['profile_hash'],self::input($intent)],JSON_THROW_ON_ERROR));}
    public static function payload(string$agreement,array$intent):string
    {
        $intent=self::input($intent);if(!preg_match('/^[1-9][0-9]{6}$/D',$agreement))throw new \DomainException('bb_api_origin_invalid');$flags=['indicadorAlterarAbatimento','indicadorAlterarDataDesconto','indicadorAlterarDesconto','indicadorAlterarEnderecoPagador','indicadorAlterarPrazoBoletoVencido','indicadorAlterarSeuNumero','indicadorAtribuirDesconto','indicadorCancelarProtesto','indicadorCobrarJuros','indicadorCobrarMulta','indicadorDispensarJuros','indicadorDispensarMulta','indicadorIncluirAbatimento','indicadorNegativar','indicadorNovaDataVencimento','indicadorProtestar','indicadorSustacaoProtesto','indicadorNovoValorNominal'];$data=['numeroConvenio'=>(int)$agreement]+array_fill_keys($flags,'N');$data['indicadorAlterarEnderecoPagador']='S';$data['alteracaoEndereco']=['enderecoPagador'=>$intent['address'],'bairroPagador'=>$intent['district'],'cidadePagador'=>$intent['city'],'UFPagador'=>$intent['state'],'CEPPagador'=>(int)$intent['zip']];return json_encode($data,JSON_THROW_ON_ERROR);
    }
    public static function acknowledged(array$data,array$snapshot,array$intent,string$fingerprint):array
    {
        $contract=$data['numeroContratoCobranca']??null;$date=$data['dataAtualizacao']??null;$time=$data['horarioAtualizacao']??null;if(!is_string($contract)||$contract!==($snapshot['accepted_contract']??null)||!is_string($date)||!preg_match('/^([0-9]{2})[.\/]([0-9]{2})[.\/]([0-9]{4})$/D',$date,$m)||!is_string($time)||!preg_match('/^(?:[01][0-9]|2[0-3]):[0-5][0-9]:[0-5][0-9]$/D',$time))throw new \DomainException('bb_api_address_response_invalid');Fields::date($m[3].'-'.$m[2].'-'.$m[1]);return['numeroContratoCobranca'=>$contract,'dataAtualizacao'=>$date,'horarioAtualizacao'=>$time,'bank_state'=>'address_requested','address_instruction'=>self::input($intent),'address_contract'=>self::CONTRACT,'address_fingerprint'=>$fingerprint];
    }
}
