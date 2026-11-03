<?php
declare(strict_types=1);
require_once __DIR__.'/NativeCompanyTicketCatalog.php';
/** Persisted schema-version boundary for registry/wizard writes; never infer readiness from missing tables. */
final class NativeCompanyTicketBootstrap
{
    private static function version($db,string $p,bool $lock=false):int
    {
        if(!preg_match('/^[A-Za-z0-9_]+$/D',$p))throw new InvalidArgumentException('Invalid prefix');
        $rows=IssuerStorage::query($db,'SELECT version FROM '.$p.'migrations'.($lock?' FOR UPDATE':''))->result_array();
        if(count($rows)!==1||!isset($rows[0]['version'])||(!is_int($rows[0]['version'])&&!is_string($rows[0]['version']))||!preg_match('/^[1-9][0-9]{0,8}$/D',(string)$rows[0]['version']))throw new DomainException('native_ticket_core_version_invalid');
        return(int)$rows[0]['version'];
    }
    public static function plan($db,string $p):array
    {
        $version=self::version($db,$p);$tables=['migrations'];
        if($version>=474){NativeCompanyTicketCatalogSchema::assertValid($db,$p);$tables=array_merge($tables,array_keys(NativeCompanyTicketCatalogSchema::definitions($p)));}
        return['version'=>$version,'tables'=>$tables];
    }
    public static function confirmInOwnedTransaction($db,string $p,array $plan):void
    {
        if(array_keys($plan)!==['version','tables']||!is_int($plan['version'])||!is_array($plan['tables']))throw new DomainException('native_ticket_core_version_invalid');
        IssuerTransaction::assertOwned($db,$p,array_merge(['migrations','issuers_registry','issuers_installation_identity'],$plan['tables']));
        if(self::version($db,$p,true)!==$plan['version'])throw new DomainException('native_ticket_core_version_changed');
    }
    public static function initializeInOwnedTransaction($db,string $p,int $issuer,int $actor,array $plan):void
    {
        self::confirmInOwnedTransaction($db,$p,$plan);
        if($plan['version']>=474){NativeCompanyTicketCatalogSchema::assertValid($db,$p);NativeCompanyTicketCatalog::initializeInOwnedTransaction($db,$p,$issuer,$actor);}
    }
}
