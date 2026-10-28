<?php

declare(strict_types=1);
require_once __DIR__.'/NativeCompanySalesSettings.php';
require_once __DIR__.'/NativeCompanyInvoiceDates.php';
require_once __DIR__.'/CompanySettingsCatalog.php';

/** Original estimate/proposal due-date suggestions, read from the active company. */
final class NativeCompanySalesDates
{
    public function __construct(private $db, private string $prefix) {}
    public function dueDate(int $issuer, int $actor, string $kind, $date): string
    {
        if (!in_array($kind,['estimates','proposals'],true)) { throw new DomainException('native_sales_date_invalid'); }
        $acl=new IssuerResourceAccess($this->db,$this->prefix);
        $verify=static function()use($acl,$issuer,$actor,$kind):void {
            if($acl->primaryId()===$issuer || !$acl->can($issuer,$actor,$kind,'view')
                || (!$acl->can($issuer,$actor,$kind,'create') && !$acl->can($issuer,$actor,$kind,'edit'))) {
                throw new DomainException('native_sales_date_denied');
            }
        };
        $verify();
        $store=new CompanySettingsStorage($this->db,$this->prefix);
        $key=$kind==='estimates'?'estimate_due_after':'proposal_due_after';
        $read=static fn($name)=>$store->exists($issuer,$name)?$store->get($issuer,$name):
            ($name==='dateformat'?CompanySettingsCatalog::defaultValue('localization','dateformat'):NativeCompanySalesSettings::defaults($kind)[$name]);
        $days=$read($key);$format=$read('dateformat');
        try { $result=NativeCompanyInvoiceDates::calculate($date,$days,$format); }
        catch(DomainException $error) { throw new DomainException('native_sales_date_invalid'); }
        $verify();
        if($days!==$read($key)||$format!==$read('dateformat'))throw new DomainException('native_sales_date_changed');
        return $result;
    }
}
