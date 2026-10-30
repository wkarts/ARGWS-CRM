<?php

declare(strict_types=1);
require_once __DIR__.'/issuers/IssuerStorage.php';

/** Explicit standalone task billing only. Installation never declares a price or adopts legacy work. */
final class CompanyStandaloneBillingSchema
{
    public static function definitions():array
    {
        return [
            'company_standalone_billing_origins'=>['id'=>'int unsigned AUTO_INCREMENT','issuer_id'=>'int unsigned','task_id'=>'int unsigned','actor_id'=>'int','request_key'=>'varchar(64)','input_hash'=>'varchar(64)','source_json'=>'longtext','source_hash'=>'varchar(64)','payload_json'=>'longtext','payload_hash'=>'varchar(64)','context_json'=>'longtext','origin_hash'=>'varchar(64)','created_at'=>'datetime'],
            'company_standalone_billing_links'=>['task_id'=>'int unsigned','issuer_id'=>'int unsigned','origin_id'=>'int unsigned','state'=>'varchar(16)','conversion_actor_id'=>'int','invoice_id'=>'int NULL','updated_at'=>'datetime'],
        ];
    }
    public static function indexes(string $table):array
    {
        return $table==='company_standalone_billing_origins'?['PRIMARY'=>[['id'],0],'billing_request'=>[['request_key'],0],'billing_origin'=>[['issuer_id','task_id','id'],0]]:['PRIMARY'=>[['task_id'],0],'billing_invoice'=>[['invoice_id'],0],'billing_state'=>[['issuer_id','state','task_id'],1]];
    }
    public static function ensure($db,string $p):void
    {
        if(!preg_match('/^[A-Za-z0-9_]+$/D',$p))throw new InvalidArgumentException('Invalid prefix');
        foreach(self::definitions() as $table=>$fields){$parts=[];foreach($fields as $field=>$type){$nullable=str_ends_with($type,' NULL');$parts[]='`'.$field.'` '.str_replace(' NULL','',$type).(str_starts_with($type,'varchar')||str_contains($type,'text')?' CHARACTER SET utf8mb4 COLLATE utf8mb4_bin':'').($nullable?' NULL':' NOT NULL');}
            foreach(self::indexes($table) as $key=>[$cols,$nonUnique])$parts[]=($key==='PRIMARY'?'PRIMARY KEY':($nonUnique?'KEY':'UNIQUE KEY').' `'.$key.'`').' (`'.implode('`,`',$cols).'`)';
            $parts[]='FOREIGN KEY (`issuer_id`) REFERENCES `'.$p.'issuers_registry` (`id`)';$parts[]='FOREIGN KEY (`issuer_id`,`task_id`) REFERENCES `'.$p.'company_standalone_tasks` (`issuer_id`,`id`)';
            if($table==='company_standalone_billing_links'){$parts[]='FOREIGN KEY (`issuer_id`,`task_id`,`origin_id`) REFERENCES `'.$p.'company_standalone_billing_origins` (`issuer_id`,`task_id`,`id`)';$parts[]='FOREIGN KEY (`invoice_id`) REFERENCES `'.$p.'invoices` (`id`)';}
            $fk=0;foreach($parts as &$part)if(str_starts_with($part,'FOREIGN KEY'))$part='CONSTRAINT `standalone_'.substr(hash('sha256',$p.$table),0,20).'_'.(++$fk).'` '.$part;unset($part);IssuerStorage::query($db,'CREATE TABLE IF NOT EXISTS `'.$p.$table.'` ('.implode(',',$parts).') ENGINE=InnoDB');
        }self::assertValid($db,$p);
    }
    public static function assertValid($db,string $p):void
    {
        if(!preg_match('/^[A-Za-z0-9_]+$/D',$p))throw new InvalidArgumentException('Invalid prefix');$conn=$db->pdo??$db->conn_id??null;if($conn instanceof PDO&&$conn->getAttribute(PDO::ATTR_DRIVER_NAME)==='sqlite')return;
        foreach(self::definitions() as $table=>$fields){$columns=[];foreach(IssuerStorage::query($db,'SELECT COLUMN_NAME,COLUMN_TYPE,IS_NULLABLE,COLUMN_DEFAULT,COLLATION_NAME,EXTRA FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?',[$p.$table])->result_array() as $r)$columns[$r['COLUMN_NAME']]=$r;if(array_diff(array_keys($columns),array_keys($fields)))throw new RuntimeException('issuer_standalone_billing_schema_invalid');
            foreach($fields as $field=>$type){$r=$columns[$field]??[];$auto=str_contains($type,' AUTO_INCREMENT');$null=str_ends_with($type,' NULL');$type=str_replace([' AUTO_INCREMENT',' NULL'],'',$type);$actual=preg_replace('/^(tinyint|int|bigint)\([0-9]+\)/','$1',strtolower($r['COLUMN_TYPE']??''));if($actual!==$type||($r['IS_NULLABLE']??null)!==($null?'YES':'NO')||!IssuerStorage::sqlNullDefault($r)||($auto&&!str_contains(strtolower($r['EXTRA']??''),'auto_increment'))||((str_starts_with($type,'varchar')||str_contains($type,'text'))&&($r['COLLATION_NAME']??null)!=='utf8mb4_bin'))throw new RuntimeException('issuer_standalone_billing_schema_invalid');}
            $r=IssuerStorage::query($db,'SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?',[$p.$table])->row_array();if(strtoupper($r['ENGINE']??'')!=='INNODB')throw new RuntimeException('issuer_standalone_billing_schema_invalid');
            $indexes=[];foreach(IssuerStorage::query($db,'SELECT INDEX_NAME,COLUMN_NAME,NON_UNIQUE,SUB_PART FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? ORDER BY INDEX_NAME,SEQ_IN_INDEX',[$p.$table])->result_array() as $r){if($r['SUB_PART']!==null)throw new RuntimeException('issuer_standalone_billing_schema_invalid');$indexes[$r['INDEX_NAME']]['columns'][]=$r['COLUMN_NAME'];$indexes[$r['INDEX_NAME']]['non_unique']=(int)$r['NON_UNIQUE'];}foreach(self::indexes($table) as $name=>[$cols,$non])if(($indexes[$name]['columns']??[])!==$cols||($indexes[$name]['non_unique']??null)!==$non)throw new RuntimeException('issuer_standalone_billing_schema_invalid');
            $foreign=[];foreach(IssuerStorage::query($db,'SELECT CONSTRAINT_NAME,COLUMN_NAME,REFERENCED_TABLE_NAME,REFERENCED_COLUMN_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND REFERENCED_TABLE_SCHEMA=DATABASE() ORDER BY CONSTRAINT_NAME,ORDINAL_POSITION',[$p.$table])->result_array() as $r)$foreign[$r['CONSTRAINT_NAME']][]=[$r['COLUMN_NAME'],$r['REFERENCED_TABLE_NAME'],$r['REFERENCED_COLUMN_NAME']];$need=[[['issuer_id',$p.'issuers_registry','id']],[['issuer_id',$p.'company_standalone_tasks','issuer_id'],['task_id',$p.'company_standalone_tasks','id']]];if($table==='company_standalone_billing_links'){$need[]=[['issuer_id',$p.'company_standalone_billing_origins','issuer_id'],['task_id',$p.'company_standalone_billing_origins','task_id'],['origin_id',$p.'company_standalone_billing_origins','id']];$need[]=[['invoice_id',$p.'invoices','id']];}foreach($need as $fk)if(!in_array($fk,array_values($foreign),true))throw new RuntimeException('issuer_standalone_billing_schema_invalid');
        }
    }
}
