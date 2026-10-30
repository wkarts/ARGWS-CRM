<?php

declare(strict_types=1);
namespace BancoBrasil;
require_once __DIR__.'/Transaction.php';
/** Additive107 review/receipt provenance; a review is never a bank authentication assertion. */
final class ReconciliationSchema
{
    public static function statements(string $p):array
    {
        if(!preg_match('/^[A-Za-z0-9_]+$/D',$p))throw new \InvalidArgumentException('bb_prefix_invalid');
        $e=' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin';
        return [
            "CREATE TABLE IF NOT EXISTS {$p}bb_event_links(event_id BIGINT UNSIGNED NOT NULL PRIMARY KEY,origin_id BIGINT UNSIGNED NOT NULL,issuer_id BIGINT UNSIGNED NOT NULL,linked_by BIGINT UNSIGNED NOT NULL,created_at DATETIME NOT NULL,FOREIGN KEY(event_id) REFERENCES {$p}bb_return_events(id),FOREIGN KEY(origin_id) REFERENCES {$p}bb_origins(id))".$e,
            "CREATE TABLE IF NOT EXISTS {$p}bb_event_reviews(id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,event_id BIGINT UNSIGNED NOT NULL,issuer_id BIGINT UNSIGNED NOT NULL,actor_id BIGINT UNSIGNED NOT NULL,decision VARCHAR(32) NOT NULL,reason TEXT NOT NULL,evidence_reference VARCHAR(191) NOT NULL,review_key CHAR(64) NOT NULL,created_at DATETIME NOT NULL,UNIQUE KEY review_identity(review_key),FOREIGN KEY(event_id) REFERENCES {$p}bb_return_events(id))".$e,
            // The core company_payment_receipts ledger owns the FK to the native payment record.
            "CREATE TABLE IF NOT EXISTS {$p}bb_event_receipts(event_id BIGINT UNSIGNED NOT NULL PRIMARY KEY,origin_id BIGINT UNSIGNED NOT NULL,issuer_id BIGINT UNSIGNED NOT NULL,canonical_key CHAR(64) NOT NULL,payment_id INT NOT NULL,evidence_hash CHAR(64) NOT NULL,created_by BIGINT UNSIGNED NOT NULL,created_at DATETIME NOT NULL,UNIQUE KEY canonical_receipt(canonical_key),UNIQUE KEY native_payment(payment_id),FOREIGN KEY(event_id) REFERENCES {$p}bb_return_events(id),FOREIGN KEY(origin_id) REFERENCES {$p}bb_origins(id))".$e,
        ];
    }
    public static function ensure($db,string $prefix,bool $create=true):void
    {
        Transaction::quiet($db,static function()use($db,$prefix,$create):void{
            if($create)foreach(self::statements($prefix) as $sql)if(!$db->query($sql))throw new \RuntimeException('bb_reconciliation_schema_failed');
            $tables=[
                'bb_event_links'=>['event_id'=>'bigint unsigned','origin_id'=>'bigint unsigned','issuer_id'=>'bigint unsigned','linked_by'=>'bigint unsigned','created_at'=>'datetime'],
                'bb_event_reviews'=>['id'=>'bigint unsigned','event_id'=>'bigint unsigned','issuer_id'=>'bigint unsigned','actor_id'=>'bigint unsigned','decision'=>'varchar(32)','reason'=>'text','evidence_reference'=>'varchar(191)','review_key'=>'char(64)','created_at'=>'datetime'],
                'bb_event_receipts'=>['event_id'=>'bigint unsigned','origin_id'=>'bigint unsigned','issuer_id'=>'bigint unsigned','canonical_key'=>'char(64)','payment_id'=>'int','evidence_hash'=>'char(64)','created_by'=>'bigint unsigned','created_at'=>'datetime'],
            ];
            foreach($tables as $suffix=>$fields){
                $table=$prefix.$suffix;$meta=$db->query('SELECT ENGINE,TABLE_COLLATION FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?',[$table])->row_array();
                if(strtoupper($meta['ENGINE']??'')!=='INNODB'||($meta['TABLE_COLLATION']??null)!=='utf8mb4_bin')throw new \RuntimeException('bb_reconciliation_schema_invalid');
                $columns=[];foreach($db->query('SELECT COLUMN_NAME,COLUMN_TYPE,IS_NULLABLE,COLLATION_NAME,EXTRA FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?',[$table])->result_array() as $row)$columns[$row['COLUMN_NAME']]=$row;
                foreach($fields as $field=>$type){$c=$columns[$field]??[];if(preg_replace('/^(bigint|int)\([0-9]+\)/','$1',strtolower($c['COLUMN_TYPE']??''))!==$type||($c['IS_NULLABLE']??null)!=='NO'||(preg_match('/char|text/',$type)&&($c['COLLATION_NAME']??null)!=='utf8mb4_bin'))throw new \RuntimeException('bb_reconciliation_schema_invalid');}
                if($suffix==='bb_event_reviews'&&($columns['id']['EXTRA']??null)!=='auto_increment')throw new \RuntimeException('bb_reconciliation_schema_invalid');
                $keys=[];foreach($db->query('SELECT INDEX_NAME,NON_UNIQUE,COLUMN_NAME,SUB_PART FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? ORDER BY INDEX_NAME,SEQ_IN_INDEX',[$table])->result_array() as $key){if($key['SUB_PART']!==null)throw new \RuntimeException('bb_reconciliation_schema_invalid');if((int)$key['NON_UNIQUE']===0)$keys[$key['INDEX_NAME']][]=$key['COLUMN_NAME'];}
                $expected=['PRIMARY'=>[$suffix==='bb_event_reviews'?'id':'event_id']];
                if($suffix==='bb_event_reviews')$expected['review_identity']=['review_key'];
                if($suffix==='bb_event_receipts')$expected+=['canonical_receipt'=>['canonical_key'],'native_payment'=>['payment_id']];
                foreach($expected as $key=>$columns)if(($keys[$key]??null)!==$columns)throw new \RuntimeException('bb_reconciliation_schema_invalid');
                foreach(array_merge(['event_id'=>'bb_return_events'],$suffix==='bb_event_reviews'?[]:['origin_id'=>'bb_origins']) as $column=>$target){
                    $fk=$db->query('SELECT REFERENCED_TABLE_NAME,REFERENCED_COLUMN_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=? AND REFERENCED_TABLE_SCHEMA=DATABASE()',[$table,$column])->result_array();
                    if(count($fk)!==1||$fk[0]['REFERENCED_TABLE_NAME']!==$prefix.$target||$fk[0]['REFERENCED_COLUMN_NAME']!=='id')throw new \RuntimeException('bb_reconciliation_schema_invalid');
                }
            }
        });
    }
}
