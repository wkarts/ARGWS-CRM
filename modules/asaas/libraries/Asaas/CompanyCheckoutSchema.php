<?php

declare(strict_types=1);
namespace Asaas;
require_once __DIR__.'/CompanySchema.php';

/** Native151: account-scoped payer intents and explicit publication of an existing canonical hosted link. */
final class CompanyCheckoutSchema
{
    public const FIELDS=[
        'payers'=>['id'=>'int','issuer_id'=>'int unsigned','account_row'=>'int','client_id'=>'int','actor_id'=>'int','account_revision'=>'char(64)','request_key'=>'char(64)','external_reference'=>'char(64)','snapshot_hash'=>'char(64)','customer_id'=>'varchar(64)?','state'=>'varchar(32)','last_code'=>'varchar(64)','proof_hash'=>'char(64)?','sent_at'=>'datetime?','created_at'=>'datetime','updated_at'=>'datetime'],
        'hosted_links'=>['origin_id'=>'int','issuer_id'=>'int unsigned','proof_id'=>'int','account_revision'=>'char(64)','url_hash'=>'char(64)','published_by'=>'int','enabled'=>'tinyint','created_at'=>'datetime','updated_at'=>'datetime'],
    ];
    private const INDEXES=[
        'payers'=>['PRIMARY'=>[['id'],0],'account_client'=>[['account_row','client_id'],0],'request_key'=>[['request_key'],0],'external_reference'=>[['external_reference'],0],'account_remote'=>[['account_row','customer_id'],0]],
        'hosted_links'=>['PRIMARY'=>[['origin_id'],0]],
    ];
    private const FKS=[
        'payers'=>['issuer_id'=>['issuers_registry','id'],'account_row'=>['asaas_company_accounts','id'],'client_id'=>['clients','userid']],
        'hosted_links'=>['origin_id'=>['asaas_payment_origins','id'],'issuer_id'=>['issuers_registry','id'],'proof_id'=>['asaas_company_proofs','id']],
    ];
    public static function tables():array{return ['asaas_company_payers','asaas_company_hosted_links'];}
    public static function ensure($db,string $p,bool $create=true):void
    {
        if(!preg_match('/^[A-Za-z0-9_]+$/D',$p))throw new \InvalidArgumentException('asaas_company_prefix_invalid');$sqlite=CompanySchema::sqlite($db);
        foreach(self::FIELDS as $suffix=>$fields){$t=$p.'asaas_company_'.$suffix;
            if($create){$parts=[];foreach($fields as $n=>$type){$nullable=str_ends_with($type,'?');$type=rtrim($type,'?');$parts[]='`'.$n.'` '.($n==='id'&&$sqlite?'INTEGER PRIMARY KEY AUTOINCREMENT':$type.(!$sqlite&&str_contains($type,'char')?' CHARACTER SET utf8mb4 COLLATE utf8mb4_bin':'').($nullable?' NULL':' NOT NULL').($n==='id'?' AUTO_INCREMENT':''));}
                foreach(self::INDEXES[$suffix] as $name=>[$cols,$nonUnique]){if($sqlite){if($name==='PRIMARY'&&!isset($fields['id']))$parts[]='PRIMARY KEY (`'.implode('`,`',$cols).'`)';continue;}$parts[]=($name==='PRIMARY'?'PRIMARY KEY':($nonUnique?'KEY `'.$name.'`':'UNIQUE KEY `'.$name.'`')).' (`'.implode('`,`',$cols).'`)';}
                foreach(self::FKS[$suffix] as $col=>[$target,$key])$parts[]='FOREIGN KEY (`'.$col.'`) REFERENCES `'.$p.$target.'` (`'.$key.'`)';
                \IssuerStorage::query($db,'CREATE TABLE IF NOT EXISTS `'.$t.'` ('.implode(',',$parts).')'.($sqlite?'':' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin'));
                if($sqlite)foreach(self::INDEXES[$suffix] as $name=>[$cols,$nonUnique])if($name!=='PRIMARY')\IssuerStorage::query($db,'CREATE '.($nonUnique?'':'UNIQUE ').'INDEX IF NOT EXISTS `'.$t.'_'.$name.'` ON `'.$t.'` (`'.implode('`,`',$cols).'`)');
            }
            if($sqlite){$cols=\IssuerStorage::query($db,'PRAGMA table_info(`'.$t.'`)')->result_array();if(count($cols)!==count($fields)||array_column($cols,'name')!==array_keys($fields))throw new \RuntimeException('asaas_checkout_schema_invalid');continue;}
            $meta=\IssuerStorage::query($db,'SELECT ENGINE,TABLE_COLLATION FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?',[$t])->row_array();if(strtoupper($meta['ENGINE']??'')!=='INNODB'||($meta['TABLE_COLLATION']??'')!=='utf8mb4_bin')throw new \RuntimeException('asaas_checkout_schema_invalid');
            $cols=[];foreach(\IssuerStorage::query($db,'SELECT COLUMN_NAME,COLUMN_TYPE,IS_NULLABLE,COLUMN_DEFAULT,COLLATION_NAME,EXTRA FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?',[$t])->result_array() as $r)$cols[$r['COLUMN_NAME']]=$r;
            if(count($cols)!==count($fields))throw new \RuntimeException('asaas_checkout_schema_invalid');foreach($fields as $n=>$type){$nullable=str_ends_with($type,'?');$type=rtrim($type,'?');$r=$cols[$n]??[];if(preg_replace('/^(int|tinyint)\([0-9]+\)/','$1',strtolower($r['COLUMN_TYPE']??''))!==$type||($r['IS_NULLABLE']??'')!==($nullable?'YES':'NO')||!\IssuerStorage::sqlNullDefault($r)||($n==='id'&&($r['EXTRA']??'')!=='auto_increment')||(str_contains($type,'char')&&($r['COLLATION_NAME']??'')!=='utf8mb4_bin'))throw new \RuntimeException('asaas_checkout_schema_invalid');}
            $idx=[];foreach(\IssuerStorage::query($db,'SELECT INDEX_NAME,COLUMN_NAME,NON_UNIQUE,SUB_PART FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? ORDER BY INDEX_NAME,SEQ_IN_INDEX',[$t])->result_array() as $r){if($r['SUB_PART']!==null)throw new \RuntimeException('asaas_checkout_schema_invalid');$idx[$r['INDEX_NAME']]['columns'][]=$r['COLUMN_NAME'];$idx[$r['INDEX_NAME']]['non_unique']=(int)$r['NON_UNIQUE'];}
            foreach(self::INDEXES[$suffix] as $n=>[$cols,$nonUnique])if(($idx[$n]['columns']??[])!==$cols||($idx[$n]['non_unique']??null)!==$nonUnique)throw new \RuntimeException('asaas_checkout_schema_invalid');
            foreach(self::FKS[$suffix] as $col=>[$target,$key]){$fk=\IssuerStorage::query($db,'SELECT REFERENCED_TABLE_NAME,REFERENCED_COLUMN_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=? AND REFERENCED_TABLE_SCHEMA=DATABASE()',[$t,$col])->result_array();if(count($fk)!==1||$fk[0]['REFERENCED_TABLE_NAME']!==$p.$target||$fk[0]['REFERENCED_COLUMN_NAME']!==$key)throw new \RuntimeException('asaas_checkout_schema_invalid');}
        }
    }
}
