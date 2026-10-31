<?php
declare(strict_types=1);
require_once __DIR__.'/CompanySettingsStorage.php';
/** Original public creation preference, not a financial classification of existing tasks. */
final class NativeCompanyTaskFormPreferences
{
    public const BILLABLE='task_biillable_checked_on_creation';
    public static function prepare(array$values):array
    {
        if(array_keys($values)!==[self::BILLABLE]||!in_array($values[self::BILLABLE],['0','1'],true))throw new DomainException('native_task_form_preference_invalid');return$values;
    }
    public static function read($db,string$p,int$i,int$a):array
    {
        $acl=new IssuerResourceAccess($db,$p);if(!$acl->member($i,$a))throw new DomainException('native_task_form_denied');$storage=new CompanySettingsStorage($db,$p);
        // Public native seed option215 is 1. An existing explicit 0 is never absence.
        $values=[self::BILLABLE=>$storage->exists($i,self::BILLABLE)?$storage->get($i,self::BILLABLE):'1'];self::prepare($values);if(!$acl->member($i,$a))throw new DomainException('native_task_form_denied');return$values;
    }
}
