<?php

declare(strict_types=1);
namespace BancoBrasil;
require_once __DIR__.'/Transaction.php';
/** Additive109: one durable credit receipt per immutable API origin. */
final class ApiReceiptSchema
{
    public static function statement(string $p):string
    {
        if(!preg_match('/^[A-Za-z0-9_]+$/D',$p))throw new \InvalidArgumentException('bb_prefix_invalid');
        return "CREATE TABLE IF NOT EXISTS {$p}bb_api_receipts(origin_id BIGINT UNSIGNED NOT NULL PRIMARY KEY,issuer_id BIGINT UNSIGNED NOT NULL,operation_id BIGINT UNSIGNED NOT NULL,canonical_key CHAR(64) NOT NULL,payment_id INT NOT NULL,evidence_hash CHAR(64) NOT NULL,created_by BIGINT UNSIGNED NOT NULL,created_at DATETIME NOT NULL,UNIQUE KEY canonical_receipt(canonical_key),UNIQUE KEY native_payment(payment_id),FOREIGN KEY(origin_id) REFERENCES {$p}bb_origins(id),FOREIGN KEY(operation_id) REFERENCES {$p}bb_api_operations(id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin";
    }
    public static function ensure($db,string $p,bool $create=true):void
    {
        Transaction::quiet($db,static function()use($db,$p,$create):void{
            $ddl=self::statement($p);if($create&&!$db->query($ddl))throw new \RuntimeException('bb_api_receipt_schema_failed');
            $table=$p.'bb_api_receipts';$meta=$db->query('SELECT ENGINE,TABLE_COLLATION FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?',[$table])->row_array();
            if(strtoupper($meta['ENGINE']??'')!=='INNODB'||($meta['TABLE_COLLATION']??null)!=='utf8mb4_bin')throw new \RuntimeException('bb_api_receipt_schema_invalid');
            $expected=['origin_id'=>'bigint unsigned','issuer_id'=>'bigint unsigned','operation_id'=>'bigint unsigned','canonical_key'=>'char(64)','payment_id'=>'int','evidence_hash'=>'char(64)','created_by'=>'bigint unsigned','created_at'=>'datetime'];
            $cols=[];foreach($db->query('SELECT COLUMN_NAME,COLUMN_TYPE,IS_NULLABLE,COLLATION_NAME,COLUMN_DEFAULT FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?',[$table])->result_array() as $row)$cols[$row['COLUMN_NAME']]=$row;
            foreach($expected as $field=>$type){$c=$cols[$field]??[];if(preg_replace('/^(bigint|int)\([0-9]+\)/','$1',strtolower($c['COLUMN_TYPE']??''))!==$type||($c['IS_NULLABLE']??null)!=='NO'||!array_key_exists('COLUMN_DEFAULT',$c)||($c['COLUMN_DEFAULT']!==null&&strtoupper(trim((string)$c['COLUMN_DEFAULT']))!=='NULL')||(str_starts_with($type,'char')&&($c['COLLATION_NAME']??null)!=='utf8mb4_bin'))throw new \RuntimeException('bb_api_receipt_schema_invalid');}
            $keys=[];foreach($db->query('SELECT INDEX_NAME,NON_UNIQUE,COLUMN_NAME,SUB_PART FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? ORDER BY INDEX_NAME,SEQ_IN_INDEX',[$table])->result_array() as $key){if($key['SUB_PART']!==null)throw new \RuntimeException('bb_api_receipt_schema_invalid');if((int)$key['NON_UNIQUE']===0)$keys[$key['INDEX_NAME']][]=$key['COLUMN_NAME'];}
            foreach(['PRIMARY'=>['origin_id'],'canonical_receipt'=>['canonical_key'],'native_payment'=>['payment_id']] as $key=>$fields)if(($keys[$key]??null)!==$fields)throw new \RuntimeException('bb_api_receipt_schema_invalid');
            foreach(['origin_id'=>'bb_origins','operation_id'=>'bb_api_operations'] as $column=>$target){$fk=$db->query('SELECT REFERENCED_TABLE_NAME,REFERENCED_COLUMN_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=? AND REFERENCED_TABLE_SCHEMA=DATABASE()',[$table,$column])->result_array();if(count($fk)!==1||$fk[0]['REFERENCED_TABLE_NAME']!==$p.$target||$fk[0]['REFERENCED_COLUMN_NAME']!=='id')throw new \RuntimeException('bb_api_receipt_schema_invalid');}
        });
    }
}
