<?php

declare(strict_types=1);
namespace BancoBrasil;
require_once __DIR__.'/Transaction.php';
final class ApiSchema
{
    private const COLUMNS=[
        'bb_api_profiles'=>['id'=>'bigint unsigned','issuer_id'=>'bigint unsigned','connection_id'=>'bigint unsigned','api_stage'=>'varchar(16)','configuration_hash'=>'char(64)','profile_hash'=>'char(64)','definition_json'=>'longtext','created_by'=>'bigint unsigned','created_at'=>'datetime'],
        'bb_api_credentials'=>['id'=>'bigint unsigned','profile_id'=>'bigint unsigned','issuer_id'=>'bigint unsigned','context_nonce'=>'char(64)','ciphertext'=>'longtext','created_by'=>'bigint unsigned','created_at'=>'datetime'],
        'bb_api_origins'=>['origin_id'=>'bigint unsigned','profile_id'=>'bigint unsigned','request_key'=>'char(64)','input_hash'=>'char(64)','payload_json'=>'longtext','snapshot_json'=>'longtext'],
        'bb_api_operations'=>['id'=>'bigint unsigned','origin_id'=>'bigint unsigned','operation'=>'varchar(12)','request_key'=>'char(64)','claim_token'=>'char(64)','status'=>'varchar(24)','http_status'=>'int unsigned','credential_id'=>'bigint unsigned?','response_hash'=>'char(64)','result_json'=>'longtext','failure_category'=>'varchar(48)','actor_id'=>'bigint unsigned','locked_until'=>'datetime','created_at'=>'datetime','updated_at'=>'datetime'],
    ];
    private const UNIQUE=[
        'bb_api_profiles'=>['PRIMARY'=>['id'],'profile_revision'=>['profile_hash']],
        'bb_api_credentials'=>['PRIMARY'=>['id'],'credential_context'=>['context_nonce']],
        'bb_api_origins'=>['PRIMARY'=>['origin_id'],'origin_request'=>['request_key']],
        'bb_api_operations'=>['PRIMARY'=>['id'],'operation_request'=>['origin_id','operation','request_key']],
    ];
    private const FKS=[
        'bb_api_profiles'=>['connection_id'=>['bb_connections','id']],
        'bb_api_credentials'=>['profile_id'=>['bb_api_profiles','id']],
        'bb_api_origins'=>['origin_id'=>['bb_origins','id'],'profile_id'=>['bb_api_profiles','id']],
        'bb_api_operations'=>['origin_id'=>['bb_origins','id'],'credential_id'=>['bb_api_credentials','id']],
    ];
    public static function statements(string $p):array
    {
        if(!preg_match('/^[A-Za-z0-9_]+$/D',$p))throw new \InvalidArgumentException('bb_prefix_invalid');$sql=[];
        foreach(self::COLUMNS as $suffix=>$fields){$parts=[];foreach($fields as $field=>$type){$nullable=str_ends_with($type,'?');$parts[]='`'.$field.'` '.rtrim($type,'?').($nullable?' NULL':' NOT NULL').($field==='id'?' AUTO_INCREMENT':'');}
            foreach(self::UNIQUE[$suffix] as $key=>$columns)$parts[]=($key==='PRIMARY'?'PRIMARY KEY':'UNIQUE KEY `'.$key.'`').'(`'.implode('`,`',$columns).'`)';
            foreach(self::FKS[$suffix] as $column=>[$target,$key])$parts[]='FOREIGN KEY(`'.$column.'`) REFERENCES `'.$p.$target.'`(`'.$key.'`)';
            $sql[]='CREATE TABLE IF NOT EXISTS `'.$p.$suffix.'`('.implode(',',$parts).') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin';
        }return $sql;
    }
    public static function ensure($db,string $p,bool $create=true):void
    {
        Transaction::quiet($db,static function()use($db,$p,$create):void{
            if($create)foreach(self::statements($p) as $sql)if(!$db->query($sql))throw new \RuntimeException('bb_api_schema_failed');
            foreach(self::COLUMNS as $suffix=>$fields){$table=$p.$suffix;
                $meta=$db->query('SELECT ENGINE,TABLE_COLLATION FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?',[$table])->row_array();if(strtoupper($meta['ENGINE']??'')!=='INNODB'||($meta['TABLE_COLLATION']??null)!=='utf8mb4_bin')throw new \RuntimeException('bb_api_schema_invalid');
                $cols=[];foreach($db->query('SELECT COLUMN_NAME,COLUMN_TYPE,IS_NULLABLE,COLLATION_NAME,EXTRA FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?',[$table])->result_array() as $c)$cols[$c['COLUMN_NAME']]=$c;
                foreach($fields as $field=>$type){$c=$cols[$field]??[];$nullable=str_ends_with($type,'?');$type=rtrim($type,'?');if(preg_replace('/^(bigint|int)\([0-9]+\)/','$1',strtolower($c['COLUMN_TYPE']??''))!==$type||($c['IS_NULLABLE']??null)!==($nullable?'YES':'NO')||(preg_match('/char|text/',$type)&&($c['COLLATION_NAME']??null)!=='utf8mb4_bin')||($field==='id'&&($c['EXTRA']??null)!=='auto_increment'))throw new \RuntimeException('bb_api_schema_invalid');}
                $keys=[];foreach($db->query('SELECT INDEX_NAME,NON_UNIQUE,COLUMN_NAME,SUB_PART FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? ORDER BY INDEX_NAME,SEQ_IN_INDEX',[$table])->result_array() as $key){if($key['SUB_PART']!==null)throw new \RuntimeException('bb_api_schema_invalid');if((int)$key['NON_UNIQUE']===0)$keys[$key['INDEX_NAME']][]=$key['COLUMN_NAME'];}
                foreach(self::UNIQUE[$suffix] as $key=>$columns)if(($keys[$key]??null)!==$columns)throw new \RuntimeException('bb_api_schema_invalid');
                foreach(self::FKS[$suffix] as $column=>[$target,$key]){$fk=$db->query('SELECT REFERENCED_TABLE_NAME,REFERENCED_COLUMN_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=? AND REFERENCED_TABLE_SCHEMA=DATABASE()',[$table,$column])->result_array();if(count($fk)!==1||$fk[0]['REFERENCED_TABLE_NAME']!==$p.$target||$fk[0]['REFERENCED_COLUMN_NAME']!==$key)throw new \RuntimeException('bb_api_schema_invalid');}
            }
        });
    }
}
