<?php

declare(strict_types=1);
require_once __DIR__.'/CompanySettingsCatalog.php';
require_once __DIR__.'/SalesNumberPresentation.php';

/** Public document preferences only. Invoice conversion identity/transport remain independent. */
final class CompanySalesPreferences
{
    public static function capture(CompanySettingsStorage $store,int $issuer,string $kind,bool $legacy=false):array
    {
        $type=match($kind){'estimates'=>'estimate','proposals'=>'proposal',default=>throw new DomainException('company_sales_invalid')};
        $prefix=$type==='estimate'?'estimate_prefix':'proposal_number_prefix';$terms='predefined_terms_'.$type;$values=[];
        foreach([$prefix,$terms] as $field)$values[$field]=$store->exists($issuer,$field)?$store->get($issuer,$field):CompanySettingsCatalog::defaultValue('sales_documents',$field);
        $values=CompanySettingsCatalog::prepare('sales_documents',$values);
        $profile=$store->documentTypography($issuer,$kind);
        $base=['format'=>$profile===null?2:3,'kind'=>$kind,'prefix'=>$values[$prefix],'terms'=>$values[$terms],'pdf'=>CompanyDocumentPdf::capture($store,$issuer,$kind)];
        if(!$legacy&&$kind==='estimates'&&$store->exists($issuer,'estimate_number_format')){
            $format=$store->get($issuer,'estimate_number_format');
            $padding=$store->exists($issuer,'number_padding_prefixes')?$store->get($issuer,'number_padding_prefixes'):'6';
            if(!in_array($format,['1','2','3','4'],true)||!preg_match('/^(?:0|[1-9][0-9]?)$/D',$padding))throw new DomainException('company_sales_invalid');
            $base['format']=$profile===null?4:5;
            $base+=['number_format'=>$format,'padding'=>$padding];
        }
        $layout=CompanyPdfLayout::capture($store,$issuer,$kind);
        return $base+($profile===null?[]:['typography'=>$profile])+($layout===null?[]:['layout'=>$layout]);
    }
    public static function presentation(string $kind,$number,array $context,string $legacyPrefix='',?string $documentDate=null):array
    {
        if(!in_array($kind,['estimates','proposals'],true)||(!is_int($number)&&!is_string($number))||!preg_match('/^[1-9][0-9]{0,9}$/D',(string)$number)||(int)$number<1||(int)$number>2147483647)throw new DomainException('company_sales_invalid');
        $prefix=$legacyPrefix;$terms=$context['identity']['terms']??'';
        if(array_key_exists('sales_preferences',$context)){
            $prefs=$context['sales_preferences'];
            if(!is_array($prefs)||!in_array($prefs['format']??null,[1,2,3,4,5],true))throw new DomainException('company_sales_invalid');
            $keys=match($prefs['format']){
                1=>['format','kind','prefix','terms'],2=>['format','kind','prefix','terms','pdf'],
                3=>['format','kind','prefix','terms','pdf','typography'],
                4=>['format','kind','prefix','terms','pdf','number_format','padding'],
                5=>['format','kind','prefix','terms','pdf','number_format','padding','typography'],
            };
            if(array_key_exists('layout',$prefs)){$keys[]='layout';CompanyPdfLayout::validate($prefs['layout']);}
            if(array_keys($prefs)!==$keys||$prefs['kind']!==$kind||!is_string($prefs['prefix'])||!is_string($prefs['terms']))throw new DomainException('company_sales_invalid');
            if($prefs['format']>=2)CompanyDocumentPdf::validate($prefs['pdf']);
            if(in_array($prefs['format'],[3,5],true))CompanyPdfTypography::validate($prefs['typography']);
            if($prefs['format']>=4){
                if($kind!=='estimates'||!is_string($prefs['number_format'])||!in_array($prefs['number_format'],['1','2','3','4'],true)||!is_string($prefs['padding'])||!preg_match('/^(?:0|[1-9][0-9]?)$/D',$prefs['padding'])||!is_string($documentDate))throw new DomainException('company_sales_invalid');
                $date=DateTimeImmutable::createFromFormat('!Y-m-d',$documentDate,new DateTimeZone('UTC'));
                if(!$date||$date->format('Y-m-d')!==$documentDate)throw new DomainException('company_sales_invalid');
            }
            $type=$kind==='estimates'?'estimate':'proposal';CompanySettingsCatalog::prepare('sales_documents',[($type==='estimate'?'estimate_prefix':'proposal_number_prefix')=>$prefs['prefix'],'predefined_terms_'.$type=>$prefs['terms']]);$prefix=$prefs['prefix'];$terms=$prefs['terms'];
        }
        if(!is_string($terms))throw new DomainException('company_sales_invalid');
        $formatted=$prefix.str_pad((string)(int)$number,6,'0',STR_PAD_LEFT);
        if(($context['sales_preferences']['format']??0)>=4){$prefs=$context['sales_preferences'];$formatted=SalesNumberPresentation::format((string)(int)$number,$prefs['number_format'],$prefix,$documentDate,(int)$prefs['padding']);}
        return ['formatted_number'=>$formatted,'terms'=>$terms];
    }
    public static function context(string $json,string $hash,int $issuer,?int $client=null):array
    {
        try{$context=json_decode($json,true,32,JSON_THROW_ON_ERROR);}catch(\Throwable $error){throw new DomainException('company_sales_invalid');}
        if(!is_array($context)||!hash_equals($hash,hash('sha256',json_encode($context,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)))||(int)($context['issuer_id']??0)!==$issuer||($client!==null&&(int)($context['recipient']['client_id']??0)!==$client))throw new DomainException('company_sales_invalid');
        return $context;
    }
}
