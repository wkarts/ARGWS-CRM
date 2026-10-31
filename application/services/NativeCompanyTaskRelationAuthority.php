<?php
declare(strict_types=1);
require_once __DIR__.'/NativeCompanyResourceReferences.php';
/** Parent authority for the relation declared in the existing canonical standalone task row. */
final class NativeCompanyTaskRelationAuthority
{
    public static function fields($db,string$p,int$i,int$a,array$fields):?array
    {
        $type=$fields['relation_type']??null;$kind=$fields['relation_source_kind']??null;$id=$fields['relation_source_id']??null;
        if($type===null){if($kind!==null||$id!==null)throw new DomainException('native_task_relation_invalid');return null;}
        if(!($type==='ticket'&&in_array($kind,['ticket','company_ticket'],true))&&!($type==='invoice'&&in_array($kind,['invoice','company_invoice_draft'],true)))throw new DomainException('native_task_relation_writer_required');
        $canonical=NativeCompanyResourceId::encode($kind,IssuerPolicy::id($id));
        $ref=$kind==='company_invoice_draft'&&class_exists('NativeCompanyTaskScopedDb',false)&&$db instanceof NativeCompanyTaskScopedDb
            ?$db->draftReference($i,$a,$canonical):NativeCompanyResourceReferences::resolve($db,$p,$i,$a,$type,$canonical,'view');
        if($ref['source_kind']!==$kind||(int)$ref['source_id']!==(int)$id)throw new DomainException('native_task_relation_invalid');return$ref;
    }
    public static function capture($db,string$p,int$i,int$a,int$id):array
    {
        if(!(new IssuerResourceAccess($db,$p))->can($i,$a,'tasks','view'))throw new DomainException('native_company_task_denied');
        if(!IssuerStorage::fieldExists($db,'native_fields_json',$p.'company_standalone_tasks')){
            $versions=IssuerStorage::query($db,'SELECT version FROM '.$p.'migrations')->result_array();
            if(count($versions)!==1||!preg_match('/^[1-9][0-9]{0,8}$/D',(string)$versions[0]['version'])||(int)$versions[0]['version']>=474)throw new DomainException('native_task_relation_schema_required');
            return['schema'=>'historical_without_native_fields','version'=>(int)$versions[0]['version']];
        }
        $row=IssuerStorage::query($db,'SELECT native_fields_json,native_fields_revision FROM '.$p.'company_standalone_tasks WHERE issuer_id=? AND id=?',[$i,$id])->row_array();
        if(!$row)throw new DomainException('native_company_task_denied');
        $fields=$row['native_fields_json']===null?null:json_decode($row['native_fields_json'],true,32,JSON_THROW_ON_ERROR);
        if($fields!==null&&(!is_array($fields)||array_diff(['relation_type','relation_source_kind','relation_source_id'],array_keys($fields))))throw new DomainException('native_task_relation_invalid');
        $ref=$fields===null?null:self::fields($db,$p,$i,$a,$fields);
        return ['row'=>$row,'parent'=>$ref];
    }
    public static function confirm($db,string$p,int$i,int$a,int$id,array$expected):void
    {if(self::capture($db,$p,$i,$a,$id)!==$expected)throw new DomainException('native_task_relation_stale');}
}
