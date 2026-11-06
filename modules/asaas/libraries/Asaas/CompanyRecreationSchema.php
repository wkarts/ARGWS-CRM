<?php

declare(strict_types=1);
namespace Asaas;
require_once __DIR__.'/CompanySchema.php';

/** Durable recreation intent; origin/event rows remain immutable and no charge is sent here. */
final class CompanyRecreationSchema
{
    public const TABLE='asaas_company_recreations';
    private const FIELDS=['origin_id'=>'int','issuer_id'=>'int unsigned','invoice_id'=>'int','actor_id'=>'int','invoice_revision'=>'char(64)','account_revision'=>'char(64)','request_key'=>'char(64)','proof_id'=>'int','state'=>'varchar(32)','successor_origin_id'=>'int?','created_at'=>'datetime','updated_at'=>'datetime'];
    public static function ensure($db,string $p,bool $create=true):void
    {
        if(!preg_match('/^[A-Za-z0-9_]+$/D',$p))throw new \InvalidArgumentException('asaas_company_prefix_invalid');
        $t=$p.self::TABLE;$sqlite=CompanySchema::sqlite($db);
        $connection=$db->pdo??$db->conn_id??null;
        if($create&&(($connection instanceof \PDO&&$connection->inTransaction())||(property_exists($db,'_trans_depth')&&(new \ReflectionProperty($db,'_trans_depth'))->getValue($db)>0)))throw new \RuntimeException('asaas_recreation_schema_outer_transaction_denied');
        if($create){$parts=[];foreach(self::FIELDS as $n=>$type){$nullable=str_ends_with($type,'?');$type=rtrim($type,'?');$parts[]='`'.$n.'` '.$type.(!$sqlite&&str_contains($type,'char')?' CHARACTER SET utf8mb4 COLLATE utf8mb4_bin':'').($nullable?' NULL':' NOT NULL');}
            $parts[]='PRIMARY KEY (`origin_id`)';$parts[]='UNIQUE '.($sqlite?'':'KEY `request_key` ').'(`request_key`)';$parts[]='UNIQUE '.($sqlite?'':'KEY `successor_origin_id` ').'(`successor_origin_id`)';
            foreach(['origin_id'=>['asaas_payment_origins','id'],'successor_origin_id'=>['asaas_payment_origins','id'],'issuer_id'=>['issuers_registry','id'],'proof_id'=>['asaas_company_proofs','id']] as $col=>[$target,$key])$parts[]='FOREIGN KEY (`'.$col.'`) REFERENCES `'.$p.$target.'` (`'.$key.'`)';
            \IssuerStorage::query($db,'CREATE TABLE IF NOT EXISTS `'.$t.'` ('.implode(',',$parts).')'.($sqlite?'':' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin'));
        }
        if($sqlite){$rows=\IssuerStorage::query($db,'PRAGMA table_info(`'.$t.'`)')->result_array();if(count($rows)!==count(self::FIELDS)||array_column($rows,'name')!==array_keys(self::FIELDS))throw new \RuntimeException('asaas_recreation_schema_invalid');return;}
        $meta=\IssuerStorage::query($db,'SELECT ENGINE,TABLE_COLLATION FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?',[$t])->row_array();
        if(strtoupper($meta['ENGINE']??'')!=='INNODB'||($meta['TABLE_COLLATION']??'')!=='utf8mb4_bin')throw new \RuntimeException('asaas_recreation_schema_invalid');
        $cols=[];foreach(\IssuerStorage::query($db,'SELECT COLUMN_NAME,COLUMN_TYPE,IS_NULLABLE,COLUMN_DEFAULT,COLLATION_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?',[$t])->result_array() as $r)$cols[$r['COLUMN_NAME']]=$r;
        if(count($cols)!==count(self::FIELDS))throw new \RuntimeException('asaas_recreation_schema_invalid');
        foreach(self::FIELDS as $n=>$type){$nullable=str_ends_with($type,'?');$type=rtrim($type,'?');$r=$cols[$n]??[];if(preg_replace('/^int\([0-9]+\)/','int',strtolower($r['COLUMN_TYPE']??''))!==$type||($r['IS_NULLABLE']??'')!==($nullable?'YES':'NO')||!\IssuerStorage::sqlNullDefault($r)||(str_contains($type,'char')&&($r['COLLATION_NAME']??'')!=='utf8mb4_bin'))throw new \RuntimeException('asaas_recreation_schema_invalid');}
        $idx=[];foreach(\IssuerStorage::query($db,'SELECT INDEX_NAME,COLUMN_NAME,NON_UNIQUE,SUB_PART FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? ORDER BY INDEX_NAME,SEQ_IN_INDEX',[$t])->result_array() as $r){if($r['SUB_PART']!==null)throw new \RuntimeException('asaas_recreation_schema_invalid');$idx[$r['INDEX_NAME']][]=$r;}
        foreach(['PRIMARY'=>'origin_id','request_key'=>'request_key','successor_origin_id'=>'successor_origin_id'] as $name=>$col)if(count($idx[$name]??[])!==1||$idx[$name][0]['COLUMN_NAME']!==$col||(int)$idx[$name][0]['NON_UNIQUE']!==0)throw new \RuntimeException('asaas_recreation_schema_invalid');
        foreach(['origin_id'=>['asaas_payment_origins','id'],'successor_origin_id'=>['asaas_payment_origins','id'],'issuer_id'=>['issuers_registry','id'],'proof_id'=>['asaas_company_proofs','id']] as $col=>[$target,$key]){$fk=\IssuerStorage::query($db,'SELECT REFERENCED_TABLE_NAME,REFERENCED_COLUMN_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=? AND REFERENCED_TABLE_SCHEMA=DATABASE()',[$t,$col])->result_array();if(count($fk)!==1||$fk[0]['REFERENCED_TABLE_NAME']!==$p.$target||$fk[0]['REFERENCED_COLUMN_NAME']!==$key)throw new \RuntimeException('asaas_recreation_schema_invalid');}
    }
}
