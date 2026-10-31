<?php

declare(strict_types=1);
require_once __DIR__.'/CompanyPdfTypography.php';
require_once __DIR__.'/CompanyPdfLayout.php';

/** Exact native sales PDF formats, without global options, hooks, file names or custom dimensions. */
final class CompanyDocumentPdf
{
    public static function choices(): array
    {
        return ['A4-PORTRAIT'=>'A4 — Retrato','A4-LANDSCAPE'=>'A4 — Paisagem',
            'LETTER-PORTRAIT'=>'Carta — Retrato','LETTER-LANDSCAPE'=>'Carta — Paisagem'];
    }
    public static function validate($value): string
    {
        if (!is_string($value) || !isset(self::choices()[$value])) {
            throw new DomainException('company_settings_invalid_input');
        }
        return $value;
    }
    public static function capture(CompanySettingsStorage $store,int $issuer,string $kind): string
    {
        $field=match($kind){'estimates'=>'pdf_format_estimate','proposals'=>'pdf_format_proposal','invoices'=>'pdf_format_invoice','creditnotes'=>'pdf_format_credit_note','contracts'=>'pdf_format_contract',default=>throw new DomainException('company_sales_invalid')};
        return self::validate($store->exists($issuer,$field)?$store->get($issuer,$field):'A4-PORTRAIT');
    }
    /** Historical snapshots without version 2 retain their original A4 portrait renderer. */
    public static function specification(array $context): array
    {
        $prefs=$context['sales_preferences']??null;
        $choice=is_array($prefs)&&in_array($prefs['format']??null,[2,3,4,5],true) ? self::validate($prefs['pdf']??null) : 'A4-PORTRAIT';
        return self::choiceSpecification($choice);
    }
    public static function typography(array $context):array
    {
        $prefs=$context['sales_preferences']??null;
        return is_array($prefs)&&in_array($prefs['format']??null,[3,5],true)?CompanyPdfTypography::validate($prefs['typography']??null):CompanyPdfTypography::historical('estimates');
    }
    public static function layout(array $context,string $kind):array
    {
        $prefs=$context['sales_preferences']??null;
        return is_array($prefs)&&array_key_exists('layout',$prefs)?CompanyPdfLayout::validate($prefs['layout']):CompanyPdfLayout::historical($kind);
    }
    public static function choiceSpecification(string $choice):array
    {
        self::validate($choice);
        [$format,$orientation]=explode('-', $choice);
        return ['orientation'=>$orientation==='PORTRAIT'?'P':'L','format'=>$format];
    }
}
