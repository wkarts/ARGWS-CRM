<?php

declare(strict_types=1);
require_once __DIR__.'/issuers/IssuerStorage.php';

/** Durable notification receipts survive queue cleanup; no email address or bearer is stored here. */
final class CompanyInvoiceDeliverySchema
{
    private const FIELDS=['request_key'=>'varchar(64)','issuer_id'=>'int unsigned','actor_id'=>'int','invoice_id'=>'int','contact_id'=>'int','revision'=>'varchar(64)','recipient_hash'=>'varchar(64)','queue_id'=>'int','created_at'=>'datetime'];
    public static function ensure($db,string $prefix):void
    {
        if(!preg_match('/^[A-Za-z0-9_]+$/D',$prefix))throw new InvalidArgumentException('Invalid table prefix');
        $table=$prefix.'company_invoice_deliveries';$parts=[];
        foreach(self::FIELDS as $field=>$type)$parts[]='`'.$field.'` '.$type.(in_array($field,['request_key','revision','recipient_hash'],true)?' CHARACTER SET ascii COLLATE ascii_bin':'').' NOT NULL'.($field==='request_key'?' PRIMARY KEY':'');
        $parts[]='UNIQUE KEY `delivery_queue` (`queue_id`)';$parts[]='KEY `delivery_invoice` (`issuer_id`,`invoice_id`)';
        $parts[]='FOREIGN KEY (`issuer_id`) REFERENCES `'.$prefix.'issuers_registry` (`id`)';
        $parts[]='FOREIGN KEY (`invoice_id`) REFERENCES `'.$prefix.'invoices` (`id`)';
        // Queue cleanup and contact removal must not delete the immutable notification receipt.
        IssuerStorage::query($db,'CREATE TABLE IF NOT EXISTS `'.$table.'` ('.implode(',',$parts).') ENGINE=InnoDB');
        self::assertValid($db,$prefix);
    }
    public static function assertValid($db,string $prefix):void
    {
        if(!preg_match('/^[A-Za-z0-9_]+$/D',$prefix))throw new InvalidArgumentException('Invalid table prefix');
        $connection=$db->pdo??$db->conn_id??null;if($connection instanceof PDO&&$connection->getAttribute(PDO::ATTR_DRIVER_NAME)==='sqlite')return;
        $table=$prefix.'company_invoice_deliveries';$columns=[];
        foreach(IssuerStorage::query($db,'SELECT COLUMN_NAME,COLUMN_TYPE,IS_NULLABLE,COLUMN_DEFAULT,COLLATION_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?',[$table])->result_array() as $row)$columns[$row['COLUMN_NAME']]=$row;
        foreach(self::FIELDS as $field=>$type){$row=$columns[$field]??[];
            if(preg_replace('/^int\([0-9]+\)/','int',strtolower($row['COLUMN_TYPE']??''))!==$type||($row['IS_NULLABLE']??null)!=='NO'||!IssuerStorage::sqlNullDefault($row)||(in_array($field,['request_key','revision','recipient_hash'],true)&&($row['COLLATION_NAME']??null)!=='ascii_bin'))throw new RuntimeException('issuer_invoice_delivery_schema_invalid');
        }
        $indexes=[];
        foreach(IssuerStorage::query($db,'SELECT INDEX_NAME,COLUMN_NAME,NON_UNIQUE,SUB_PART FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? ORDER BY INDEX_NAME,SEQ_IN_INDEX',[$table])->result_array() as $row){
            if($row['SUB_PART']!==null)throw new RuntimeException('issuer_invoice_delivery_schema_invalid');$indexes[$row['INDEX_NAME']]['columns'][]=$row['COLUMN_NAME'];$indexes[$row['INDEX_NAME']]['non_unique']=(int)$row['NON_UNIQUE'];
        }
        foreach(['PRIMARY'=>[['request_key'],0],'delivery_queue'=>[['queue_id'],0],'delivery_invoice'=>[['issuer_id','invoice_id'],1]] as $name=>[$fields,$nonUnique])if(($indexes[$name]['columns']??[])!==$fields||($indexes[$name]['non_unique']??null)!==$nonUnique)throw new RuntimeException('issuer_invoice_delivery_schema_invalid');
        $engine=IssuerStorage::query($db,'SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?',[$table])->row_array();
        if(strtoupper($engine['ENGINE']??'')!=='INNODB')throw new RuntimeException('issuer_invoice_delivery_schema_invalid');
        foreach(['issuer_id'=>['issuers_registry','id'],'invoice_id'=>['invoices','id']] as $column=>[$target,$key]){
            $fk=IssuerStorage::query($db,'SELECT REFERENCED_TABLE_NAME,REFERENCED_COLUMN_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=? AND REFERENCED_TABLE_SCHEMA=DATABASE()',[$table,$column])->result_array();
            if(count($fk)!==1||$fk[0]['REFERENCED_TABLE_NAME']!==$prefix.$target||$fk[0]['REFERENCED_COLUMN_NAME']!==$key)throw new RuntimeException('issuer_invoice_delivery_schema_invalid');
        }
    }
}
