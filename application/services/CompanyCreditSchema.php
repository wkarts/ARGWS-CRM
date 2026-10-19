<?php

declare(strict_types=1);
require_once __DIR__.'/issuers/IssuerStorage.php';
/** Additive ownership/snapshot/ledger. Native legacy rows remain NULL-owned. */
final class CompanyCreditSchema
{
    private const DEFINITIONS=[
        'company_credit_documents'=>['credit_id'=>'int','issuer_id'=>'int unsigned','actor_id'=>'int','request_key'=>'varchar(64)','input_hash'=>'varchar(64)','number'=>'int unsigned','identity_json'=>'longtext','recipient_json'=>'longtext','items_json'=>'longtext','created_at'=>'datetime'],
        'company_credit_entries'=>['request_key'=>'varchar(64)','issuer_id'=>'int unsigned','actor_id'=>'int','kind'=>'varchar(20)','credit_id'=>'int NULL','invoice_id'=>'int NULL','native_id'=>'int','parent_key'=>'varchar(64) NULL','amount'=>'decimal(15,2)','input_hash'=>'varchar(64)','reference_hash'=>'varchar(64)','reference'=>'varchar(191)','book_date'=>'date','reason'=>'text','created_at'=>'datetime'],
    ];
    public static function ensure($db,string $p):void
    {
        if(!preg_match('/^[A-Za-z0-9_]+$/D',$p))throw new InvalidArgumentException('Invalid table prefix');
        if(!IssuerStorage::fieldExists($db,'issuer_id',$p.'creditnotes'))IssuerStorage::query($db,'ALTER TABLE `'.$p.'creditnotes` ADD issuer_id int unsigned NULL DEFAULT NULL');
        foreach(self::DEFINITIONS as $table=>$fields){$parts=[];
            foreach($fields as $name=>$type){$nullable=str_ends_with($type,' NULL');$type=str_replace(' NULL','',$type);$parts[]='`'.$name.'` '.$type.(in_array($name,['request_key','input_hash','reference_hash','parent_key'],true)?' CHARACTER SET ascii COLLATE ascii_bin':'').($nullable?' NULL DEFAULT NULL':' NOT NULL');}
            $parts[]='PRIMARY KEY (`'.($table==='company_credit_documents'?'credit_id':'request_key').'`)';
            $parts[]='FOREIGN KEY (`issuer_id`) REFERENCES `'.$p.'issuers_registry` (`id`)';$parts[]='FOREIGN KEY (`credit_id`) REFERENCES `'.$p.'creditnotes` (`id`)';
            if($table==='company_credit_documents'){$parts[]='UNIQUE KEY `credit_request` (`request_key`)';$parts[]='UNIQUE KEY `credit_number` (`issuer_id`,`number`)';}
            else{$parts[]='UNIQUE KEY `credit_evidence` (`issuer_id`,`kind`,`reference_hash`)';$parts[]='KEY `credit_entry_parent` (`issuer_id`,`credit_id`)';$parts[]='FOREIGN KEY (`invoice_id`) REFERENCES `'.$p.'invoices` (`id`)';$parts[]='FOREIGN KEY (`parent_key`) REFERENCES `'.$p.'company_credit_entries` (`request_key`)';}
            IssuerStorage::query($db,'CREATE TABLE IF NOT EXISTS `'.$p.$table.'` ('.implode(',',$parts).') ENGINE=InnoDB');
        }self::assertValid($db,$p);
    }
    public static function assertValid($db,string $p):void
    {
        if(!preg_match('/^[A-Za-z0-9_]+$/D',$p))throw new InvalidArgumentException('Invalid table prefix');$conn=$db->pdo??$db->conn_id??null;if($conn instanceof PDO&&$conn->getAttribute(PDO::ATTR_DRIVER_NAME)==='sqlite')return;
        foreach(self::DEFINITIONS+['creditnotes'=>['issuer_id'=>'int unsigned NULL']] as $suffix=>$fields){$table=$p.$suffix;$columns=[];
            foreach(IssuerStorage::query($db,'SELECT COLUMN_NAME,COLUMN_TYPE,IS_NULLABLE,COLUMN_DEFAULT,COLLATION_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?',[$table])->result_array() as $row)$columns[$row['COLUMN_NAME']]=$row;
            foreach($fields as $name=>$expected){$nullable=str_ends_with($expected,' NULL');$type=str_replace(' NULL','',$expected);$row=$columns[$name]??[];
                if(preg_replace('/^int\([0-9]+\)/','int',strtolower($row['COLUMN_TYPE']??''))!==$type||($row['IS_NULLABLE']??null)!==($nullable?'YES':'NO')||!IssuerStorage::sqlNullDefault($row)||(in_array($name,['request_key','input_hash','reference_hash','parent_key'],true)&&($row['COLLATION_NAME']??null)!=='ascii_bin'))throw new RuntimeException('issuer_credit_schema_invalid');
            }
            $engine=IssuerStorage::query($db,'SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?',[$table])->row_array();if(strtoupper($engine['ENGINE']??'')!=='INNODB')throw new RuntimeException('issuer_credit_schema_invalid');
            if($suffix==='creditnotes')continue;
            $indexes=[];foreach(IssuerStorage::query($db,'SELECT INDEX_NAME,COLUMN_NAME,NON_UNIQUE,SUB_PART FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? ORDER BY INDEX_NAME,SEQ_IN_INDEX',[$table])->result_array() as $row){if($row['SUB_PART']!==null)throw new RuntimeException('issuer_credit_schema_invalid');$indexes[$row['INDEX_NAME']]['columns'][]=$row['COLUMN_NAME'];$indexes[$row['INDEX_NAME']]['unique']=(int)$row['NON_UNIQUE']===0;}
            $required=$suffix==='company_credit_documents'?['PRIMARY'=>[['credit_id'],true],'credit_request'=>[['request_key'],true],'credit_number'=>[['issuer_id','number'],true]]:['PRIMARY'=>[['request_key'],true],'credit_evidence'=>[['issuer_id','kind','reference_hash'],true],'credit_entry_parent'=>[['issuer_id','credit_id'],false]];
            foreach($required as $name=>[$names,$unique])if(($indexes[$name]['columns']??[])!==$names||($indexes[$name]['unique']??null)!==$unique)throw new RuntimeException('issuer_credit_schema_invalid');
            $fks=['issuer_id'=>['issuers_registry','id'],'credit_id'=>['creditnotes','id']];if($suffix==='company_credit_entries')$fks+=['invoice_id'=>['invoices','id'],'parent_key'=>['company_credit_entries','request_key']];
            foreach($fks as $name=>[$target,$column]){$fk=IssuerStorage::query($db,'SELECT REFERENCED_TABLE_NAME,REFERENCED_COLUMN_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=? AND REFERENCED_TABLE_SCHEMA=DATABASE()',[$table,$name])->result_array();if(count($fk)!==1||$fk[0]['REFERENCED_TABLE_NAME']!==$p.$target||$fk[0]['REFERENCED_COLUMN_NAME']!==$column)throw new RuntimeException('issuer_credit_schema_invalid');}
        }
    }
}
