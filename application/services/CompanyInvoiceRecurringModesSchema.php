<?php

declare(strict_types=1);
require_once __DIR__.'/issuers/IssuerStorage.php';require_once __DIR__.'/issuers/IssuerTransaction.php';

/** Additive immutable recurrence modes; historical schedules are not adopted. */
final class CompanyInvoiceRecurringModesSchema
{
    public static function definitions():array
    {
        return ['company_invoice_schedule_modes'=>['issuer_id'=>'int unsigned','schedule_id'=>'int unsigned','actor_id'=>'int','mode'=>'varchar(16)','principal_invoice_id'=>'int NULL','principal_hash'=>'varchar(64)','payload_hash'=>'varchar(64)','context_hash'=>'varchar(64)','config_hash'=>'varchar(64)','transport_hash'=>'varchar(64)','mode_hash'=>'varchar(64)']];
    }
    public static function indexes(string $table):array
    {
        if($table!=='company_invoice_schedule_modes')throw new InvalidArgumentException('Invalid table');return ['PRIMARY'=>[['issuer_id','schedule_id'],0],'invoice_mode_schedule'=>[['schedule_id'],0]];
    }
    public static function ensure($db,string $p):void
    {
        if(!preg_match('/^[A-Za-z0-9_]+$/D',$p))throw new InvalidArgumentException('Invalid prefix');IssuerTransaction::assertIndependent($db);
        foreach(self::definitions() as $table=>$fields){$parts=[];foreach($fields as $field=>$type){$nullable=str_ends_with($type,' NULL');$parts[]='`'.$field.'` '.str_replace(' NULL','',$type).(str_starts_with($type,'varchar')||str_contains($type,'text')?' CHARACTER SET utf8mb4 COLLATE utf8mb4_bin':'').($nullable?' NULL':' NOT NULL');}
            foreach(self::indexes($table) as $key=>[$cols,$nonUnique])$parts[]=($key==='PRIMARY'?'PRIMARY KEY':($nonUnique?'KEY':'UNIQUE KEY').' `'.$key.'`').' (`'.implode('`,`',$cols).'`)';
            $parts[]='FOREIGN KEY (`issuer_id`) REFERENCES `'.$p.'issuers_registry` (`id`)';$parts[]='FOREIGN KEY (`issuer_id`,`schedule_id`) REFERENCES `'.$p.'company_invoice_schedules` (`issuer_id`,`id`)';$parts[]='FOREIGN KEY (`principal_invoice_id`) REFERENCES `'.$p.'invoices` (`id`)';
            IssuerStorage::query($db,'CREATE TABLE IF NOT EXISTS `'.$p.$table.'` ('.implode(',',$parts).') ENGINE=InnoDB');
        }if(property_exists($db,'data_cache'))unset($db->data_cache['table_names']);self::assertValid($db,$p);
    }
    public static function assertValid($db,string $p):void
    {
        if(!preg_match('/^[A-Za-z0-9_]+$/D',$p))throw new InvalidArgumentException('Invalid prefix');$conn=$db->pdo??$db->conn_id??null;if($conn instanceof PDO&&$conn->getAttribute(PDO::ATTR_DRIVER_NAME)==='sqlite')return;
        foreach(self::definitions() as $table=>$fields){$columns=[];foreach(IssuerStorage::query($db,'SELECT COLUMN_NAME,COLUMN_TYPE,IS_NULLABLE,COLUMN_DEFAULT,COLLATION_NAME,EXTRA FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?',[$p.$table])->result_array() as $r)$columns[$r['COLUMN_NAME']]=$r;
            foreach($fields as $field=>$type){$r=$columns[$field]??[];$auto=str_contains($type,' AUTO_INCREMENT');$null=str_ends_with($type,' NULL');$type=str_replace([' AUTO_INCREMENT',' NULL'],'',$type);$actual=preg_replace('/^(tinyint|int|bigint)\([0-9]+\)/','$1',strtolower($r['COLUMN_TYPE']??''));if($actual!==$type||($r['IS_NULLABLE']??null)!==($null?'YES':'NO')||!IssuerStorage::sqlNullDefault($r)||($auto&&!str_contains(strtolower($r['EXTRA']??''),'auto_increment'))||((str_starts_with($type,'varchar')||str_contains($type,'text'))&&($r['COLLATION_NAME']??null)!=='utf8mb4_bin'))throw new RuntimeException('issuer_invoice_recurring_modes_schema_invalid');}
            $r=IssuerStorage::query($db,'SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?',[$p.$table])->row_array();if(strtoupper($r['ENGINE']??'')!=='INNODB')throw new RuntimeException('issuer_invoice_recurring_modes_schema_invalid');
            $indexes=[];foreach(IssuerStorage::query($db,'SELECT INDEX_NAME,COLUMN_NAME,NON_UNIQUE,SUB_PART FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? ORDER BY INDEX_NAME,SEQ_IN_INDEX',[$p.$table])->result_array() as $r){if($r['SUB_PART']!==null)throw new RuntimeException('issuer_invoice_recurring_modes_schema_invalid');$indexes[$r['INDEX_NAME']]['columns'][]=$r['COLUMN_NAME'];$indexes[$r['INDEX_NAME']]['non_unique']=(int)$r['NON_UNIQUE'];}foreach(self::indexes($table) as $name=>[$cols,$non])if(($indexes[$name]['columns']??[])!==$cols||($indexes[$name]['non_unique']??null)!==$non)throw new RuntimeException('issuer_invoice_recurring_modes_schema_invalid');
            $foreign=[];foreach(IssuerStorage::query($db,'SELECT CONSTRAINT_NAME,COLUMN_NAME,REFERENCED_TABLE_NAME,REFERENCED_COLUMN_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND REFERENCED_TABLE_SCHEMA=DATABASE() ORDER BY CONSTRAINT_NAME,ORDINAL_POSITION',[$p.$table])->result_array() as $r)$foreign[$r['CONSTRAINT_NAME']][]=[$r['COLUMN_NAME'],$r['REFERENCED_TABLE_NAME'],$r['REFERENCED_COLUMN_NAME']];$need=[[['issuer_id',$p.'issuers_registry','id']]];$need[]=[['issuer_id',$p.'company_invoice_schedules','issuer_id'],['schedule_id',$p.'company_invoice_schedules','id']];$need[]=[['principal_invoice_id',$p.'invoices','id']];foreach($need as $fk)if(!in_array($fk,array_values($foreign),true))throw new RuntimeException('issuer_invoice_recurring_modes_schema_invalid');
        }
    }
}
