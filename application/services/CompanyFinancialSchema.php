<?php

declare(strict_types=1);
require_once __DIR__.'/issuers/IssuerStorage.php';
final class CompanyFinancialSchema
{
    public static function ensure($db,string $prefix):void
    {
        if(!preg_match('/^[A-Za-z0-9_]+$/D',$prefix))throw new InvalidArgumentException('Invalid table prefix');
        foreach(['company_invoice_requests'=>['request_key'=>'varchar(64)','issuer_id'=>'int unsigned','actor_id'=>'int','input_hash'=>'varchar(64)','invoice_id'=>'int','created_at'=>'datetime'],
            'company_payment_receipts'=>['request_key'=>'varchar(64)','issuer_id'=>'int unsigned','actor_id'=>'int','input_hash'=>'varchar(64)','invoice_id'=>'int','payment_id'=>'int','created_at'=>'datetime']] as $suffix=>$fields){
            $table=$prefix.$suffix;$parts=[];
            foreach($fields as $field=>$type)$parts[]='`'.$field.'` '.$type.(in_array($field,['request_key','input_hash'],true)?' CHARACTER SET ascii COLLATE ascii_bin':'').' NOT NULL'.($field==='request_key'?' PRIMARY KEY':'');
            $parts[]='FOREIGN KEY (`issuer_id`) REFERENCES `'.$prefix.'issuers_registry` (`id`)';
            $parts[]='FOREIGN KEY (`invoice_id`) REFERENCES `'.$prefix.'invoices` (`id`)';
            if($suffix==='company_payment_receipts')$parts[]='FOREIGN KEY (`payment_id`) REFERENCES `'.$prefix.'invoicepaymentrecords` (`id`)';
            IssuerStorage::query($db,'CREATE TABLE IF NOT EXISTS `'.$table.'` ('.implode(',',$parts).') ENGINE=InnoDB');
            $columns=[];foreach(IssuerStorage::query($db,'SELECT COLUMN_NAME,COLUMN_TYPE,IS_NULLABLE,COLLATION_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?',[$table])->result_array() as $row)$columns[$row['COLUMN_NAME']]=$row;
            foreach($fields as $field=>$type){$row=$columns[$field]??[];if(preg_replace('/^int\([0-9]+\)/','int',strtolower($row['COLUMN_TYPE']??''))!==$type||($row['IS_NULLABLE']??null)!=='NO'||(in_array($field,['request_key','input_hash'],true)&&($row['COLLATION_NAME']??null)!=='ascii_bin'))throw new RuntimeException('company_financial_schema_invalid');}
            $pk=IssuerStorage::query($db,"SELECT COLUMN_NAME,SUB_PART FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND INDEX_NAME='PRIMARY' ORDER BY SEQ_IN_INDEX",[$table])->result_array();
            $engine=IssuerStorage::query($db,'SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?',[$table])->result_array();
            if(array_column($pk,'COLUMN_NAME')!==['request_key']||($pk[0]['SUB_PART']??null)!==null||strtoupper($engine[0]['ENGINE']??'')!=='INNODB')throw new RuntimeException('company_financial_schema_invalid');
            foreach(array_merge(['issuer_id'=>['issuers_registry','id'],'invoice_id'=>['invoices','id']],$suffix==='company_payment_receipts'?['payment_id'=>['invoicepaymentrecords','id']]:[]) as $column=>[$target,$key]){
                $fk=IssuerStorage::query($db,'SELECT REFERENCED_TABLE_NAME,REFERENCED_COLUMN_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=? AND REFERENCED_TABLE_SCHEMA=DATABASE()',[$table,$column])->result_array();
                if(count($fk)!==1||$fk[0]['REFERENCED_TABLE_NAME']!==$prefix.$target||$fk[0]['REFERENCED_COLUMN_NAME']!==$key)throw new RuntimeException('company_financial_schema_invalid');
            }
        }
    }
}
