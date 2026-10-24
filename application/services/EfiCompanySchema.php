<?php

declare(strict_types=1);
namespace Efi;
require_once APPPATH.'services/issuers/IssuerStorage.php';

/** Named-company storage. Runtime validates only; DDL belongs exclusively to core 473. */
final class CompanySchema
{
    public const FIELDS = [
        'accounts'=>['id'=>'bigint unsigned','issuer_id'=>'int unsigned','environment'=>'varchar(16)','account_hash'=>'char(64)','credentials_cipher'=>'longtext','certificate_cipher'=>'longtext?','revision'=>'char(64)','enabled'=>'tinyint unsigned','created_by'=>'int unsigned','updated_by'=>'int unsigned','created_at'=>'datetime','updated_at'=>'datetime'],
        'origins'=>['id'=>'bigint unsigned','issuer_id'=>'int unsigned','invoice_id'=>'int unsigned','account_id'=>'bigint unsigned','account_revision'=>'char(64)','environment'=>'varchar(16)','account_hash'=>'char(64)','kind'=>'varchar(16)','amount_minor'=>'bigint unsigned','currency'=>'char(3)','snapshot_json'=>'longtext','snapshot_hash'=>'char(64)','state'=>'varchar(24)','provider_id'=>'varchar(128)?','created_by'=>'int unsigned','created_at'=>'datetime','updated_at'=>'datetime'],
        'proofs'=>['id'=>'bigint unsigned','origin_id'=>'bigint unsigned','account_revision'=>'char(64)','operation'=>'varchar(16)','result_json'=>'longtext','evidence_hash'=>'char(64)','state'=>'varchar(32)','created_by'=>'int unsigned','created_at'=>'datetime'],
        'receipts'=>['origin_id'=>'bigint unsigned','issuer_id'=>'int unsigned','proof_id'=>'bigint unsigned','payment_id'=>'int unsigned','canonical_key'=>'char(64)','evidence_hash'=>'char(64)','created_by'=>'int unsigned','created_at'=>'datetime'],
    ];
    public const KEYS = [
        'accounts'=>['PRIMARY'=>['id'],'issuer_environment'=>['issuer_id','environment'],'account_environment'=>['account_hash','environment']],
        'origins'=>['PRIMARY'=>['id'],'invoice_origin'=>['invoice_id'],'provider_identity'=>['account_id','kind','provider_id']],
        'proofs'=>['PRIMARY'=>['id']],
        'receipts'=>['PRIMARY'=>['origin_id'],'native_payment'=>['payment_id'],'canonical_receipt'=>['canonical_key']],
    ];
    public static function tables():array{return array_map(static fn($s)=>'efi_company_'.$s,array_keys(self::FIELDS));}
    public static function statements(string $p):array
    {
        if(!preg_match('/^[A-Za-z0-9_]+$/D',$p))throw new \InvalidArgumentException('efi_company_prefix_invalid');
        $out=[];
        foreach(self::FIELDS as $suffix=>$fields){$parts=[];
            foreach($fields as $name=>$type){$nullable=str_ends_with($type,'?');$type=rtrim($type,'?');$parts[]='`'.$name.'` '.strtoupper($type).(preg_match('/char|text/',$type)?' CHARACTER SET utf8mb4 COLLATE utf8mb4_bin':'').($nullable?' NULL':' NOT NULL').($name==='id'?' AUTO_INCREMENT':'');}
            foreach(self::KEYS[$suffix] as $name=>$columns)$parts[]=($name==='PRIMARY'?'PRIMARY KEY':'UNIQUE KEY `'.$name.'`').' (`'.implode('`,`',$columns).'`)';
            $out[$suffix]='CREATE TABLE IF NOT EXISTS `'.$p.'efi_company_'.$suffix.'` ('.implode(',',$parts).') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin';
        }return $out;
    }
    public static function ensure($db,string $p,bool $create=false):void
    {
        $statements=self::statements($p);
        foreach(self::FIELDS as $suffix=>$fields){$table=$p.'efi_company_'.$suffix;
            if($create)\IssuerStorage::query($db,$statements[$suffix]);
            $meta=\IssuerStorage::query($db,'SELECT ENGINE,TABLE_COLLATION FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?',[$table])->row_array();
            if(strtoupper($meta['ENGINE']??'')!=='INNODB'||($meta['TABLE_COLLATION']??'')!=='utf8mb4_bin')throw new \RuntimeException('efi_company_schema_invalid');
            $columns=\IssuerStorage::query($db,'SHOW FULL COLUMNS FROM `'.$table.'`')->result_array();
            if(count($columns)!==count($fields))throw new \RuntimeException('efi_company_schema_invalid');
            foreach($columns as $row){$name=$row['Field'];$expected=$fields[$name]??null;if($expected===null)throw new \RuntimeException('efi_company_schema_invalid');
                $nullable=str_ends_with($expected,'?');$expected=rtrim($expected,'?');$type=preg_replace('/\b(bigint|tinyint|int)\([0-9]+\)/','$1',strtolower($row['Type']));
                if($type!==$expected||$row['Null']!==($nullable?'YES':'NO')||$row['Collation']!==(preg_match('/char|text/',$expected)?'utf8mb4_bin':null)||$row['Extra']!==($name==='id'?'auto_increment':'')||!\IssuerStorage::sqlNullDefault(['COLUMN_DEFAULT'=>$row['Default']]))throw new \RuntimeException('efi_company_schema_invalid');
            }
            $keys=[];foreach(\IssuerStorage::query($db,'SHOW INDEX FROM `'.$table.'`')->result_array() as $row){if((int)$row['Non_unique']!==0||$row['Sub_part']!==null||$row['Index_type']!=='BTREE')throw new \RuntimeException('efi_company_schema_invalid');$keys[$row['Key_name']][(int)$row['Seq_in_index']]=$row['Column_name'];}
            if(count($keys)!==count(self::KEYS[$suffix]))throw new \RuntimeException('efi_company_schema_invalid');
            foreach(self::KEYS[$suffix] as $key=>$columns)if(array_values($keys[$key]??[])!==$columns)throw new \RuntimeException('efi_company_schema_invalid');
        }
    }
}
