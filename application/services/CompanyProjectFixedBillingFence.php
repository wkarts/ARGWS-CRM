<?php

declare(strict_types=1);
require_once __DIR__.'/CompanyProjectFixedBillingSchema.php';
/** Shared exclusion between task/time billing and a whole completed project's fixed price. */
final class CompanyProjectFixedBillingFence
{
    public static function tables($db,string $p):array
    {
        if(!$db->table_exists($p.'company_project_fixed_billing_links')&&!$db->table_exists($p.'company_project_fixed_billing_origins'))return [];CompanyProjectFixedBillingSchema::assertValid($db,$p);return ['issuers_lock','company_projects','issuers_registry','issuers_staff','issuers_resource_permissions','issuers_installation_identity','staff','company_project_fixed_billing_origins','company_project_fixed_billing_links','company_invoice_requests'];
    }
    public static function stateInOwned($db,string $p,int $issuer,int $actor,int $project):?string
    {
        $tables=self::tables($db,$p);if(!$tables)return null;IssuerTransaction::assertOwned($db,$p,$tables);$acl=new IssuerResourceAccess($db,$p);if(!$acl->can($issuer,$actor,'tasks','view')||!$acl->can($issuer,$actor,'projects','view')||!IssuerStorage::query($db,'SELECT id FROM '.$p.'company_projects WHERE issuer_id=? AND id=?',[$issuer,$project])->row_array())throw new DomainException('company_project_access_denied');$r=IssuerStorage::query($db,'SELECT state FROM '.$p.'company_project_fixed_billing_links WHERE issuer_id=? AND project_id=? FOR UPDATE',[$issuer,$project])->row_array();$receipt=IssuerStorage::query($db,'SELECT issuer_id,request_key FROM '.$p.'company_invoice_requests WHERE request_key=? FOR UPDATE',[self::key($issuer,$project)])->row_array();
        if(($r&&!in_array($r['state'],['ready','converting','converted','disabled'],true))||($receipt&&(int)$receipt['issuer_id']!==$issuer))throw new RuntimeException('issuer_project_fixed_billing_unconfirmed');if($receipt)return $r&&$r['state']==='converted'?'converted':'converting';return $r['state']??null;
    }
    public static function assertAvailableInOwned($db,string $p,int $issuer,int $actor,int $project):void
    {
        if(in_array(self::stateInOwned($db,$p,$issuer,$actor,$project),['converting','converted'],true))throw new DomainException('company_project_fixed_billing_immutable');
    }
    public static function key(int $issuer,int $project):string{return hash('sha256','company-project-fixed-invoice:'.$issuer.':'.$project);}
}
