<?php

declare(strict_types=1);
require_once __DIR__.'/issuers/IssuerStorage.php';require_once __DIR__.'/issuers/IssuerTransaction.php';
/** Own ticket notice opt-in and durable one-way deduplicated intents. No invoice source or global SMS hook. */
final class CompanyTicketConnectSchema
{
    public static function definitions(string $p):array
    {
        if(!preg_match('/^[A-Za-z0-9_]+$/D',$p))throw new InvalidArgumentException('Invalid prefix');
        $h=static fn($n,$null=false)=>'`'.$n.'` varchar(64) CHARACTER SET ascii COLLATE ascii_bin'.($null?' NULL':' NOT NULL');
        $text=static fn($n)=>'`'.$n.'` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL';
        return[
            'company_ticket_connect_policies'=>[
                '`issuer_id` int unsigned NOT NULL PRIMARY KEY','`revision` int unsigned NOT NULL','`enabled` tinyint NOT NULL','`configured_by` int NOT NULL',
                $h('account_hash'),'`portal_base` varchar(1000) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL','`first_ticket_id` int unsigned NOT NULL',
                '`scan_count` bigint unsigned NOT NULL','`scan_ticket_id` int unsigned NOT NULL','`scan_contact_id` int NOT NULL',$h('policy_hash'),'`audit_id` bigint unsigned NOT NULL','`configured_at` datetime NOT NULL',
                'FOREIGN KEY (`issuer_id`) REFERENCES `'.$p.'issuers_registry` (`id`)','FOREIGN KEY (`configured_by`) REFERENCES `'.$p.'staff` (`staffid`)',
                'FOREIGN KEY (`audit_id`) REFERENCES `'.$p.'issuers_audit` (`id`)',
            ],
            'company_ticket_connect_messages'=>[
                $h('message_key').' PRIMARY KEY','`issuer_id` int unsigned NOT NULL','`ticket_id` int unsigned NOT NULL','`client_id` int NOT NULL','`contact_id` int NOT NULL','`actor_id` int NOT NULL',
                $h('account_hash'),$text('source_json'),$h('source_hash'),$text('payload_cipher'),$h('payload_hash'),
                '`status` varchar(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL',$h('claim_key'),'`remote_id` varchar(128) CHARACTER SET ascii COLLATE ascii_bin NULL',$h('response_hash',true),
                '`audit_id` bigint unsigned NOT NULL','`created_at` datetime NOT NULL','`updated_at` datetime NOT NULL',
                'UNIQUE KEY `ticket_connect_once` (`issuer_id`,`ticket_id`,`contact_id`)','UNIQUE KEY `ticket_connect_remote` (`issuer_id`,`account_hash`,`remote_id`)',
                'KEY `ticket_connect_pending` (`status`,`created_at`,`message_key`)',
                'FOREIGN KEY (`issuer_id`) REFERENCES `'.$p.'issuers_registry` (`id`)','FOREIGN KEY (`issuer_id`) REFERENCES `'.$p.'company_ticket_connect_policies` (`issuer_id`)',
                'FOREIGN KEY (`issuer_id`) REFERENCES `'.$p.'company_connect_api_accounts` (`issuer_id`)','FOREIGN KEY (`actor_id`) REFERENCES `'.$p.'staff` (`staffid`)',
                'FOREIGN KEY (`issuer_id`,`ticket_id`) REFERENCES `'.$p.'company_tickets` (`issuer_id`,`id`)',
                'FOREIGN KEY (`client_id`,`contact_id`) REFERENCES `'.$p.'contacts` (`userid`,`id`)','FOREIGN KEY (`audit_id`) REFERENCES `'.$p.'issuers_audit` (`id`)',
            ],
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
                if($actual!==$type||($r['IS_NULLABLE']??null)!==$nullable||!IssuerStorage::sqlNullDefault($r)||str_contains($tail,'AUTO_INCREMENT')!==str_contains(strtolower($r['EXTRA']??''),'auto_increment'))throw new RuntimeException('issuer_ticket_connect_schema_invalid');
                if(preg_match('/COLLATE ([a-z0-9_]+)/',$tail,$c)&&($r['COLLATION_NAME']??null)!==$c[1])throw new RuntimeException('issuer_ticket_connect_schema_invalid');
            }
            if(array_diff(array_keys($columns),$expected))throw new RuntimeException('issuer_ticket_connect_schema_invalid');
            $engine=IssuerStorage::query($db,'SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?',[$name])->row_array();if(strtoupper($engine['ENGINE']??'')!=='INNODB')throw new RuntimeException('issuer_ticket_connect_schema_invalid');
            $indexes=[];foreach(IssuerStorage::query($db,'SELECT INDEX_NAME,COLUMN_NAME,NON_UNIQUE,SUB_PART FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? ORDER BY INDEX_NAME,SEQ_IN_INDEX',[$name])->result_array() as $r){if($r['SUB_PART']!==null)throw new RuntimeException('issuer_ticket_connect_schema_invalid');$indexes[$r['INDEX_NAME']]['columns'][]=$r['COLUMN_NAME'];$indexes[$r['INDEX_NAME']]['non_unique']=(int)$r['NON_UNIQUE'];}
            foreach($parts as $definition){
                if(preg_match('/^(UNIQUE )?KEY `([^`]+)` \(([^)]+)\)$/D',$definition,$m)){$key=$m[2];$cols=explode(',',str_replace('`','',$m[3]));$unique=$m[1]!==''?0:1;}
                elseif(preg_match('/^PRIMARY KEY \(([^)]+)\)$/D',$definition,$m)){$key='PRIMARY';$cols=explode(',',str_replace('`','',$m[1]));$unique=0;}
                elseif(preg_match('/^`([^`]+)` .*PRIMARY KEY/',$definition,$m)){$key='PRIMARY';$cols=[$m[1]];$unique=0;}else continue;
                if(($indexes[$key]['columns']??[])!==$cols||($indexes[$key]['non_unique']??null)!==$unique)throw new RuntimeException('issuer_ticket_connect_schema_invalid');
            }
            $foreign=[];foreach(IssuerStorage::query($db,'SELECT k.CONSTRAINT_NAME,k.COLUMN_NAME,k.REFERENCED_TABLE_NAME,k.REFERENCED_COLUMN_NAME,r.DELETE_RULE,r.UPDATE_RULE FROM information_schema.KEY_COLUMN_USAGE k JOIN information_schema.REFERENTIAL_CONSTRAINTS r ON r.CONSTRAINT_SCHEMA=k.CONSTRAINT_SCHEMA AND r.TABLE_NAME=k.TABLE_NAME AND r.CONSTRAINT_NAME=k.CONSTRAINT_NAME WHERE k.TABLE_SCHEMA=DATABASE() AND k.TABLE_NAME=? AND k.REFERENCED_TABLE_SCHEMA=DATABASE() ORDER BY k.CONSTRAINT_NAME,k.ORDINAL_POSITION',[$name])->result_array() as $r){if(!in_array($r['DELETE_RULE'],['RESTRICT','NO ACTION'],true)||!in_array($r['UPDATE_RULE'],['RESTRICT','NO ACTION'],true))throw new RuntimeException('issuer_ticket_connect_schema_invalid');$foreign[$r['CONSTRAINT_NAME']][]=$r['COLUMN_NAME'].':'.$r['REFERENCED_TABLE_NAME'].':'.$r['REFERENCED_COLUMN_NAME'];}
            foreach($parts as $definition)if(preg_match('/^FOREIGN KEY \(([^)]+)\) REFERENCES `([^`]+)` \(([^)]+)\)$/D',$definition,$m)){$cols=explode(',',str_replace('`','',$m[1]));$refs=explode(',',str_replace('`','',$m[3]));$want=[];foreach($cols as $i=>$col)$want[]=$col.':'.$m[2].':'.$refs[$i];if(!in_array($want,array_values($foreign),true))throw new RuntimeException('issuer_ticket_connect_schema_invalid');}
        }
    }
}
