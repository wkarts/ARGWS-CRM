<?php

declare(strict_types=1);

/** Card-only edit of the existing account/environment profile, without stale hidden copies. */
final class NativeCompanyAsaasCardPreferences
{
    public static function save($db,string $prefix,int $issuer,int $actor,array $input):void
    {
        foreach(['environment','enabled','profile_revision','account_revision'] as $key) {
            if(!is_string($input[$key]??null))throw new DomainException('asaas_company_profile_invalid');
        }
        if(!in_array($input['enabled'],['0','1'],true)||!in_array($input['environment'],['sandbox','production'],true))throw new DomainException('asaas_company_profile_invalid');
        $service=new \Asaas\CompanyPreferences($db,$prefix);
        $page=$service->page($issuer,$actor);
        $profile=$page['profiles'][$input['environment']]??null;
        if(!$page['ready']||!is_array($profile))throw new DomainException('asaas_company_profile_invalid');
        // saveProfile rechecks ACL/account and the submitted revision under lock.
        // A concurrent general-profile edit cannot be overwritten by this read.
        $values=array_intersect_key($profile,\Asaas\CompanyPreferences::DEFAULTS);
        $values['allow_card']=$input['enabled'];
        $service->saveProfile($issuer,$actor,$input['environment'],$values,$input['profile_revision'],$input['account_revision']);
    }
}
