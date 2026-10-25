<?php

declare(strict_types=1);
require_once __DIR__.'/CompanySettingsCatalog.php';
require_once __DIR__.'/CompanySettingsStorage.php';

/** Native date formatting with explicit company options, without application hooks. */
final class NativeCompanyFormatting
{
    public function __construct(private string $dateFormat,private string $timeFormat)
    {
        if(!in_array($dateFormat,array_values(CompanySettingsFormats::dates()),true)||!in_array($timeFormat,['12','24'],true))throw new DomainException('native_format_invalid');
    }
    public static function current():?self
    {
        if(!class_exists('CompanySettingsRuntime',false)||!CompanySettingsRuntime::active())return null;
        $ci=&get_instance();$issuer=CompanySettingsRuntime::issuerId();$actor=CompanySettingsRuntime::staffId();
        if(!(new IssuerResourceAccess($ci->db,db_prefix()))->member($issuer,$actor))throw new DomainException('native_format_denied');
        $store=new CompanySettingsStorage($ci->db,db_prefix());$values=[];foreach(['dateformat','time_format']as$key)$values[$key]=$store->exists($issuer,$key)?$store->get($issuer,$key):CompanySettingsCatalog::defaultValue('localization',$key);
        CompanySettingsFormats::validate($values);return new self(CompanySettingsFormats::dates()[$values['dateformat']],$values['time_format']);
    }
    /** Public formatting uses a sealed issuer request, never a fabricated staff identity. */
    public static function portal($ci):self
    {
        if(!class_exists('PortalRequestScope',false)||!PortalRequestScope::sealedFor($ci))throw new DomainException('native_format_denied');
        $issuer=PortalRequestScope::issuerId();$p=db_prefix();$check=static function()use($ci,$p,$issuer){if(!IssuerStorage::query($ci->db,'SELECT id FROM '.$p.'issuers_registry WHERE id=? AND active=1',[$issuer])->row_array())throw new DomainException('native_format_denied');};$check();
        $store=new CompanySettingsStorage($ci->db,$p);$values=[];foreach(['dateformat','time_format']as$key)$values[$key]=$store->exists($issuer,$key)?$store->get($issuer,$key):CompanySettingsCatalog::defaultValue('localization',$key);
        CompanySettingsFormats::validate($values);$check();return new self(CompanySettingsFormats::dates()[$values['dateformat']],$values['time_format']);
    }
    private static function parse(string $value,string $format):?DateTimeImmutable
    {
        $parsed=DateTimeImmutable::createFromFormat('!'.$format,$value);
        return $parsed&&$parsed->format($format)===$value?$parsed:null;
    }
    public function date($value):string
    {
        if($value===null||$value===''||$value==='0000-00-00')return'';
        if(!is_string($value))throw new DomainException('native_date_invalid');
        if(str_contains($value,' '))return$this->datetime($value);
        $date=self::parse($value,'Y-m-d');if(!$date)throw new DomainException('native_date_invalid');return$date->format($this->dateFormat);
    }
    public function datetime($value,bool $timesheet=false):string
    {
        if($value===null||$value===''||$value==='0000-00-00 00:00:00')return'';
        if($timesheet&&(is_int($value)||(is_string($value)&&preg_match('/^[0-9]{1,11}$/D',$value)))){$date=(new DateTimeImmutable('@'.$value))->setTimezone(new DateTimeZone(date_default_timezone_get()));}
        else{if(!is_string($value))throw new DomainException('native_date_invalid');$date=self::parse($value,'Y-m-d H:i:s')??self::parse($value,'Y-m-d H:i');if(!$date)throw new DomainException('native_date_invalid');}
        return$date->format($this->dateFormat.' '.($this->timeFormat==='12'?'g:i A':($timesheet?'H:i':'H:i:s')));
    }
    public function toSql($value,bool $datetime=false):?string
    {
        if($value===null||$value==='')return null;if(!is_string($value))throw new DomainException('native_date_invalid');
        if(!$datetime){$date=self::parse($value,'Y-m-d')??self::parse($value,$this->dateFormat);if(!$date)throw new DomainException('native_date_invalid');return$date->format('Y-m-d');}
        $formats=['Y-m-d H:i:s','Y-m-d H:i',$this->dateFormat.' H:i:s',$this->dateFormat.' H:i',$this->dateFormat.' g:i A',$this->dateFormat,'Y-m-d'];
        foreach(array_unique($formats)as$format){$date=self::parse($value,$format);if($date)return$date->format('Y-m-d H:i:s');}throw new DomainException('native_date_invalid');
    }
}
