<?php

declare(strict_types=1);
require_once __DIR__.'/CompanyStandaloneBillingSchema.php';require_once __DIR__.'/CompanyTaskTimeBatchFence.php';
/** Shared mutation fence for an explicitly invoiced standalone task. */
final class CompanyStandaloneBillingFence
{
    public static function tables($db,string $p):array
    {
        $extra=CompanyTaskTimeBatchFence::tables($db,$p);if(!$db->table_exists($p.'company_standalone_billing_links')&&!$db->table_exists($p.'company_standalone_billing_origins'))return $extra;CompanyStandaloneBillingSchema::assertValid($db,$p);return array_merge(['issuers_lock','company_standalone_tasks','issuers_registry','issuers_staff','issuers_resource_permissions','issuers_installation_identity','staff','company_standalone_billing_origins','company_standalone_billing_links','company_invoice_requests'], $extra);
    }
    public static function stateInOwned($db,string $p,int $issuer,int $actor,int $task):?string
    {
        $tables=self::tables($db,$p);$partial=CompanyTaskTimeBatchFence::stateInOwned($db,$p,'standalone',$issuer,$task);if(!$tables)return $partial;IssuerTransaction::assertOwned($db,$p,$tables);$acl=new IssuerResourceAccess($db,$p);if(!$acl->can($issuer,$actor,'tasks','view')||!IssuerStorage::query($db,'SELECT id FROM '.$p.'company_standalone_tasks WHERE issuer_id=? AND id=?',[$issuer,$task])->row_array())throw new DomainException('company_standalone_billing_denied');$r=IssuerStorage::query($db,'SELECT state FROM '.$p.'company_standalone_billing_links WHERE issuer_id=? AND task_id=? FOR UPDATE',[$issuer,$task])->row_array();$receipt=IssuerStorage::query($db,'SELECT issuer_id,request_key FROM '.$p.'company_invoice_requests WHERE request_key=? FOR UPDATE',[self::key($issuer,$task)])->row_array();
        if(($r&&!in_array($r['state'],['ready','converting','converted','disabled'],true))||($receipt&&(int)$receipt['issuer_id']!==$issuer))throw new RuntimeException('issuer_standalone_billing_unconfirmed');if($receipt)return $r&&$r['state']==='converted'?'converted':'converting';return $partial??($r['state']??null);
    }
    public static function assertAvailableInOwned($db,string $p,int $issuer,int $actor,int $task):void
    {
        if(in_array(self::stateInOwned($db,$p,$issuer,$actor,$task),['converting','converted'],true))throw new DomainException('company_standalone_billing_immutable');
    }
    public static function key(int $issuer,int $task):string{return hash('sha256','company-standalone-task-invoice:'.$issuer.':'.$task);}
}
