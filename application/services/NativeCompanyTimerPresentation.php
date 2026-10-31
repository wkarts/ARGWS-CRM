<?php

declare(strict_types=1);
require_once __DIR__.'/CompanySettingsFormats.php';

/** Pure native _dt(epoch,true) formatting, with explicit settings and no hooks/global option reads. */
final class NativeCompanyTimerPresentation
{
    public static function startedLabel(array $timer,array $formats,string $displayTimezone):string
    {
        CompanySettingsFormats::validate($formats);
        if(!isset($timer['start_time'],$timer['time_basis'])||!is_int($timer['start_time'])||$timer['start_time']<0||!in_array($timer['time_basis'],['runtime_datetime','utc_datetime','unix_epoch'],true))throw new DomainException('native_timer_presentation_invalid');
        if($timer['time_basis']==='runtime_datetime'&&(!isset($timer['storage_timezone'])||!is_string($timer['storage_timezone'])))throw new DomainException('native_timer_presentation_invalid');
        if($timer['time_basis']==='utc_datetime'&&($timer['storage_timezone']??null)!=='UTC')throw new DomainException('native_timer_presentation_invalid');
        try{$zone=new DateTimeZone($displayTimezone);if($timer['time_basis']==='runtime_datetime')new DateTimeZone($timer['storage_timezone']);}catch(Throwable $e){throw new DomainException('native_timer_presentation_invalid');}
        $format=CompanySettingsFormats::dates()[$formats['dateformat']].($formats['time_format']==='24'?' H:i':' g:i A');
        return (new DateTimeImmutable('@'.$timer['start_time']))->setTimezone($zone)->format($format);
    }
}
