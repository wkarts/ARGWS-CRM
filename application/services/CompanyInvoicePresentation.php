<?php

declare(strict_types=1);
require_once __DIR__.'/CompanyDocumentPdf.php';

/** Known optional PDF snapshot only; never supplies identity, authority or a current setting at read time. */
final class CompanyInvoicePresentation
{
    public static function capture(CompanySettingsStorage $store,int $issuer,string $kind):array
    {
        $profile=$store->documentTypography($issuer,$kind);
        return ['format'=>$profile===null?1:2,'kind'=>$kind,'choice'=>CompanyDocumentPdf::capture($store,$issuer,$kind)]+($profile===null?[]:['typography'=>$profile]);
    }
    public static function validate($value,string $kind):array
    {
        if(!in_array($kind,['invoices','creditnotes','contracts'],true)||!is_array($value)
            ||!in_array($value['format']??null,[1,2],true)||array_keys($value)!==($value['format']===1?['format','kind','choice']:['format','kind','choice','typography'])||$value['kind']!==$kind){
            throw new DomainException('company_document_pdf_invalid');
        }
        try{CompanyDocumentPdf::validate($value['choice']);if($value['format']===2)CompanyPdfTypography::validate($value['typography']);}catch(DomainException $error){throw new DomainException('company_document_pdf_invalid');}
        return $value;
    }
    public static function specification(array $container,string $kind):array
    {
        $choice=array_key_exists('pdf_preferences',$container)?self::validate($container['pdf_preferences'],$kind)['choice']:'A4-PORTRAIT';
        return CompanyDocumentPdf::choiceSpecification($choice);
    }
    public static function typography(array $container,string $kind):array
    {
        $value=array_key_exists('pdf_preferences',$container)?self::validate($container['pdf_preferences'],$kind):null;
        return $value!==null&&$value['format']===2?$value['typography']:CompanyPdfTypography::historical($kind);
    }
    /** Validate first; exclude exactly one presentation key, retaining all unknown/fiscal keys for comparison. */
    public static function fiscalIdentity(array $identity):array
    {
        if(array_key_exists('pdf_preferences',$identity)){self::validate($identity['pdf_preferences'],'invoices');unset($identity['pdf_preferences']);}
        return $identity;
    }
}
