<?php

declare(strict_types=1);
require_once __DIR__.'/issuers/IssuerStorage.php';require_once __DIR__.'/issuers/IssuerTransaction.php';
/** Private editable documents and immutable request/audit provenance; no invoice or number exists before issue. */
final class CompanyInvoiceDraftsSchema
{
    public static function definitions(string $p):array
    {
        if(!preg_match('/^[A-Za-z0-9_]+$/D',$p))throw new InvalidArgumentException('Invalid prefix');
        $hash=static fn($name,$nullable=false)=>'`'.$name.'` varchar(64) CHARACTER SET ascii COLLATE ascii_bin'.($nullable?' NULL':' NOT NULL');
        $json=static fn($name,$nullable=false)=>'`'.$name.'` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin'.($nullable?' NULL':' NOT NULL');
        return [
            'company_invoice_drafts'=>[
                '`id` int NOT NULL AUTO_INCREMENT PRIMARY KEY','`issuer_id` int unsigned NOT NULL','`created_by` int NOT NULL',$hash('origin_key'),$hash('creation_hash'),$hash('last_event_key'),
                '`state` varchar(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL','`revision` int NOT NULL','`client_id` int NOT NULL','`currency_id` int NOT NULL',
                $json('payload_json'),$hash('payload_hash'),$json('context_json'),$hash('context_hash'),$json('source_json',true),$hash('source_hash',true),
                '`invoice_id` int NULL','`issue_actor` int NULL','`issue_revision` int NULL',$hash('issue_key',true),$json('issue_json',true),$hash('issue_hash',true),
                '`created_at` datetime NOT NULL','`updated_at` datetime NOT NULL',
                'UNIQUE KEY `draft_origin` (`origin_key`)','UNIQUE KEY `draft_invoice` (`invoice_id`)','KEY `draft_owner` (`issuer_id`,`id`)',
                'FOREIGN KEY (`issuer_id`) REFERENCES `'.$p.'issuers_registry` (`id`)','FOREIGN KEY (`created_by`) REFERENCES `'.$p.'staff` (`staffid`)',
                'FOREIGN KEY (`client_id`) REFERENCES `'.$p.'clients` (`userid`)','FOREIGN KEY (`currency_id`) REFERENCES `'.$p.'currencies` (`id`)',
                'FOREIGN KEY (`invoice_id`) REFERENCES `'.$p.'invoices` (`id`)'
            ],
            'company_invoice_draft_events'=>[
                $hash('request_key').' PRIMARY KEY','`issuer_id` int unsigned NOT NULL','`actor_id` int NOT NULL','`draft_id` int NOT NULL',
                '`operation` varchar(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL',$hash('input_hash'),$json('data_json'),$hash('data_hash'),'`audit_id` bigint unsigned NOT NULL','`created_at` datetime NOT NULL',
                'KEY `draft_events_owner` (`issuer_id`,`draft_id`)','FOREIGN KEY (`issuer_id`) REFERENCES `'.$p.'issuers_registry` (`id`)',
                'FOREIGN KEY (`actor_id`) REFERENCES `'.$p.'staff` (`staffid`)','FOREIGN KEY (`draft_id`) REFERENCES `'.$p.'company_invoice_drafts` (`id`)',
                'FOREIGN KEY (`audit_id`) REFERENCES `'.$p.'issuers_audit` (`id`)'
            ]
        ];
    }
    public static function ensure($db,string $p):void
    {
        $definitions=self::definitions($p);IssuerTransaction::assertIndependent($db);foreach($definitions as $table=>$parts)IssuerStorage::query($db,'CREATE TABLE IF NOT EXISTS `'.$p.$table.'` ('.implode(',',$parts).') ENGINE=InnoDB');
        if(property_exists($db,'data_cache'))unset($db->data_cache['table_names']);
        self::assertValid($db,$p);
    }
    public static function assertValid($db,string $p):void
    {
        self::definitions($p);$conn=$db->pdo??$db->conn_id??null;if($conn instanceof PDO&&$conn->getAttribute(PDO::ATTR_DRIVER_NAME)==='sqlite')return;
        foreach(self::definitions($p) as $table=>$parts){
            $name=$p.$table;$columns=[];
            foreach(IssuerStorage::query($db,'SELECT COLUMN_NAME,COLUMN_TYPE,IS_NULLABLE,COLUMN_DEFAULT,COLLATION_NAME,EXTRA FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?',[$name])->result_array() as $r)$columns[$r['COLUMN_NAME']]=$r;
            $expected=[];
            foreach($parts as $definition){
                if(!preg_match('/^`([^`]+)` (varchar\([0-9]+\)|bigint unsigned|int unsigned|int|tinyint|mediumblob|mediumtext|text|datetime|date)(.*)$/D',$definition,$m))continue;
                [$all,$column,$type,$tail]=$m;$expected[]=$column;$r=$columns[$column]??[];
                $actual=preg_replace('/^(tinyint|int|bigint)\([0-9]+\)/','$1',strtolower($r['COLUMN_TYPE']??''));$nullable=str_contains($tail,'NOT NULL')?'NO':'YES';
                if($actual!==$type||($r['IS_NULLABLE']??null)!==$nullable||!IssuerStorage::sqlNullDefault($r)||str_contains($tail,'AUTO_INCREMENT')!==str_contains(strtolower($r['EXTRA']??''),'auto_increment'))throw new RuntimeException('issuer_invoice_drafts_schema_invalid');
                if(preg_match('/COLLATE ([a-z0-9_]+)/',$tail,$c)&&($r['COLLATION_NAME']??null)!==$c[1])throw new RuntimeException('issuer_invoice_drafts_schema_invalid');
            }
            if(array_diff(array_keys($columns),$expected))throw new RuntimeException('issuer_invoice_drafts_schema_invalid');
            $engine=IssuerStorage::query($db,'SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?',[$name])->row_array();if(strtoupper($engine['ENGINE']??'')!=='INNODB')throw new RuntimeException('issuer_invoice_drafts_schema_invalid');
            $indexes=[];foreach(IssuerStorage::query($db,'SELECT INDEX_NAME,COLUMN_NAME,NON_UNIQUE,SUB_PART FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? ORDER BY INDEX_NAME,SEQ_IN_INDEX',[$name])->result_array() as $r){if($r['SUB_PART']!==null)throw new RuntimeException('issuer_invoice_drafts_schema_invalid');$indexes[$r['INDEX_NAME']]['columns'][]=$r['COLUMN_NAME'];$indexes[$r['INDEX_NAME']]['non_unique']=(int)$r['NON_UNIQUE'];}
            foreach($parts as $definition){
                if(preg_match('/^(UNIQUE )?KEY `([^`]+)` \(([^)]+)\)$/D',$definition,$m)){$key=$m[2];$cols=explode(',',str_replace('`','',$m[3]));$unique=$m[1]!==''?0:1;}
                elseif(preg_match('/^PRIMARY KEY \(([^)]+)\)$/D',$definition,$m)){$key='PRIMARY';$cols=explode(',',str_replace('`','',$m[1]));$unique=0;}
                elseif(preg_match('/^`([^`]+)` .*PRIMARY KEY/',$definition,$m)){$key='PRIMARY';$cols=[$m[1]];$unique=0;}else continue;
                if(($indexes[$key]['columns']??[])!==$cols||($indexes[$key]['non_unique']??null)!==$unique)throw new RuntimeException('issuer_invoice_drafts_schema_invalid');
            }
            $foreign=[];foreach(IssuerStorage::query($db,'SELECT CONSTRAINT_NAME,COLUMN_NAME,REFERENCED_TABLE_NAME,REFERENCED_COLUMN_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND REFERENCED_TABLE_SCHEMA=DATABASE() ORDER BY CONSTRAINT_NAME,ORDINAL_POSITION',[$name])->result_array() as $r)$foreign[$r['CONSTRAINT_NAME']][]=$r['COLUMN_NAME'].':'.$r['REFERENCED_TABLE_NAME'].':'.$r['REFERENCED_COLUMN_NAME'];
            foreach($parts as $definition)if(preg_match('/^FOREIGN KEY \(([^)]+)\) REFERENCES `([^`]+)` \(([^)]+)\)$/D',$definition,$m)){$cols=explode(',',str_replace('`','',$m[1]));$refs=explode(',',str_replace('`','',$m[3]));$want=[];foreach($cols as $i=>$col)$want[]=$col.':'.$m[2].':'.$refs[$i];if(!in_array($want,array_values($foreign),true))throw new RuntimeException('issuer_invoice_drafts_schema_invalid');}
        }
    }
}
