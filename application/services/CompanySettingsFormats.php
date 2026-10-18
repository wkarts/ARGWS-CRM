<?php
declare(strict_types=1);
/** Public formatting catalog only. No company lookup, hooks, credentials or timezone mutation. */
final class CompanySettingsFormats
{
    public static function dates(): array
    {
        return ['d-m-Y|%d-%m-%Y'=>'d-m-Y','d/m/Y|%d/%m/%Y'=>'d/m/Y','m-d-Y|%m-%d-%Y'=>'m-d-Y','m.d.Y|%m.%d.%Y'=>'m.d.Y','m/d/Y|%m/%d/%Y'=>'m/d/Y','Y-m-d|%Y-%m-%d'=>'Y-m-d','d.m.Y|%d.%m.%Y'=>'d.m.Y'];
    }
    public static function validate(array $settings): void
    {
        if (array_diff(array_keys($settings), ['dateformat','time_format']) || !isset($settings['dateformat'],$settings['time_format'])
            || !is_string($settings['dateformat']) || !array_key_exists($settings['dateformat'],self::dates())
            || !in_array($settings['time_format'],['12','24'],true)) throw new DomainException('company_settings_invalid_input');
    }
}
