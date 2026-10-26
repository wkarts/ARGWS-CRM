<?php
declare(strict_types=1);
require_once __DIR__.'/CompanyCalendarPreferences.php';
/** Read only the public datepicker policy; no calendar/configuration capability required. */
final class NativeCompanyCalendarPresentation
{
    public static function read($db,string $p,int $issuer,int $actor):array
    {
        $acl=new IssuerResourceAccess($db,$p);if(!$acl->member($issuer,$actor))throw new DomainException('native_calendar_presentation_denied');$primary=$acl->primaryId();
        $tables=(new CompanyCalendarPreferences($db,$p))->tables();if($primary===$issuer)$tables[]='options';
        return IssuerTransaction::run($db,$p,$tables,static function()use($db,$p,$issuer,$actor,$acl,$primary){
            $acl->lockFinancialIdentity($issuer,$actor);if(!$acl->member($issuer,$actor)||$acl->primaryId()!==$primary)throw new DomainException('native_calendar_presentation_denied');
            $snapshot=CompanyCalendarPreferences::readInOwnedTransaction($db,$p,$issuer);
            $value=$snapshot['values']['calendar_first_day'];$source=$snapshot['revision']>0?'owned_override':'owned_default';
            if($primary===$issuer){
                $rows=IssuerStorage::query($db,'SELECT value FROM '.$p.'options WHERE name=?',['calendar_first_day'])->result_array();
                if(count($rows)>1)throw new DomainException('native_calendar_presentation_invalid');
                $value=$rows?$rows[0]['value']:'0';$source=$rows?'principal_legacy':'principal_default';
            }
            if(!is_string($value)||!preg_match('/^[0-6]$/D',$value))throw new DomainException('native_calendar_presentation_invalid');
            CompanyCalendarPreferences::confirmInOwnedTransaction($db,$p,$issuer,$snapshot);
            if(!$acl->member($issuer,$actor)||$acl->primaryId()!==$primary)throw new DomainException('native_calendar_presentation_denied');
            return ['calendar_first_day'=>$value,'source'=>$source,'revision'=>$snapshot['revision']];
        });
    }
}
