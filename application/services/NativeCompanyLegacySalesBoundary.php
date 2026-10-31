<?php
declare(strict_types=1);
require_once __DIR__.'/ClientIssuerScope.php';
require_once __DIR__.'/issuers/IssuerStorage.php';
/** Proposals linked to leads must prove that lead's historical lineage too. */
final class NativeCompanyLegacySalesBoundary
{
    public static function sql($db,string $p,string $type,string $alias):string
    {
        if(!preg_match('/^[A-Za-z0-9_]+$/D',$p)||!preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/D',$alias))throw new InvalidArgumentException('Invalid SQL identifier');
        if($type!=='proposals'){ $sql=\app\services\ClientIssuerScope::legacyDocumentSql($db,$p,$type,$alias);return '('.$sql.')'.(IssuerStorage::fieldExists($db,'issuer_id',$p.$type)?' AND '.$alias.'.issuer_id IS NULL':''); }
        $customer=\app\services\ClientIssuerScope::legacySql($db,$p,$alias,'rel_id');$lead=\app\services\ClientIssuerScope::legacySql($db,$p,'native_proposal_lead','client_id');
        if(IssuerStorage::fieldExists($db,'issuer_id',$p.'leads'))$lead.=' AND native_proposal_lead.issuer_id IS NULL';
        $sql="((HEX($alias.rel_type)=HEX('customer') AND $customer) OR (HEX($alias.rel_type)=HEX('lead') AND EXISTS (SELECT 1 FROM {$p}leads native_proposal_lead WHERE native_proposal_lead.id=$alias.rel_id AND ($lead))) OR (($alias.rel_type IS NULL OR $alias.rel_type='') AND ($alias.rel_id IS NULL OR $alias.rel_id=0)))";
        return $sql.(IssuerStorage::fieldExists($db,'issuer_id',$p.$type)?' AND '.$alias.'.issuer_id IS NULL':'');
    }
    public static function permits($db,string $p,string $type,int $id):bool
    {
        if(!in_array($type,['estimates','creditnotes','contracts','proposals'],true)||$id<1)return false;
        return(bool)IssuerStorage::query($db,'SELECT x.id FROM '.$p.$type.' x WHERE x.id=? AND '.self::sql($db,$p,$type,'x'),[$id])->row_array();
    }
}
