<?php

declare(strict_types=1);
require_once __DIR__.'/CompanySettingsCatalog.php';

/** Public document preferences only. Invoice conversion identity/transport remain independent. */
final class CompanySalesPreferences
{
    public static function capture(CompanySettingsStorage $store,int $issuer,string $kind):array
    {
        $type=match($kind){'estimates'=>'estimate','proposals'=>'proposal',default=>throw new DomainException('company_sales_invalid')};
        $prefix=$type==='estimate'?'estimate_prefix':'proposal_number_prefix';$terms='predefined_terms_'.$type;$values=[];
        foreach([$prefix,$terms] as $field)$values[$field]=$store->exists($issuer,$field)?$store->get($issuer,$field):CompanySettingsCatalog::defaultValue('sales_documents',$field);
        $values=CompanySettingsCatalog::prepare('sales_documents',$values);
        $profile=$store->documentTypography($issuer,$kind);
        return ['format'=>$profile===null?2:3,'kind'=>$kind,'prefix'=>$values[$prefix],'terms'=>$values[$terms],'pdf'=>CompanyDocumentPdf::capture($store,$issuer,$kind)]+($profile===null?[]:['typography'=>$profile]);
    }
    public static function presentation(string $kind,$number,array $context,string $legacyPrefix=''):array
    {
        if(!in_array($kind,['estimates','proposals'],true)||(!is_int($number)&&!is_string($number))||!preg_match('/^[1-9][0-9]{0,9}$/D',(string)$number)||(int)$number<1||(int)$number>2147483647)throw new DomainException('company_sales_invalid');
        $prefix=$legacyPrefix;$terms=$context['identity']['terms']??'';
        if(array_key_exists('sales_preferences',$context)){
            $prefs=$context['sales_preferences'];
            if(!is_array($prefs)||!in_array($prefs['format']??null,[1,2,3],true)||array_keys($prefs)!==(($prefs['format']??null)===3?['format','kind','prefix','terms','pdf','typography']:(($prefs['format']??null)===2?['format','kind','prefix','terms','pdf']:['format','kind','prefix','terms']))||$prefs['kind']!==$kind||!is_string($prefs['prefix'])||!is_string($prefs['terms']))throw new DomainException('company_sales_invalid');
            if($prefs['format']>=2)CompanyDocumentPdf::validate($prefs['pdf']);
            if($prefs['format']===3)CompanyPdfTypography::validate($prefs['typography']);
            $type=$kind==='estimates'?'estimate':'proposal';CompanySettingsCatalog::prepare('sales_documents',[($type==='estimate'?'estimate_prefix':'proposal_number_prefix')=>$prefs['prefix'],'predefined_terms_'.$type=>$prefs['terms']]);$prefix=$prefs['prefix'];$terms=$prefs['terms'];
        }
        if(!is_string($terms))throw new DomainException('company_sales_invalid');
        return ['formatted_number'=>$prefix.str_pad((string)(int)$number,6,'0',STR_PAD_LEFT),'terms'=>$terms];
    }
    public static function context(string $json,string $hash,int $issuer,?int $client=null):array
    {
        try{$context=json_decode($json,true,32,JSON_THROW_ON_ERROR);}catch(\Throwable $error){throw new DomainException('company_sales_invalid');}
        if(!is_array($context)||!hash_equals($hash,hash('sha256',json_encode($context,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)))||(int)($context['issuer_id']??0)!==$issuer||($client!==null&&(int)($context['recipient']['client_id']??0)!==$client))throw new DomainException('company_sales_invalid');
        return $context;
    }
}
