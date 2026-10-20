<?php

declare(strict_types=1);
require_once __DIR__.'/issuers/IssuerStorage.php';require_once __DIR__.'/issuers/IssuerTransaction.php';

/** Explicit expense schedules and durable occurrences only. No adoption of legacy recurring costs. */
final class CompanyExpenseRecurringSchema
{
    public static function definitions():array
    {
        return [
            'company_expense_recurring'=>['id'=>'int unsigned AUTO_INCREMENT','issuer_id'=>'int unsigned','actor_id'=>'int','source_expense_id'=>'int unsigned','source_json'=>'longtext','source_hash'=>'varchar(64)','reference_json'=>'longtext','reference_hash'=>'varchar(64)','billing_json'=>'longtext','billing_hash'=>'varchar(64)','interval_unit'=>'varchar(8)','interval_value'=>'int unsigned','custom'=>'tinyint','max_cycles'=>'int unsigned','completed_cycles'=>'int unsigned','next_date'=>'date','anchor_day'=>'int unsigned','anchor_month'=>'int unsigned','state'=>'varchar(24)','revision'=>'int unsigned','last_error'=>'varchar(40)','request_key'=>'varchar(64)','input_hash'=>'varchar(64)','created_at'=>'datetime','updated_at'=>'datetime'],
            'company_expense_occurrences'=>['request_key'=>'varchar(64)','issuer_id'=>'int unsigned','schedule_id'=>'int unsigned','actor_id'=>'int','run_date'=>'date','state'=>'varchar(24)','expense_id'=>'int unsigned NULL','expense_hash'=>'varchar(64)','billing_origin_id'=>'int unsigned NULL','invoice_id'=>'int NULL','invoice_revision'=>'varchar(64)','delivery_key'=>'varchar(64)','queue_id'=>'int NULL','created_at'=>'datetime','updated_at'=>'datetime'],
            'company_expense_recurring_requests'=>['request_key'=>'varchar(64)','issuer_id'=>'int unsigned','actor_id'=>'int','operation'=>'varchar(16)','input_hash'=>'varchar(64)','schedule_id'=>'int unsigned','created_at'=>'datetime'],
        ];
    }
    public static function indexes(string $table):array
    {
        return match($table){
            'company_expense_recurring'=>['PRIMARY'=>[['id'],0],'expense_schedule_request'=>[['request_key'],0],'expense_schedule_owner'=>[['issuer_id','id'],0],'expense_schedule_pending'=>[['state','next_date','id'],1]],
            'company_expense_occurrences'=>['PRIMARY'=>[['request_key'],0],'expense_occurrence_date'=>[['issuer_id','schedule_id','run_date'],0],'expense_occurrence_child'=>[['expense_id'],0],'expense_occurrence_invoice'=>[['invoice_id'],0],'expense_occurrence_queue'=>[['queue_id'],0],'expense_occurrence_delivery'=>[['delivery_key'],0]],
            'company_expense_recurring_requests'=>['PRIMARY'=>[['request_key'],0]],
            default=>throw new InvalidArgumentException('Invalid table'),
        };
    }
    public static function ensure($db,string $p):void
    {
        if(!preg_match('/^[A-Za-z0-9_]+$/D',$p))throw new InvalidArgumentException('Invalid prefix');IssuerTransaction::assertIndependent($db);
        foreach(self::definitions() as $table=>$fields){$parts=[];foreach($fields as $field=>$type){$nullable=str_ends_with($type,' NULL');$parts[]='`'.$field.'` '.str_replace(' NULL','',$type).(str_starts_with($type,'varchar')||str_contains($type,'text')?' CHARACTER SET utf8mb4 COLLATE utf8mb4_bin':'').($nullable?' NULL':' NOT NULL');}
            foreach(self::indexes($table) as $key=>[$cols,$nonUnique])$parts[]=($key==='PRIMARY'?'PRIMARY KEY':($nonUnique?'KEY':'UNIQUE KEY').' `'.$key.'`').' (`'.implode('`,`',$cols).'`)';
            $parts[]='FOREIGN KEY (`issuer_id`) REFERENCES `'.$p.'issuers_registry` (`id`)';if($table==='company_expense_recurring')$parts[]='FOREIGN KEY (`source_expense_id`) REFERENCES `'.$p.'company_expenses` (`id`)';else $parts[]='FOREIGN KEY (`issuer_id`,`schedule_id`) REFERENCES `'.$p.'company_expense_recurring` (`issuer_id`,`id`)';
            if($table==='company_expense_occurrences'){$parts[]='FOREIGN KEY (`expense_id`) REFERENCES `'.$p.'company_expenses` (`id`)';$parts[]='FOREIGN KEY (`billing_origin_id`) REFERENCES `'.$p.'company_expense_billing_origins` (`id`)';$parts[]='FOREIGN KEY (`invoice_id`) REFERENCES `'.$p.'invoices` (`id`)';}
            IssuerStorage::query($db,'CREATE TABLE IF NOT EXISTS `'.$p.$table.'` ('.implode(',',$parts).') ENGINE=InnoDB');
        }self::assertValid($db,$p);
    }
    public static function assertValid($db,string $p):void
    {
        if(!preg_match('/^[A-Za-z0-9_]+$/D',$p))throw new InvalidArgumentException('Invalid prefix');$conn=$db->pdo??$db->conn_id??null;if($conn instanceof PDO&&$conn->getAttribute(PDO::ATTR_DRIVER_NAME)==='sqlite')return;
        foreach(self::definitions() as $table=>$fields){$columns=[];foreach(IssuerStorage::query($db,'SELECT COLUMN_NAME,COLUMN_TYPE,IS_NULLABLE,COLUMN_DEFAULT,COLLATION_NAME,EXTRA FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?',[$p.$table])->result_array() as $r)$columns[$r['COLUMN_NAME']]=$r;
            foreach($fields as $field=>$type){$r=$columns[$field]??[];$auto=str_contains($type,' AUTO_INCREMENT');$null=str_ends_with($type,' NULL');$type=str_replace([' AUTO_INCREMENT',' NULL'],'',$type);$actual=preg_replace('/^(tinyint|int|bigint)\([0-9]+\)/','$1',strtolower($r['COLUMN_TYPE']??''));if($actual!==$type||($r['IS_NULLABLE']??null)!==($null?'YES':'NO')||!IssuerStorage::sqlNullDefault($r)||($auto&&!str_contains(strtolower($r['EXTRA']??''),'auto_increment'))||((str_starts_with($type,'varchar')||str_contains($type,'text'))&&($r['COLLATION_NAME']??null)!=='utf8mb4_bin'))throw new RuntimeException('issuer_expense_recurring_schema_invalid');}
            $r=IssuerStorage::query($db,'SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?',[$p.$table])->row_array();if(strtoupper($r['ENGINE']??'')!=='INNODB')throw new RuntimeException('issuer_expense_recurring_schema_invalid');
            $indexes=[];foreach(IssuerStorage::query($db,'SELECT INDEX_NAME,COLUMN_NAME,NON_UNIQUE,SUB_PART FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? ORDER BY INDEX_NAME,SEQ_IN_INDEX',[$p.$table])->result_array() as $r){if($r['SUB_PART']!==null)throw new RuntimeException('issuer_expense_recurring_schema_invalid');$indexes[$r['INDEX_NAME']]['columns'][]=$r['COLUMN_NAME'];$indexes[$r['INDEX_NAME']]['non_unique']=(int)$r['NON_UNIQUE'];}foreach(self::indexes($table) as $name=>[$cols,$non])if(($indexes[$name]['columns']??[])!==$cols||($indexes[$name]['non_unique']??null)!==$non)throw new RuntimeException('issuer_expense_recurring_schema_invalid');
            $foreign=[];foreach(IssuerStorage::query($db,'SELECT CONSTRAINT_NAME,COLUMN_NAME,REFERENCED_TABLE_NAME,REFERENCED_COLUMN_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND REFERENCED_TABLE_SCHEMA=DATABASE() ORDER BY CONSTRAINT_NAME,ORDINAL_POSITION',[$p.$table])->result_array() as $r)$foreign[$r['CONSTRAINT_NAME']][]=[$r['COLUMN_NAME'],$r['REFERENCED_TABLE_NAME'],$r['REFERENCED_COLUMN_NAME']];$need=[[['issuer_id',$p.'issuers_registry','id']]];if($table==='company_expense_recurring')$need[]=[['source_expense_id',$p.'company_expenses','id']];else $need[]=[['issuer_id',$p.'company_expense_recurring','issuer_id'],['schedule_id',$p.'company_expense_recurring','id']];if($table==='company_expense_occurrences'){foreach(['expense_id'=>['company_expenses','id'],'billing_origin_id'=>['company_expense_billing_origins','id'],'invoice_id'=>['invoices','id']] as $c=>[$t,$k])$need[]=[[$c,$p.$t,$k]];}foreach($need as $fk)if(!in_array($fk,array_values($foreign),true))throw new RuntimeException('issuer_expense_recurring_schema_invalid');
        }
    }
}
