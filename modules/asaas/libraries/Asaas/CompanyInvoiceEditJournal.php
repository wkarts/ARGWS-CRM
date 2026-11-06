<?php
declare(strict_types=1);
namespace Asaas;
require_once __DIR__.'/CompanyInvoiceEditSchema.php';
/** Read-only effective snapshot: original origin/meta/proofs are never rewritten. */
final class CompanyInvoiceEditJournal
{
    public const TABLE='asaas_company_invoice_edits';
    public static function json(array $v):string{return json_encode($v,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);}
    public static function tables($db,string $p):array
    {
        if($db->table_exists($p.self::TABLE))return [self::TABLE];
        $version=\IssuerStorage::query($db,'SELECT installed_version FROM '.$p.'modules WHERE module_name=?',['asaas'])->row_array()['installed_version']??'0';
        if(version_compare($version,'1.7.0','>='))throw new \DomainException('asaas_invoice_edit_schema_required');return [];
    }
    public static function audit(array $row):string
    {
        return self::json(['request_key'=>$row['request_key'],'origin_id'=>(int)$row['origin_id'],'input_hash'=>$row['input_hash'],'before_hash'=>$row['before_hash'],'after_hash'=>$row['after_hash'],'result_hash'=>$row['result_hash']]);
    }
    public static function snapshots(array $before,array $after):void
    {
        if(array_keys($before)!==array_keys($after)||array_diff_key($before,['duedate'=>true,'total'=>true])!==array_diff_key($after,['duedate'=>true,'total'=>true]))throw new \DomainException('asaas_invoice_edit_snapshot_conflict');
        foreach([$before,$after] as $snapshot){
            if(!is_string($snapshot['total']??null)||\CompanyInvoiceAmounts::minor($snapshot['total'])<=0)throw new \DomainException('asaas_invoice_edit_snapshot_conflict');
            $date=\DateTimeImmutable::createFromFormat('!Y-m-d',$snapshot['duedate']??'');if(!$date||$date->format('Y-m-d')!==$snapshot['duedate'])throw new \DomainException('asaas_invoice_edit_snapshot_conflict');
        }
    }
    public static function effective($db,string $p,array $origin,array $meta,?string $ownKey=null):array
    {
        if(!self::tables($db,$p))return [$origin,$meta];
        \IssuerTransaction::assertOwned($db,$p,[self::TABLE,'issuers_audit']);CompanyInvoiceEditSchema::ensure($db,$p,false);
        $rows=\IssuerStorage::query($db,'SELECT * FROM '.$p.self::TABLE.' WHERE origin_id=? ORDER BY sequence_no FOR UPDATE',[$origin['id']])->result_array();
        $current=$meta['snapshot_json'];if(!hash_equals($meta['snapshot_hash'],hash('sha256',$current)))throw new \DomainException('asaas_invoice_edit_history_conflict');
        $sequence=0;$applied=[];
        foreach($rows as $row){
            if((int)$row['sequence_no']!==++$sequence||(int)$row['issuer_id']!==(int)$origin['issuer_id']||(int)$row['account_row']!==(int)$meta['account_row']||!hash_equals($row['input_hash'],hash('sha256',$row['input_json']))||!hash_equals($row['before_hash'],hash('sha256',$row['before_json']))||!hash_equals($row['after_hash'],hash('sha256',$row['after_json']))||$row['before_json']!==$current)throw new \DomainException('asaas_invoice_edit_history_conflict');
            $input=json_decode($row['input_json'],true,64,JSON_THROW_ON_ERROR);if(($input['invoice']??null)!==(int)$origin['invoice_id']||($input['remote']??null)!==$origin['remote_id'])throw new \DomainException('asaas_invoice_edit_history_conflict');
            self::snapshots(json_decode($current,true,32,JSON_THROW_ON_ERROR),json_decode($row['after_json'],true,32,JSON_THROW_ON_ERROR));
            if($row['state']!=='applied'){
                if($row['request_key']!==$ownKey||(int)$row['active_slot']!==1||$row['audit_id']!==null||!in_array($row['state'],['reserved','sending','uncertain','confirmed'],true)||$sequence!==count($rows))throw new \DomainException('asaas_invoice_edit_pending');
                continue;
            }
            if($row['active_slot']!==null||$row['audit_id']===null||$row['result_json']===null||!hash_equals($row['result_hash']??'',hash('sha256',$row['result_json'])))throw new \DomainException('asaas_invoice_edit_history_conflict');
            $result=json_decode($row['result_json'],true,32,JSON_THROW_ON_ERROR);$after=json_decode($row['after_json'],true,32,JSON_THROW_ON_ERROR);
            if(($result['id']??null)!==$origin['remote_id']||($result['customer']??null)!==$meta['customer_id']||($result['external_reference']??null)!==$after['hash']||($result['value']??null)!==$after['total']||($result['due_date']??null)!==$after['duedate']||($result['billing_type']??null)!==$meta['billing_type']||($result['deleted']??true)!==false||!in_array($result['status']??null,['PENDING','OVERDUE'],true))throw new \DomainException('asaas_invoice_edit_history_conflict');
            $audit=\IssuerStorage::query($db,'SELECT id FROM '.$p.'issuers_audit WHERE id=? AND issuer_id=? AND actor_id=? AND subject_id=? AND event=? AND metadata_json=?',[$row['audit_id'],$origin['issuer_id'],$row['actor_id'],$origin['invoice_id'],'company_asaas_invoice_edited',self::audit($row)])->row_array();
            if(!$audit)throw new \DomainException('asaas_invoice_edit_history_conflict');$applied[]=(int)$row['audit_id'];$current=$row['after_json'];
        }
        $audits=\IssuerStorage::query($db,'SELECT id,metadata_json FROM '.$p.'issuers_audit WHERE issuer_id=? AND subject_id=? AND event=? ORDER BY id FOR UPDATE',[$origin['issuer_id'],$origin['invoice_id'],'company_asaas_invoice_edited'])->result_array();$found=[];
        foreach($audits as $audit)if((json_decode($audit['metadata_json'],true,16,JSON_THROW_ON_ERROR)['origin_id']??null)===(int)$origin['id'])$found[]=(int)$audit['id'];sort($applied);if($found!==$applied)throw new \DomainException('asaas_invoice_edit_history_conflict');
        $snapshot=json_decode($current,true,32,JSON_THROW_ON_ERROR);$meta['snapshot_json']=$current;$meta['snapshot_hash']=hash('sha256',$current);
        $origin['invoice_total']=$snapshot['total'];$origin['invoice_due_date']=$snapshot['duedate'];return [$origin,$meta];
    }
}
