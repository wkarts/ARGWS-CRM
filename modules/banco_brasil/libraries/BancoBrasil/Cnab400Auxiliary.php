<?php
declare(strict_types=1);
namespace BancoBrasil;
require_once __DIR__.'/LayoutCatalog.php';
/** Position-defined raw observations only. No address/URL/QR lookup or financial authority. */
final class Cnab400Auxiliary
{
    public const CONTRACT='bb_cbr643_raw_aux_v1';
    public static function isAuxiliary(array$event):bool{return($event['event_kind']??null)==='cnab400_raw_auxiliary';}
    private static function raw($value,int$width):string{if(!is_string($value)||strlen($value)!==$width||preg_match('/[^\x20-\x7e]/',$value))throw new \UnexpectedValueException('bb400_auxiliary_raw_invalid');return$value;}
    private static function hash($value):string{if(!is_string($value)||!preg_match('/^[a-f0-9]{64}$/D',$value))throw new \UnexpectedValueException('bb400_auxiliary_hash_invalid');return$value;}
    private static function body(string$service,array$fields):string
    {
        $body='5'.$service.str_repeat(' ',391);
        if($service==='01'){
            if(array_keys($fields)!==['email_slot_raw'])throw new \UnexpectedValueException('bb400_auxiliary_fields_invalid');
            $body=substr_replace($body,'00',3,2);$body=substr_replace($body,self::raw($fields['email_slot_raw'],137),5,137);
        }elseif($service==='10'){
            if(array_keys($fields)!==['occurrence_raw','url_or_key_raw','txid_raw']||!is_string($fields['occurrence_raw'])||!preg_match('/^[0-9]{2}$/D',$fields['occurrence_raw']))throw new \UnexpectedValueException('bb400_auxiliary_fields_invalid');
            $body=substr_replace($body,$fields['occurrence_raw'],3,2);$body=substr_replace($body,self::raw($fields['url_or_key_raw'],77),140,77);$body=substr_replace($body,self::raw($fields['txid_raw'],35),217,35);
        }else throw new \UnexpectedValueException('bb400_auxiliary_service_unsupported');
        return$body;
    }
    private static function semantic(array$e):string{return hash('sha256',json_encode([self::CONTRACT,LayoutCatalog::CNAB400_AUX,$e['scope_key'],$e['parent_event_key'],$e['parent_payload_hash'],'5',$e['service'],$e['raw_fields'],$e['raw_body_hash']],JSON_THROW_ON_ERROR));}
    public static function event(array$parent,string$line,string$fileHash,int$sequence):array
    {
        if(strlen($line)!==400||$line[0]!=='5'||($parent['channel']??null)!=='cnab400'||($parent['layout']??null)!==LayoutCatalog::CNAB400)throw new \UnexpectedValueException('bb400_auxiliary_parent_required');
        $service=substr($line,1,2);
        if($service==='01')$fields=['email_slot_raw'=>substr($line,5,137)];
        elseif($service==='10')$fields=['occurrence_raw'=>substr($line,3,2),'url_or_key_raw'=>substr($line,140,77),'txid_raw'=>substr($line,217,35)];
        else throw new \UnexpectedValueException('bb400_auxiliary_service_unsupported');
        $body=self::body($service,$fields);if(substr($line,0,394)!==$body)throw new \UnexpectedValueException('bb400_auxiliary_reserved_invalid');
        $projection=$parent;unset($projection['file_hash'],$projection['lot'],$projection['sequence']);
        $e=['scope_key'=>$parent['scope_key'],'provider'=>'banco_brasil','channel'=>'cnab400_aux_observation','layout'=>LayoutCatalog::CNAB400_AUX,
            'file_hash'=>$fileHash,'lot'=>0,'sequence'=>$sequence,'our_number'=>$parent['our_number'],'control'=>$parent['control'],
            'occurrence'=>$service,'observation'=>$service==='01'?'aux_email_raw_observed':'aux_pix_raw_observed',
            'event_kind'=>'cnab400_raw_auxiliary','auxiliary_contract'=>self::CONTRACT,'parent_event_key'=>$parent['event_key'],
            'parent_payload_hash'=>hash('sha256',json_encode($projection,JSON_THROW_ON_ERROR)),'parent_occurrence'=>$parent['occurrence'],
            'service'=>$service,'raw_fields'=>$fields,'raw_body_hash'=>hash('sha256',$body),
            'interpretation_limit'=>$service==='01'?'email_range_137_picture_135':'pix_occurrence_range_2_note_3',
            'financial_effect'=>'not_applied','financial_authority'=>'none','requires_review'=>true];
        $e['semantic_hash']=self::semantic($e);$e['event_key']=hash('sha256',self::CONTRACT.':'.$e['semantic_hash']);self::view($e);return$e;
    }
    public static function view(array$e):array
    {
        $allowed=['scope_key','provider','channel','layout','file_hash','lot','sequence','our_number','control','occurrence','observation','event_kind','auxiliary_contract','parent_event_key','parent_payload_hash','parent_occurrence','service','raw_fields','raw_body_hash','interpretation_limit','financial_effect','financial_authority','requires_review','semantic_hash','event_key'];
        if(array_diff(array_keys($e),$allowed)||!self::isAuxiliary($e)||($e['auxiliary_contract']??null)!==self::CONTRACT||($e['provider']??null)!=='banco_brasil'||($e['channel']??null)!=='cnab400_aux_observation'||($e['layout']??null)!==LayoutCatalog::CNAB400_AUX||($e['financial_effect']??null)!=='not_applied'||($e['financial_authority']??null)!=='none'||($e['requires_review']??null)!==true)throw new \UnexpectedValueException('bb400_auxiliary_contract_invalid');
        foreach(['scope_key','parent_event_key','parent_payload_hash','raw_body_hash','semantic_hash','event_key']as$key)self::hash($e[$key]??null);
        if(!is_string($e['our_number']??null)||!preg_match('/^[0-9]{17}$/D',$e['our_number'])||!is_string($e['control']??null)||strlen($e['control'])<1||strlen($e['control'])>25||!preg_match('/^[A-Z0-9 ]+$/D',$e['control'])||!is_string($e['parent_occurrence']??null)||!preg_match('/^[0-9]{2}$/D',$e['parent_occurrence'])||!is_string($e['service']??null)||!is_array($e['raw_fields']??null))throw new \UnexpectedValueException('bb400_auxiliary_parent_invalid');
        $service=$e['service'];$body=self::body($service,$e['raw_fields']);$limit=$service==='01'?'email_range_137_picture_135':'pix_occurrence_range_2_note_3';
        if(($e['occurrence']??null)!==$service||($e['observation']??null)!==($service==='01'?'aux_email_raw_observed':'aux_pix_raw_observed')||($e['interpretation_limit']??null)!==$limit||!hash_equals($e['raw_body_hash'],hash('sha256',$body))||!hash_equals($e['semantic_hash'],self::semantic($e))||!hash_equals($e['event_key'],hash('sha256',self::CONTRACT.':'.$e['semantic_hash'])))throw new \UnexpectedValueException('bb400_auxiliary_corrupt');
        return['contract'=>self::CONTRACT,'service'=>$service,'parent_event_key'=>$e['parent_event_key'],'parent_payload_hash'=>$e['parent_payload_hash'],'parent_occurrence'=>$e['parent_occurrence'],'raw_body_hash'=>$e['raw_body_hash'],'interpretation_limit'=>$limit,'fields'=>$e['raw_fields']];
    }
}
