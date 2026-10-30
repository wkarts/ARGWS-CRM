<?php

declare(strict_types=1);
require_once __DIR__.'/issuers/IssuerStorage.php';

/** Durable create receipts. No customer PII is stored in the receipt. */
final class CompanyClientRequestSchema
{
    public static function ensure($db, string $prefix): void
    {
        if (!preg_match('/^[A-Za-z0-9_]+$/D', $prefix)) throw new InvalidArgumentException('Invalid table prefix');
        $table=$prefix.'company_client_requests';
        IssuerStorage::query($db,"CREATE TABLE IF NOT EXISTS `{$table}` (
            `request_key` varchar(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL PRIMARY KEY,
            `issuer_id` int unsigned NOT NULL, `actor_id` int NOT NULL,
            `input_hash` varchar(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
            `client_id` int NOT NULL, `created_at` datetime NOT NULL,
            FOREIGN KEY (`issuer_id`) REFERENCES `{$prefix}issuers_registry` (`id`)
        ) ENGINE=InnoDB");
        $columns=IssuerStorage::query($db,'SELECT COLUMN_NAME,COLUMN_TYPE,IS_NULLABLE,COLLATION_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?',[$table])->result_array();
        $actual=[];foreach($columns as $column)$actual[$column['COLUMN_NAME']]=$column;
        foreach(['request_key'=>'varchar(64)','issuer_id'=>'int unsigned','actor_id'=>'int','input_hash'=>'varchar(64)','client_id'=>'int','created_at'=>'datetime'] as $name=>$type){
            $column=$actual[$name]??[];
            if(preg_replace('/^int\([0-9]+\)/','int',strtolower($column['COLUMN_TYPE']??''))!==$type||($column['IS_NULLABLE']??null)!=='NO')throw new RuntimeException('company_client_request_schema_invalid');
        }
        foreach(['request_key','input_hash'] as $name)if(($actual[$name]['COLLATION_NAME']??null)!=='ascii_bin')throw new RuntimeException('company_client_request_schema_invalid');
        $pk=IssuerStorage::query($db,"SELECT COLUMN_NAME,SUB_PART FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND INDEX_NAME='PRIMARY' ORDER BY SEQ_IN_INDEX",[$table])->result_array();
        $engine=IssuerStorage::query($db,'SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?',[$table])->result_array();
        $fk=IssuerStorage::query($db,'SELECT REFERENCED_TABLE_NAME,REFERENCED_COLUMN_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=? AND REFERENCED_TABLE_SCHEMA=DATABASE()',[$table,'issuer_id'])->result_array();
        if(array_column($pk,'COLUMN_NAME')!==['request_key']||($pk[0]['SUB_PART']??null)!==null||strtoupper($engine[0]['ENGINE']??'')!=='INNODB'||count($fk)!==1||$fk[0]['REFERENCED_TABLE_NAME']!==$prefix.'issuers_registry'||$fk[0]['REFERENCED_COLUMN_NAME']!=='id')throw new RuntimeException('company_client_request_schema_invalid');
    }
}
