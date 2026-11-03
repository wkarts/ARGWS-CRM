<?php
declare(strict_types=1);
require_once __DIR__.'/CompanySettingsStorage.php';
require_once __DIR__.'/CompanySettingsCatalog.php';
require_once __DIR__.'/CompanySettingsFormats.php';
require_once __DIR__.'/issuers/IssuerResourceAccess.php';

/** Native date-field behavior using the selected issuer's public settings, without date hooks. */
final class NativeCompanyInvoiceDates
{
    public function __construct(private $db, private string $prefix) {}

    public static function calculate($date, $days, $format): string
    {
        if (!is_string($date) || strlen($date)>10 || !is_string($days)
            || !preg_match('/^(?:0|[1-9][0-9]{0,3})$/D',$days)
            || !is_string($format) || !isset(CompanySettingsFormats::dates()[$format])) {
            throw new DomainException('native_company_invoice_date_invalid');
        }
        // Native zero disables due-date suggestion; do not replace an explicit zero with a default.
        if ($days==='0') { return ''; }
        $presentation=CompanySettingsFormats::dates()[$format];
        $inputFormat=preg_match('/^[0-9]{4}-[0-9]{2}-[0-9]{2}$/D',$date)?'Y-m-d':$presentation;
        $parsed=DateTimeImmutable::createFromFormat('!'.$inputFormat,$date,new DateTimeZone('UTC'));
        if (!$parsed || $parsed->format($inputFormat)!==$date) {
            throw new DomainException('native_company_invoice_date_invalid');
        }
        $result=$parsed->add(new DateInterval('P'.$days.'D'));
        if ((int)$result->format('Y')>9999) { throw new DomainException('native_company_invoice_date_invalid'); }
        return $result->format($presentation);
    }

    public function dueDate($issuer, $actor, $date): string
    {
        $issuer=IssuerPolicy::id($issuer); $actor=IssuerPolicy::id($actor);
        $acl=new IssuerResourceAccess($this->db,$this->prefix);
        $authorize=static function()use($acl,$issuer,$actor):void {
            if (!$acl->can($issuer,$actor,'invoices','view')
                || (!$acl->can($issuer,$actor,'invoices','create')&&!$acl->can($issuer,$actor,'invoices','edit'))) {
                throw new DomainException('company_invoice_access_denied');
            }
        };
        $authorize();
        $store=new CompanySettingsStorage($this->db,$this->prefix);
        $read=static fn($key)=>$store->exists($issuer,$key)?$store->get($issuer,$key):CompanySettingsCatalog::defaultValue($key==='dateformat'?'localization':'documents',$key);
        $days=$read('invoice_due_after'); $format=$read('dateformat');
        $result=self::calculate($date,$days,$format);
        $authorize();
        if ($days!==$read('invoice_due_after')||$format!==$read('dateformat')) {
            throw new DomainException('native_company_invoice_date_changed');
        }
        return $result;
    }
}
