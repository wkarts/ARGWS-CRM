<?php

declare(strict_types=1);
require_once __DIR__.'/issuers/IssuerStorage.php';
require_once __DIR__.'/issuers/IssuerTransaction.php';

/** Traversal cursor only. No company permission, invoice authority or financial position is stored here. */
final class CompanyInvoiceRecurringDiscoverySchema
{
    public static function definitions():array
    {
        return ['company_invoice_schedule_discovery'=>['id'=>'tinyint unsigned','cursor_id'=>'int unsigned','high_water_id'=>'int unsigned','revision'=>'int unsigned','batch_hash'=>'varchar(64)','audit_id'=>'bigint unsigned','created_at'=>'datetime','updated_at'=>'datetime']];
    }
    public static function indexes(string$table):array
    {
        if($table!=='company_invoice_schedule_discovery')throw new InvalidArgumentException('Invalid table');return['PRIMARY'=>[['id'],0]];
    }
    public static function ensure($db,string$p):void
    {
        if(!preg_match('/^[A-Za-z0-9_]+$/D',$p))throw new InvalidArgumentException('Invalid prefix');IssuerTransaction::assertIndependent($db);
        IssuerStorage::query($db,'CREATE TABLE IF NOT EXISTS `'.$p.'company_invoice_schedule_discovery` (`id` tinyint unsigned NOT NULL,`cursor_id` int unsigned NOT NULL,`high_water_id` int unsigned NOT NULL,`revision` int unsigned NOT NULL,`batch_hash` varchar(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,`audit_id` bigint unsigned NOT NULL,`created_at` datetime NOT NULL,`updated_at` datetime NOT NULL,PRIMARY KEY (`id`),FOREIGN KEY (`audit_id`) REFERENCES `'.$p.'issuers_audit` (`id`)) ENGINE=InnoDB');
        if(property_exists($db,'data_cache'))unset($db->data_cache['table_names']);self::assertValid($db,$p);
    }
    public static function assertValid($db,string$p):void
    {
        if(!preg_match('/^[A-Za-z0-9_]+$/D',$p))throw new InvalidArgumentException('Invalid prefix');$conn=$db->pdo??$db->conn_id??null;if($conn instanceof PDO&&$conn->getAttribute(PDO::ATTR_DRIVER_NAME)==='sqlite')return;
        $table=$p.'company_invoice_schedule_discovery';$fields=self::definitions()['company_invoice_schedule_discovery'];$columns=[];
        foreach(IssuerStorage::query($db,'SELECT COLUMN_NAME,COLUMN_TYPE,IS_NULLABLE,COLUMN_DEFAULT,COLLATION_NAME,EXTRA FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?',[$table])->result_array()as$r)$columns[$r['COLUMN_NAME']]=$r;
        if(array_diff(array_keys($columns),array_keys($fields)))throw new RuntimeException('issuer_invoice_recurring_discovery_schema_invalid');
        foreach($fields as$field=>$type){$r=$columns[$field]??[];$actual=preg_replace('/^(tinyint|int|bigint)\([0-9]+\)/','$1',strtolower($r['COLUMN_TYPE']??''));if($actual!==$type||($r['IS_NULLABLE']??null)!=='NO'||!IssuerStorage::sqlNullDefault($r)||($r['EXTRA']??'')!==''||($field==='batch_hash'&&($r['COLLATION_NAME']??null)!=='ascii_bin'))throw new RuntimeException('issuer_invoice_recurring_discovery_schema_invalid');}
        $engine=IssuerStorage::query($db,'SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?',[$table])->row_array();if(strtoupper($engine['ENGINE']??'')!=='INNODB')throw new RuntimeException('issuer_invoice_recurring_discovery_schema_invalid');
        $primary=[];foreach(IssuerStorage::query($db,'SELECT INDEX_NAME,COLUMN_NAME,NON_UNIQUE,SUB_PART FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? ORDER BY INDEX_NAME,SEQ_IN_INDEX',[$table])->result_array()as$r){if($r['SUB_PART']!==null)throw new RuntimeException('issuer_invoice_recurring_discovery_schema_invalid');if($r['INDEX_NAME']==='PRIMARY'){$primary[]=$r['COLUMN_NAME'];if((int)$r['NON_UNIQUE']!==0)throw new RuntimeException('issuer_invoice_recurring_discovery_schema_invalid');}}
        if($primary!==['id'])throw new RuntimeException('issuer_invoice_recurring_discovery_schema_invalid');$foreign=[];
        foreach(IssuerStorage::query($db,'SELECT CONSTRAINT_NAME,COLUMN_NAME,REFERENCED_TABLE_NAME,REFERENCED_COLUMN_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND REFERENCED_TABLE_SCHEMA=DATABASE() ORDER BY CONSTRAINT_NAME,ORDINAL_POSITION',[$table])->result_array()as$r)$foreign[$r['CONSTRAINT_NAME']][]=[$r['COLUMN_NAME'],$r['REFERENCED_TABLE_NAME'],$r['REFERENCED_COLUMN_NAME']];
        if(!in_array([['audit_id',$p.'issuers_audit','id']],array_values($foreign),true))throw new RuntimeException('issuer_invoice_recurring_discovery_schema_invalid');
    }
}
