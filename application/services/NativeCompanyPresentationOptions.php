<?php
declare(strict_types=1);
require_once __DIR__.'/CompanySettingsFormats.php';

/** Explicit public presentation allowlist. Never all(), provider settings or credentials. */
final class NativeCompanyPresentationOptions
{
    /** The catalog's public product defaults are not persisted company values. */
    public static function formats(array $captured):array
    {
        if(!class_exists('CompanySettingsCatalog',false))require_once __DIR__.'/CompanySettingsCatalog.php';
        $out=[];foreach(['dateformat','time_format'] as $field)$out[$field]=$captured['values'][$field]??CompanySettingsCatalog::defaultValue('localization',$field);
        CompanySettingsFormats::validate($out);return $out;
    }
    public static function pagination(array $captured):string
    {
        $value=$captured['values']['tables_pagination_limit']??'25'; // Existing native install default, not a persisted override.
        if(!is_string($value)||!preg_match('/^[1-9][0-9]*$/D',$value)||strlen($value)>strlen((string)PHP_INT_MAX)||(strlen($value)===strlen((string)PHP_INT_MAX)&&strcmp($value,(string)PHP_INT_MAX)>0))throw new DomainException('native_company_options_invalid');
        return $value;
    }
    public static function capture(int $issuer,int $actor):array
    {
        if(!class_exists('CompanySettingsRuntime',false)||!CompanySettingsRuntime::active()||CompanySettingsRuntime::issuerId()!==$issuer||CompanySettingsRuntime::staffId()!==$actor)throw new DomainException('native_company_options_context_mismatch');
        $values=[];$present=[];
        foreach(['dateformat','time_format','desktop_notifications','auto_dismiss_desktop_notifications_after','tables_pagination_limit'] as $name){
            $present[$name]=CompanySettingsRuntime::exists($name);
            $values[$name]=$present[$name]?CompanySettingsRuntime::get($name):null;
        }
        if($values['dateformat']!==null&&!array_key_exists($values['dateformat'],CompanySettingsFormats::dates()))throw new DomainException('native_company_options_invalid');
        if($values['time_format']!==null&&!in_array($values['time_format'],['12','24'],true))throw new DomainException('native_company_options_invalid');
        if($values['desktop_notifications']!==null&&!in_array($values['desktop_notifications'],['0','1'],true))throw new DomainException('native_company_options_invalid');
        $delay=$values['auto_dismiss_desktop_notifications_after'];
        if($delay!==null&&(!preg_match('/^(0|[1-9][0-9]{0,4})$/D',$delay)||(int)$delay>86400))throw new DomainException('native_company_options_invalid');
        self::pagination(['values'=>$values]);
        // Absence is explicit, not a manufactured persisted flag or principal-company fallback.
        return ['values'=>$values,'present'=>$present];
    }
}
