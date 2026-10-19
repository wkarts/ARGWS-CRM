<?php

declare(strict_types=1);
namespace Asaas;
require_once APPPATH.'services/issuers/IssuerStorage.php';

/** Additive company adapter only. Existing legacy origins/webhook ledgers are not rewritten. */
final class CompanySchema
{
    public const FIELDS=[
        'accounts'=>['id'=>'int','issuer_id'=>'int unsigned','environment'=>'varchar(16)','account_id'=>'varchar(128)','secret_cipher'=>'text','revision'=>'char(64)','verified_at'=>'datetime?','webhook_public_id'=>'char(32)','token_hash'=>'char(64)?','automation_actor'=>'int?','enabled'=>'tinyint','created_at'=>'datetime'],
        'origin_meta'=>['origin_id'=>'int','account_row'=>'int','created_by'=>'int','request_key'=>'char(64)','snapshot_hash'=>'char(64)','snapshot_json'=>'longtext','customer_id'=>'varchar(64)','billing_type'=>'varchar(20)','created_at'=>'datetime'],
        'proofs'=>['id'=>'int','origin_id'=>'int','account_revision'=>'char(64)','actor_id'=>'int','operation'=>'varchar(16)','result_json'=>'longtext','evidence_hash'=>'char(64)','state'=>'varchar(32)','created_at'=>'datetime'],
        'events'=>['id'=>'int','account_row'=>'int','event_id'=>'varchar(128)','event_type'=>'varchar(64)','remote_id'=>'varchar(64)','payload_hash'=>'char(64)','state'=>'varchar(32)','created_at'=>'datetime'],
        'receipts'=>['origin_id'=>'int','issuer_id'=>'int unsigned','proof_id'=>'int','canonical_key'=>'char(64)','evidence_hash'=>'char(64)','payment_id'=>'int','created_by'=>'int','created_at'=>'datetime'],
        'jobs'=>['event_id'=>'int','issuer_id'=>'int unsigned','origin_id'=>'int','actor_id'=>'int','state'=>'varchar(32)','attempts'=>'int','lease_token'=>'char(64)?','lease_until'=>'datetime?','next_attempt_at'=>'datetime','last_code'=>'varchar(64)','created_at'=>'datetime','updated_at'=>'datetime'],
        'fee_expenses'=>['origin_id'=>'int','issuer_id'=>'int unsigned','proof_id'=>'int','payment_id'=>'int','expense_id'=>'int unsigned','canonical_key'=>'char(64)','evidence_hash'=>'char(64)','created_by'=>'int','created_at'=>'datetime'],
    ];
    private const INDEXES=[
        'accounts'=>['PRIMARY'=>[['id'],0],'company_environment'=>[['issuer_id','environment'],0],'account_environment'=>[['account_id','environment'],0],'public_webhook'=>[['webhook_public_id'],0]],
        'origin_meta'=>['PRIMARY'=>[['origin_id'],0],'request_key'=>[['request_key'],0]],
        'proofs'=>['PRIMARY'=>[['id'],0],'proof_origin'=>[['origin_id','id'],1]],
        'events'=>['PRIMARY'=>[['id'],0],'event_identity'=>[['account_row','event_id'],0],'event_remote'=>[['account_row','remote_id','id'],1]],
        'jobs'=>['PRIMARY'=>[['event_id'],0],'pending_jobs'=>[['state','next_attempt_at','event_id'],1]],
        'fee_expenses'=>['PRIMARY'=>[['origin_id'],0],'canonical_fee'=>[['canonical_key'],0],'expense_link'=>[['expense_id'],0]],
        'receipts'=>['PRIMARY'=>[['origin_id'],0],'canonical_key'=>[['canonical_key'],0],'native_payment'=>[['payment_id'],0]],
    ];
    private const FKS=[
        'accounts'=>['issuer_id'=>['issuers_registry','id']],
        'origin_meta'=>['origin_id'=>['asaas_payment_origins','id'],'account_row'=>['asaas_company_accounts','id']],
        'proofs'=>['origin_id'=>['asaas_payment_origins','id']],
        'events'=>['account_row'=>['asaas_company_accounts','id']],
        'jobs'=>['event_id'=>['asaas_company_events','id'],'origin_id'=>['asaas_payment_origins','id']],
        'fee_expenses'=>['origin_id'=>['asaas_company_receipts','origin_id'],'proof_id'=>['asaas_company_proofs','id'],'payment_id'=>['invoicepaymentrecords','id'],'expense_id'=>['company_expenses','id']],
        'receipts'=>['origin_id'=>['asaas_payment_origins','id'],'proof_id'=>['asaas_company_proofs','id'],'payment_id'=>['invoicepaymentrecords','id']],
    ];
    public static function tables():array{return array_map(static fn($s)=>'asaas_company_'.$s,array_keys(self::FIELDS));}
    public static function sqlite($db):bool{$c=$db->pdo??$db->conn_id??null;return $c instanceof \PDO&&$c->getAttribute(\PDO::ATTR_DRIVER_NAME)==='sqlite';}
    public static function ensure($db,string $p,bool $create=true):void
    {
        if(!preg_match('/^[A-Za-z0-9_]+$/D',$p))throw new \InvalidArgumentException('asaas_company_prefix_invalid');
        $sqlite=self::sqlite($db);
        foreach(self::FIELDS as $suffix=>$fields){
            $table=$p.'asaas_company_'.$suffix;
            if($create){
                $parts=[];
                foreach($fields as $name=>$type){$nullable=str_ends_with($type,'?');$type=rtrim($type,'?');
                    $parts[]='`'.$name.'` '.($name==='id'&&$sqlite?'INTEGER PRIMARY KEY AUTOINCREMENT':$type.(!$sqlite&&preg_match('/(?:var)?char/',$type)?' CHARACTER SET utf8mb4 COLLATE utf8mb4_bin':'').($nullable?' NULL':' NOT NULL').($name==='id'?' AUTO_INCREMENT':''));
                }
                foreach(self::INDEXES[$suffix] as $name=>[$columns,$nonUnique]){
                    if($sqlite){if($name==='PRIMARY'&&!array_key_exists('id',$fields))$parts[]='PRIMARY KEY (`'.implode('`,`',$columns).'`)';continue;}
                    $parts[]=($name==='PRIMARY'?'PRIMARY KEY':($nonUnique?'KEY `'.$name.'`':'UNIQUE KEY `'.$name.'`')).' (`'.implode('`,`',$columns).'`)';
                }
                foreach(self::FKS[$suffix] as $column=>[$target,$key])$parts[]='FOREIGN KEY (`'.$column.'`) REFERENCES `'.$p.$target.'` (`'.$key.'`)';
                \IssuerStorage::query($db,'CREATE TABLE IF NOT EXISTS `'.$table.'` ('.implode(',',$parts).')'.($sqlite?'':' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin'));
                if($sqlite)foreach(self::INDEXES[$suffix] as $name=>[$columns,$nonUnique])if($name!=='PRIMARY')\IssuerStorage::query($db,'CREATE '.($nonUnique?'':'UNIQUE ').'INDEX IF NOT EXISTS `'.$table.'_'.$name.'` ON `'.$table.'` (`'.implode('`,`',$columns).'`)');
            }
            if($sqlite){$meta=\IssuerStorage::query($db,'PRAGMA table_info(`'.$table.'`)')->result_array();if(count($meta)!==count($fields))throw new \RuntimeException('asaas_company_schema_invalid');continue;}
            $meta=\IssuerStorage::query($db,'SELECT ENGINE,TABLE_COLLATION FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?',[$table])->row_array();
            if(strtoupper($meta['ENGINE']??'')!=='INNODB'||($meta['TABLE_COLLATION']??'')!=='utf8mb4_bin')throw new \RuntimeException('asaas_company_schema_invalid');
            $columns=[];foreach(\IssuerStorage::query($db,'SELECT COLUMN_NAME,COLUMN_TYPE,IS_NULLABLE,COLUMN_DEFAULT,COLLATION_NAME,EXTRA FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?',[$table])->result_array() as $row)$columns[$row['COLUMN_NAME']]=$row;
            foreach($fields as $name=>$type){$nullable=str_ends_with($type,'?');$type=rtrim($type,'?');$row=$columns[$name]??[];
                if(preg_replace('/^(int|tinyint)\([0-9]+\)/','$1',strtolower($row['COLUMN_TYPE']??''))!==$type||($row['IS_NULLABLE']??'')!==($nullable?'YES':'NO')||!\IssuerStorage::sqlNullDefault($row)||($name==='id'&&($row['EXTRA']??'')!=='auto_increment')||(preg_match('/(?:var)?char|text/',$type)&&($row['COLLATION_NAME']??'')!=='utf8mb4_bin'))throw new \RuntimeException('asaas_company_schema_invalid');
            }
            $indexes=[];foreach(\IssuerStorage::query($db,'SELECT INDEX_NAME,COLUMN_NAME,NON_UNIQUE,SUB_PART FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? ORDER BY INDEX_NAME,SEQ_IN_INDEX',[$table])->result_array() as $row){if($row['SUB_PART']!==null)throw new \RuntimeException('asaas_company_schema_invalid');$indexes[$row['INDEX_NAME']]['columns'][]=$row['COLUMN_NAME'];$indexes[$row['INDEX_NAME']]['non_unique']=(int)$row['NON_UNIQUE'];}
            foreach(self::INDEXES[$suffix] as $name=>[$columns,$nonUnique])if(($indexes[$name]['columns']??[])!==$columns||($indexes[$name]['non_unique']??null)!==$nonUnique)throw new \RuntimeException('asaas_company_schema_invalid');
            foreach(self::FKS[$suffix] as $column=>[$target,$key]){$fk=\IssuerStorage::query($db,'SELECT REFERENCED_TABLE_NAME,REFERENCED_COLUMN_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=? AND REFERENCED_TABLE_SCHEMA=DATABASE()',[$table,$column])->result_array();if(count($fk)!==1||$fk[0]['REFERENCED_TABLE_NAME']!==$p.$target||$fk[0]['REFERENCED_COLUMN_NAME']!==$key)throw new \RuntimeException('asaas_company_schema_invalid');}
        }
    }
}
