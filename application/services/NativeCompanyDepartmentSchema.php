<?php

declare(strict_types=1);
require_once __DIR__.'/issuers/IssuerStorage.php';
/** Core-owned knowledge-base storage. Historical articles and groups are never adopted. */
final class NativeCompanyDepartmentSchema
{
    public const DEPARTMENTS='company_departments';
    public const MEMBERS='company_department_members';
    public const TICKETS='company_ticket_native_metadata';
    public const LEGACY_TICKETS='company_legacy_ticket_metadata';
    public static function definitions(string $p):array
    {
        if(!preg_match('/^[A-Za-z0-9_]+$/D',$p))throw new InvalidArgumentException('Invalid prefix');
        $v=static fn($n,$size)=>'`'.$n.'` varchar('.$size.') CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL';
        $definitions = [
            self::DEPARTMENTS=>[
                '`id` int unsigned NOT NULL AUTO_INCREMENT PRIMARY KEY','`issuer_id` int unsigned NOT NULL',$v('name',100),$v('imap_username',191),$v('email',100),$v('host',150),$v('encryption',3),$v('folder',191),
                '`password_cipher` text CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL','`calendar_id` text CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL',
                '`email_from_header` int NOT NULL','`delete_after_import` int NOT NULL','`hidefromclient` int NOT NULL','`revision` int unsigned NOT NULL','`actor_id` int NOT NULL','`created_at` datetime NOT NULL','`updated_at` datetime NOT NULL',
                'UNIQUE KEY `department_name` (`issuer_id`,`name`)','KEY `actor` (`actor_id`)',
                'FOREIGN KEY (`issuer_id`) REFERENCES `'.$p.'issuers_registry` (`id`)','FOREIGN KEY (`actor_id`) REFERENCES `'.$p.'staff` (`staffid`)',
            ],
            self::MEMBERS=>[
                '`id` int unsigned NOT NULL AUTO_INCREMENT PRIMARY KEY','`issuer_id` int unsigned NOT NULL',$v('department_source_kind',40),'`department_source_id` int unsigned NOT NULL','`staff_id` int NOT NULL','`revision` int unsigned NOT NULL','`actor_id` int NOT NULL','`created_at` datetime NOT NULL','`updated_at` datetime NOT NULL',
                'UNIQUE KEY `department_staff` (`issuer_id`,`department_source_kind`,`department_source_id`,`staff_id`)','KEY `staff` (`staff_id`)','KEY `actor` (`actor_id`)',
                'FOREIGN KEY (`issuer_id`) REFERENCES `'.$p.'issuers_registry` (`id`)','FOREIGN KEY (`staff_id`) REFERENCES `'.$p.'staff` (`staffid`)','FOREIGN KEY (`actor_id`) REFERENCES `'.$p.'staff` (`staffid`)',
            ],
        ];
        foreach([self::TICKETS=>true,self::LEGACY_TICKETS=>false]as$table=>$owned){
            $definitions[$table]=[
                '`id` int unsigned NOT NULL AUTO_INCREMENT PRIMARY KEY','`issuer_id` int unsigned NOT NULL','`ticket_id` int'.($owned?' unsigned':'').' NOT NULL',$v('department_source_kind',40),'`department_source_id` int unsigned NOT NULL','`revision` int unsigned NOT NULL','`actor_id` int NOT NULL','`created_at` datetime NOT NULL','`updated_at` datetime NOT NULL',
                'UNIQUE KEY `ticket_owner` (`issuer_id`,`ticket_id`)','KEY `ticket` (`ticket_id`)','KEY `department` (`issuer_id`,`department_source_kind`,`department_source_id`)','KEY `actor` (`actor_id`)',
                'FOREIGN KEY (`issuer_id`) REFERENCES `'.$p.'issuers_registry` (`id`)','FOREIGN KEY (`actor_id`) REFERENCES `'.$p.'staff` (`staffid`)',
                $owned?'FOREIGN KEY (`issuer_id`,`ticket_id`) REFERENCES `'.$p.'company_tickets` (`issuer_id`,`id`)':'FOREIGN KEY (`ticket_id`) REFERENCES `'.$p.'tickets` (`ticketid`)',
            ];
        }
        // Same ticket source, with native sender/context fields; a NULL customer is not public access.
        array_splice($definitions[self::TICKETS],9,0,[
            '`contact_id` int NULL','`project_source_kind` varchar(40) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL','`project_source_id` int unsigned NULL',
            $v('sender_name',191),$v('sender_email',191),$v('sender_origin',24),'`cc_json` text CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL',
            '`opened_by` int NOT NULL','`admin_read` int NOT NULL','`last_reply_at` datetime NULL','`staff_replying` int NULL','`staff_replying_at` datetime NULL',
            'KEY `contact` (`contact_id`)','KEY `opened_by` (`opened_by`)','KEY `staff_replying` (`staff_replying`)',
            'FOREIGN KEY (`contact_id`) REFERENCES `'.$p.'contacts` (`id`)','FOREIGN KEY (`opened_by`) REFERENCES `'.$p.'staff` (`staffid`)','FOREIGN KEY (`staff_replying`) REFERENCES `'.$p.'staff` (`staffid`)',
        ]);
        return $definitions;
    }
    public static function ensure($db,string $p):void
    {
        // Validate existing tables BEFORE creating any missing table; never repair suspicious state.
        $missing=[];
        foreach(self::definitions($p) as $table=>$parts) {
            $found=IssuerStorage::query($db,'SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?',[$p.$table])->result_array();
            if($found)self::assertTable($db,$p,$table,$parts); else $missing[$table]=$parts;
        }
        foreach($missing as $table=>$parts)IssuerStorage::query($db,'CREATE TABLE IF NOT EXISTS `'.$p.$table.'` ('.implode(',',$parts).') ENGINE=InnoDB');
        self::assertValid($db,$p);
    }
    public static function assertValid($db,string $p):void
    {
        foreach(self::definitions($p) as $table=>$parts)self::assertTable($db,$p,$table,$parts);
    }
    private static function assertTable($db,string $p,string $table,array $parts):void
    {
            $name=$p.$table;$columns=[];
            foreach(IssuerStorage::query($db,'SELECT COLUMN_NAME,COLUMN_TYPE,IS_NULLABLE,COLUMN_DEFAULT,COLLATION_NAME,EXTRA FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?',[$name])->result_array() as $r)$columns[$r['COLUMN_NAME']]=$r;
            $expected=[];
            foreach($parts as $definition){
                if(!preg_match('/^`([^`]+)` (varchar\([0-9]+\)|bigint unsigned|int unsigned|int|text|datetime|date)(.*)$/D',$definition,$m))continue;
                [$all,$column,$type,$tail]=$m;$expected[]=$column;$r=$columns[$column]??[];
                $actual=preg_replace('/^(bigint|int)\([0-9]+\)/','$1',strtolower($r['COLUMN_TYPE']??''));$nullable=str_contains($tail,'NOT NULL')?'NO':'YES';
                if($actual!==$type||($r['IS_NULLABLE']??null)!==$nullable||!IssuerStorage::sqlNullDefault($r)||strtolower($r['EXTRA']??'')!==(str_contains($tail,'AUTO_INCREMENT')?'auto_increment':''))throw new RuntimeException('issuer_native_department_schema_invalid');
                if(preg_match('/COLLATE ([a-z0-9_]+)/',$tail,$c)&&($r['COLLATION_NAME']??null)!==$c[1])throw new RuntimeException('issuer_native_department_schema_invalid');
            }
            if(array_diff(array_keys($columns),$expected))throw new RuntimeException('issuer_native_department_schema_invalid');
            $engine=IssuerStorage::query($db,'SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?',[$name])->row_array();if(strtoupper($engine['ENGINE']??'')!=='INNODB')throw new RuntimeException('issuer_native_department_schema_invalid');
            $indexes=[];foreach(IssuerStorage::query($db,'SELECT INDEX_NAME,COLUMN_NAME,NON_UNIQUE,SUB_PART FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? ORDER BY INDEX_NAME,SEQ_IN_INDEX',[$name])->result_array() as $r){if($r['SUB_PART']!==null)throw new RuntimeException('issuer_native_department_schema_invalid');if(isset($indexes[$r['INDEX_NAME']]['non_unique'])&&$indexes[$r['INDEX_NAME']]['non_unique']!==(int)$r['NON_UNIQUE'])throw new RuntimeException('issuer_native_department_schema_invalid');$indexes[$r['INDEX_NAME']]['columns'][]=$r['COLUMN_NAME'];$indexes[$r['INDEX_NAME']]['non_unique']=(int)$r['NON_UNIQUE'];}
            $expectedIndexes=[];
            foreach($parts as $definition){
                if(preg_match('/^(UNIQUE )?KEY `([^`]+)` \(([^)]+)\)$/D',$definition,$m)){$key=$m[2];$cols=explode(',',str_replace('`','',$m[3]));$unique=$m[1]!==''?0:1;}
                elseif(preg_match('/^`([^`]+)` .*PRIMARY KEY/',$definition,$m)){$key='PRIMARY';$cols=[$m[1]];$unique=0;}else continue;
                $expectedIndexes[]=$key;
                if(($indexes[$key]['columns']??[])!==$cols||($indexes[$key]['non_unique']??null)!==$unique)throw new RuntimeException('issuer_native_department_schema_invalid');
            }
            if(array_diff(array_keys($indexes),$expectedIndexes))throw new RuntimeException('issuer_native_department_schema_invalid');
            $foreign=[];foreach(IssuerStorage::query($db,'SELECT CONSTRAINT_NAME,COLUMN_NAME,REFERENCED_TABLE_NAME,REFERENCED_COLUMN_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND REFERENCED_TABLE_SCHEMA=DATABASE() ORDER BY CONSTRAINT_NAME,ORDINAL_POSITION',[$name])->result_array() as $r)$foreign[$r['CONSTRAINT_NAME']][]=$r['COLUMN_NAME'].':'.$r['REFERENCED_TABLE_NAME'].':'.$r['REFERENCED_COLUMN_NAME'];
            $expectedForeign=0;
            foreach($parts as $definition)if(preg_match('/^FOREIGN KEY \(([^)]+)\) REFERENCES `([^`]+)` \(([^)]+)\)$/D',$definition,$m)){$expectedForeign++;$cols=explode(',',str_replace('`','',$m[1]));$refs=explode(',',str_replace('`','',$m[3]));$want=[];foreach($cols as $i=>$col)$want[]=$col.':'.$m[2].':'.$refs[$i];if(!in_array($want,array_values($foreign),true))throw new RuntimeException('issuer_native_department_schema_invalid');}
        if(count($foreign)!==$expectedForeign)throw new RuntimeException('issuer_native_department_schema_invalid');
        $rules=IssuerStorage::query($db,'SELECT UPDATE_RULE,DELETE_RULE FROM information_schema.REFERENTIAL_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME=?',[$name])->result_array();
        if(count($rules)!==$expectedForeign)throw new RuntimeException('issuer_native_department_schema_invalid');
        foreach($rules as $rule)foreach(['UPDATE_RULE','DELETE_RULE'] as $key)if(!in_array(strtoupper($rule[$key]??''),['RESTRICT','NO ACTION'],true))throw new RuntimeException('issuer_native_department_schema_invalid');
    }
}
