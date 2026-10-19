<?php

declare(strict_types=1);
namespace Asaas;
require_once __DIR__.'/CompanySchema.php';

/** Additive 150 ledger; never rewrites native 148/149 history or legacy mappings. */
final class CompanyRemoteOperationsSchema
{
    public const TABLE='asaas_company_remote_operations';
    private const FIELDS=['origin_id'=>'int','issuer_id'=>'int unsigned','actor_id'=>'int','account_revision'=>'char(64)','request_key'=>'char(64)','proof_id'=>'int','state'=>'varchar(32)','last_code'=>'varchar(64)','created_at'=>'datetime','updated_at'=>'datetime','sent_at'=>'datetime?'];
    public static function ensure($db,string $p,bool $create=true):void
    {
        if(!preg_match('/^[A-Za-z0-9_]+$/D',$p))throw new \InvalidArgumentException('asaas_company_prefix_invalid');
        $t=$p.self::TABLE;$sqlite=CompanySchema::sqlite($db);
        if($create){$parts=[];foreach(self::FIELDS as $n=>$type){$nullable=str_ends_with($type,'?');$type=rtrim($type,'?');$parts[]='`'.$n.'` '.$type.(!$sqlite&&str_contains($type,'char')?' CHARACTER SET utf8mb4 COLLATE utf8mb4_bin':'').($nullable?' NULL':' NOT NULL');}
            $parts[]='PRIMARY KEY (`origin_id`)';$parts[]='UNIQUE '.($sqlite?'':'KEY `request_key` ').'(`request_key`)';
            foreach(['origin_id'=>['asaas_payment_origins','id'],'issuer_id'=>['issuers_registry','id'],'proof_id'=>['asaas_company_proofs','id']] as $col=>[$target,$key])$parts[]='FOREIGN KEY (`'.$col.'`) REFERENCES `'.$p.$target.'` (`'.$key.'`)';
            \IssuerStorage::query($db,'CREATE TABLE IF NOT EXISTS `'.$t.'` ('.implode(',',$parts).')'.($sqlite?'':' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin'));
        }
        if($sqlite){$rows=\IssuerStorage::query($db,'PRAGMA table_info(`'.$t.'`)')->result_array();if(count($rows)!==count(self::FIELDS)||array_column($rows,'name')!==array_keys(self::FIELDS))throw new \RuntimeException('asaas_remote_schema_invalid');return;}
        $meta=\IssuerStorage::query($db,'SELECT ENGINE,TABLE_COLLATION FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?',[$t])->row_array();
        if(strtoupper($meta['ENGINE']??'')!=='INNODB'||($meta['TABLE_COLLATION']??'')!=='utf8mb4_bin')throw new \RuntimeException('asaas_remote_schema_invalid');
        $cols=[];foreach(\IssuerStorage::query($db,'SELECT COLUMN_NAME,COLUMN_TYPE,IS_NULLABLE,COLUMN_DEFAULT,COLLATION_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?',[$t])->result_array() as $r)$cols[$r['COLUMN_NAME']]=$r;
        if(count($cols)!==count(self::FIELDS))throw new \RuntimeException('asaas_remote_schema_invalid');
        foreach(self::FIELDS as $n=>$type){$nullable=str_ends_with($type,'?');$type=rtrim($type,'?');$r=$cols[$n]??[];if(preg_replace('/^int\([0-9]+\)/','int',strtolower($r['COLUMN_TYPE']??''))!==$type||($r['IS_NULLABLE']??'')!==($nullable?'YES':'NO')||!\IssuerStorage::sqlNullDefault($r)||(str_contains($type,'char')&&($r['COLLATION_NAME']??'')!=='utf8mb4_bin'))throw new \RuntimeException('asaas_remote_schema_invalid');}
        $idx=[];foreach(\IssuerStorage::query($db,'SELECT INDEX_NAME,COLUMN_NAME,NON_UNIQUE,SUB_PART FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? ORDER BY INDEX_NAME,SEQ_IN_INDEX',[$t])->result_array() as $r){if($r['SUB_PART']!==null)throw new \RuntimeException('asaas_remote_schema_invalid');$idx[$r['INDEX_NAME']][]=$r;}
        foreach(['PRIMARY'=>'origin_id','request_key'=>'request_key'] as $name=>$col)if(count($idx[$name]??[])!==1||$idx[$name][0]['COLUMN_NAME']!==$col||(int)$idx[$name][0]['NON_UNIQUE']!==0)throw new \RuntimeException('asaas_remote_schema_invalid');
        foreach(['origin_id'=>['asaas_payment_origins','id'],'issuer_id'=>['issuers_registry','id'],'proof_id'=>['asaas_company_proofs','id']] as $col=>[$target,$key]){$fk=\IssuerStorage::query($db,'SELECT REFERENCED_TABLE_NAME,REFERENCED_COLUMN_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=? AND REFERENCED_TABLE_SCHEMA=DATABASE()',[$t,$col])->result_array();if(count($fk)!==1||$fk[0]['REFERENCED_TABLE_NAME']!==$p.$target||$fk[0]['REFERENCED_COLUMN_NAME']!==$key)throw new \RuntimeException('asaas_remote_schema_invalid');}
    }
}
