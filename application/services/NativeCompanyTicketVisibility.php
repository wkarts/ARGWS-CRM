<?php
declare(strict_types=1);
require_once __DIR__.'/NativeCompanyTicketLifecycleSchema.php';
require_once __DIR__.'/NativeCompanyTicketMergeSchema.php';
/** Versioned, read-only deletion boundary shared by staff, API, publication and portal. */
final class NativeCompanyTicketVisibility
{
    public static function enabled($db,string $p):bool
    {
        if(!preg_match('/^[A-Za-z0-9_]+$/D',$p))throw new InvalidArgumentException('Invalid prefix');
        $rows=IssuerStorage::query($db,'SELECT version FROM '.$p.'migrations')->result_array();
        if(count($rows)!==1||!isset($rows[0]['version'])||(!is_int($rows[0]['version'])&&!is_string($rows[0]['version']))||!preg_match('/^[1-9][0-9]{0,8}$/D',(string)$rows[0]['version']))throw new DomainException('native_ticket_core_version_invalid');
        if((int)$rows[0]['version']<474)return false;
        NativeCompanyTicketLifecycleSchema::assertValid($db,$p);return true;
    }
    public static function tables($db,string $p):array{return self::enabled($db,$p)?array_merge(['migrations'],array_keys(NativeCompanyTicketLifecycleSchema::definitions($p)),array_keys(NativeCompanyTicketMergeSchema::definitions($p))):['migrations'];}
    private static function identifier(string $v):string{if(!preg_match('/^[A-Za-z_][A-Za-z0-9_]*(?:\.[A-Za-z_][A-Za-z0-9_]*)?$/D',$v))throw new InvalidArgumentException('Invalid SQL identity');return$v;}
    public static function predicate($db,string $p,string $issuer,string $id,bool $reply=false):string
    {
        $issuer=self::identifier($issuer);$id=self::identifier($id);if(!self::enabled($db,$p))return '1=1';
        $table=$reply?NativeCompanyTicketLifecycleSchema::REPLIES:NativeCompanyTicketLifecycleSchema::TICKETS;$field=$reply?'reply_id':'ticket_id';
        return 'NOT EXISTS (SELECT 1 FROM '.$p.$table.' native_deleted WHERE native_deleted.'.$field.'='.$id.')';
    }
    public static function assertTicket($db,string $p,int $issuer,int $id):void
    {self::assertLive($db,$p,$issuer,$id,false);}
    public static function assertReply($db,string $p,int $issuer,int $id):void
    {self::assertLive($db,$p,$issuer,$id,true);}
    public static function assertMutable($db,string$p,int$issuer,int$id):void{
        self::assertTicket($db,$p,$issuer,$id);if(!self::enabled($db,$p))return;NativeCompanyTicketMergeSchema::assertValid($db,$p);
        if(IssuerStorage::query($db,'SELECT issuer_id FROM '.$p.NativeCompanyTicketMergeSchema::LINKS.' WHERE child_id=?',[$id])->result_array())throw new DomainException('native_ticket_merged_read_only');
    }
    private static function assertLive($db,string $p,int $issuer,int $id,bool $reply):void
    {
        if(!self::enabled($db,$p))return;$table=$reply?NativeCompanyTicketLifecycleSchema::REPLIES:NativeCompanyTicketLifecycleSchema::TICKETS;$field=$reply?'reply_id':'ticket_id';
        // Any receipt for this globally unique source denies access, including a corrupted issuer claim.
        if(IssuerStorage::query($db,'SELECT issuer_id FROM '.$p.$table.' WHERE '.$field.'=?',[$id])->result_array())throw new DomainException('native_ticket_deleted');
    }
}
