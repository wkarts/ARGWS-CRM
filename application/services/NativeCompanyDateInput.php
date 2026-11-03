<?php

declare(strict_types=1);
require_once __DIR__.'/CompanySettingsFormats.php';
require_once __DIR__.'/CompanySettingsStorage.php';
/** Native date/time formatting with explicit issuer settings and no global hooks. */
final class NativeCompanyDateInput
{
    private string $format;
    private string $dateFormat;
    public function __construct($db,string $p,int $issuer)
    {
        $store=new CompanySettingsStorage($db,$p);$settings=['dateformat'=>$store->get($issuer,'dateformat'),'time_format'=>$store->get($issuer,'time_format')];CompanySettingsFormats::validate($settings);
        $this->dateFormat=CompanySettingsFormats::dates()[$settings['dateformat']];
        $this->format=$this->dateFormat.($settings['time_format']==='24'?' H:i':' g:i A');
    }
    public function parse($value):string
    {
        if(!is_string($value)||strlen($value)>40)throw new DomainException('company_auxiliary_invalid_input');
        foreach([$this->format,$this->format==='Y-m-d H:i'?'Y-m-d H:i:s':$this->format]as$format){
            $date=DateTimeImmutable::createFromFormat('!'.$format,$value);
            if($date&&$date->format($format)===$value)return$date->format('Y-m-d H:i:s');
        }
        throw new DomainException('company_auxiliary_invalid_input');
    }
    public function displayDate(string $value):string{
        $date=DateTimeImmutable::createFromFormat('!Y-m-d',$value);if(!$date||$date->format('Y-m-d')!==$value)throw new DomainException('company_auxiliary_invalid_input');return$date->format($this->dateFormat);
    }
    public function display(string $value):string
    {
        $date=DateTimeImmutable::createFromFormat('!Y-m-d H:i:s',$value);
        if(!$date||$date->format('Y-m-d H:i:s')!==$value)throw new DomainException('company_auxiliary_invalid_input');
        return$date->format($this->format);
    }
}
