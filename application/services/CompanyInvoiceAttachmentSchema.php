<?php

declare(strict_types=1);
require_once __DIR__.'/issuers/IssuerStorage.php';
final class CompanyInvoiceAttachmentSchema
{
    private const FIELDS=['file_key'=>'varchar(64)','issuer_id'=>'int unsigned','actor_id'=>'int','invoice_id'=>'int','filename'=>'varchar(191)','mime'=>'varchar(40)','content_hash'=>'varchar(64)','byte_size'=>'int unsigned','content'=>'mediumblob','portal_visible'=>'tinyint','active'=>'tinyint','created_at'=>'datetime'];
    public static function ensure($db,string $prefix):void
    {
        if(!preg_match('/^[A-Za-z0-9_]+$/D',$prefix))throw new InvalidArgumentException('Invalid table prefix');$parts=[];
        foreach(self::FIELDS as $name=>$type)$parts[]='`'.$name.'` '.$type.(in_array($name,['file_key','content_hash'],true)?' CHARACTER SET ascii COLLATE ascii_bin':(in_array($name,['filename','mime'],true)?' CHARACTER SET utf8mb4 COLLATE utf8mb4_bin':'')).' NOT NULL'.($name==='file_key'?' PRIMARY KEY':'');
        $parts[]='KEY `attachment_parent` (`issuer_id`,`invoice_id`,`active`)';$parts[]='FOREIGN KEY (`issuer_id`) REFERENCES `'.$prefix.'issuers_registry` (`id`)';$parts[]='FOREIGN KEY (`invoice_id`) REFERENCES `'.$prefix.'invoices` (`id`)';
        IssuerStorage::query($db,'CREATE TABLE IF NOT EXISTS `'.$prefix.'company_invoice_attachments` ('.implode(',',$parts).') ENGINE=InnoDB');self::assertValid($db,$prefix);
    }
    public static function assertValid($db,string $prefix):void
    {
        if(!preg_match('/^[A-Za-z0-9_]+$/D',$prefix))throw new InvalidArgumentException('Invalid table prefix');$conn=$db->pdo??$db->conn_id??null;if($conn instanceof PDO&&$conn->getAttribute(PDO::ATTR_DRIVER_NAME)==='sqlite')return;
        $table=$prefix.'company_invoice_attachments';$columns=[];
        foreach(IssuerStorage::query($db,'SELECT COLUMN_NAME,COLUMN_TYPE,IS_NULLABLE,COLUMN_DEFAULT,COLLATION_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?',[$table])->result_array() as $row)$columns[$row['COLUMN_NAME']]=$row;
        foreach(self::FIELDS as $name=>$type){$row=$columns[$name]??[];$actual=preg_replace('/^(tinyint|int)\([0-9]+\)/','$1',strtolower($row['COLUMN_TYPE']??''));$collation=in_array($name,['file_key','content_hash'],true)?'ascii_bin':(in_array($name,['filename','mime'],true)?'utf8mb4_bin':null);
            if($actual!==$type||($row['IS_NULLABLE']??null)!=='NO'||!IssuerStorage::sqlNullDefault($row)||($collation!==null&&($row['COLLATION_NAME']??null)!==$collation))throw new RuntimeException('issuer_invoice_attachment_schema_invalid');
        }
        $indexes=[];foreach(IssuerStorage::query($db,'SELECT INDEX_NAME,COLUMN_NAME,NON_UNIQUE,SUB_PART FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? ORDER BY INDEX_NAME,SEQ_IN_INDEX',[$table])->result_array() as $row){if($row['SUB_PART']!==null)throw new RuntimeException('issuer_invoice_attachment_schema_invalid');$indexes[$row['INDEX_NAME']]['columns'][]=$row['COLUMN_NAME'];$indexes[$row['INDEX_NAME']]['non_unique']=(int)$row['NON_UNIQUE'];}
        foreach(['PRIMARY'=>[['file_key'],0],'attachment_parent'=>[['issuer_id','invoice_id','active'],1]] as $name=>[$fields,$nonUnique])if(($indexes[$name]['columns']??[])!==$fields||($indexes[$name]['non_unique']??null)!==$nonUnique)throw new RuntimeException('issuer_invoice_attachment_schema_invalid');
        $engine=IssuerStorage::query($db,'SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?',[$table])->row_array();if(strtoupper($engine['ENGINE']??'')!=='INNODB')throw new RuntimeException('issuer_invoice_attachment_schema_invalid');
        foreach(['issuer_id'=>['issuers_registry','id'],'invoice_id'=>['invoices','id']] as $column=>[$target,$key]){$rows=IssuerStorage::query($db,'SELECT REFERENCED_TABLE_NAME,REFERENCED_COLUMN_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=? AND REFERENCED_TABLE_SCHEMA=DATABASE()',[$table,$column])->result_array();if(count($rows)!==1||$rows[0]['REFERENCED_TABLE_NAME']!==$prefix.$target||$rows[0]['REFERENCED_COLUMN_NAME']!==$key)throw new RuntimeException('issuer_invoice_attachment_schema_invalid');}
    }
}
