<?php
declare(strict_types=1);
require_once __DIR__.'/NativeCompanyTicketCatalog.php';
/** Explicit migration-only public defaults, atomic across the existing issuer census. No GET initialization. */
final class NativeCompanyTicketCatalogBackfill
{
    public static function tables():array{return ['issuers_lock','issuers_registry','issuers_installation_identity',NativeCompanyTicketCatalogSchema::CATALOG];}
    public static function run($db,string $p):array
    {
        if(!preg_match('/^[A-Za-z0-9_]+$/D',$p))throw new InvalidArgumentException('Invalid prefix');
        NativeCompanyTicketCatalogSchema::assertValid($db,$p);
        return IssuerTransaction::run($db,$p,self::tables(),function()use($db,$p){
            NativeCompanyTicketCatalogSchema::assertValid($db,$p);
            $identity=IssuerStorage::query($db,'SELECT id,primary_issuer_id FROM '.$p.'issuers_installation_identity ORDER BY id FOR UPDATE')->result_array();
            if(count($identity)!==1||(int)$identity[0]['id']!==1)throw new DomainException('native_ticket_backfill_identity_invalid');
            $primary=$identity[0]['primary_issuer_id']===null?null:IssuerPolicy::id($identity[0]['primary_issuer_id']);
            $issuers=IssuerStorage::query($db,'SELECT id,active FROM '.$p.'issuers_registry ORDER BY id FOR UPDATE')->result_array();$ids=[];
            foreach($issuers as$row){$id=IssuerPolicy::id($row['id']);if(isset($ids[$id])||!in_array((string)$row['active'],['0','1'],true))throw new DomainException('native_ticket_backfill_issuer_invalid');$ids[$id]=true;}
            if($primary!==null&&!isset($ids[$primary]))throw new DomainException('native_ticket_backfill_identity_invalid');
            $before=IssuerStorage::query($db,'SELECT * FROM '.$p.NativeCompanyTicketCatalogSchema::CATALOG.' ORDER BY id')->result_array();$preserved=[];
            foreach($before as$row){$id=IssuerPolicy::id($row['id']);if(isset($preserved[$id])||!isset($ids[(int)$row['issuer_id']]))throw new DomainException('native_ticket_backfill_catalog_invalid');$preserved[$id]=$row;}
            foreach(array_keys($ids)as$issuer)NativeCompanyTicketCatalog::initializeInOwnedTransaction($db,$p,$issuer,null);
            $after=IssuerStorage::query($db,'SELECT * FROM '.$p.NativeCompanyTicketCatalogSchema::CATALOG.' ORDER BY id')->result_array();$seen=[];
            foreach($after as$row){$id=IssuerPolicy::id($row['id']);$seen[$id]=true;if(isset($preserved[$id])&&$preserved[$id]!==$row)throw new RuntimeException('issuer_ticket_backfill_existing_changed');if(!isset($preserved[$id])&&($row['actor_id']!==null||($primary!==null&&(int)$row['issuer_id']===$primary)))throw new RuntimeException('issuer_ticket_backfill_write_unconfirmed');}
            if(array_diff_key($preserved,$seen))throw new RuntimeException('issuer_ticket_backfill_existing_changed');
            if(IssuerStorage::query($db,'SELECT id,active FROM '.$p.'issuers_registry ORDER BY id')->result_array()!==$issuers||IssuerStorage::query($db,'SELECT id,primary_issuer_id FROM '.$p.'issuers_installation_identity ORDER BY id')->result_array()!==$identity)throw new DomainException('native_ticket_backfill_context_changed');
            NativeCompanyTicketCatalogSchema::assertValid($db,$p);
            return ['issuers'=>count($ids),'principal_skipped'=>$primary!==null,'rows_added'=>count($after)-count($before),'catalog_sha256'=>hash('sha256',json_encode($after,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES))];
        });
    }
}
