<?php
declare(strict_types=1);
require_once __DIR__.'/issuers/IssuerStorage.php';
/** Explicit immutable merge lineage; original records and attachment bytes are retained. */
final class NativeCompanyTicketMergeSchema
{
    public const LINKS='company_ticket_merges';
    public const REPLIES='company_ticket_merge_replies';
    public const FILES='company_ticket_merge_files';
    public static function definitions(string$p):array{
        if(!preg_match('/^[A-Za-z0-9_]+$/D',$p))throw new InvalidArgumentException('Invalid prefix');
        $v=static fn($n,$len)=>'`'.$n.'` varchar('.$len.') CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL';
        return[self::LINKS=>[
            '`child_id` int unsigned NOT NULL PRIMARY KEY','`issuer_id` int unsigned NOT NULL','`primary_id` int unsigned NOT NULL','`actor_id` int NOT NULL',$v('source_hash',64),$v('request_key',64),'`manifest_json` text CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL','`created_at` datetime NOT NULL',
            'KEY `merge_child` (`issuer_id`,`child_id`)','KEY `merge_primary` (`issuer_id`,`primary_id`)','KEY `merge_actor` (`actor_id`)',
            'FOREIGN KEY (`issuer_id`,`child_id`) REFERENCES `'.$p.'company_tickets` (`issuer_id`,`id`)','FOREIGN KEY (`issuer_id`,`primary_id`) REFERENCES `'.$p.'company_tickets` (`issuer_id`,`id`)','FOREIGN KEY (`actor_id`) REFERENCES `'.$p.'staff` (`staffid`)',
        ],self::REPLIES=>[
            '`reply_id` int unsigned NOT NULL PRIMARY KEY','`issuer_id` int unsigned NOT NULL','`primary_id` int unsigned NOT NULL','`child_id` int unsigned NOT NULL',$v('source_kind',16),'`source_id` int unsigned NOT NULL',
            '`author_staff_id` int NULL','`author_contact_id` int NULL','`client_id` int NULL',$v('author_name',191),$v('body_format',16),$v('content_hash',64),$v('source_revision',64),'`source_date` datetime NOT NULL',
            'KEY `merge_reply_primary` (`issuer_id`,`primary_id`)','KEY `merge_reply_child` (`child_id`)','KEY `merge_reply_staff` (`author_staff_id`)','KEY `merge_reply_contact` (`author_contact_id`)','KEY `merge_reply_client` (`client_id`)',
            'UNIQUE KEY `merge_reply_source` (`primary_id`,`child_id`,`source_kind`,`source_id`)',
            'FOREIGN KEY (`reply_id`) REFERENCES `'.$p.'company_ticket_replies` (`id`)','FOREIGN KEY (`issuer_id`,`primary_id`) REFERENCES `'.$p.'company_tickets` (`issuer_id`,`id`)','FOREIGN KEY (`child_id`) REFERENCES `'.$p.self::LINKS.'` (`child_id`)',
            'FOREIGN KEY (`author_staff_id`) REFERENCES `'.$p.'staff` (`staffid`)','FOREIGN KEY (`author_contact_id`) REFERENCES `'.$p.'contacts` (`id`)','FOREIGN KEY (`client_id`) REFERENCES `'.$p.'clients` (`userid`)',
        ],self::FILES=>[
            '`file_key` varchar(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL PRIMARY KEY','`issuer_id` int unsigned NOT NULL','`primary_id` int unsigned NOT NULL','`reply_id` int unsigned NOT NULL','`child_id` int unsigned NOT NULL','`source_file_key` varchar(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL',$v('content_hash',64),
            'KEY `merge_file_primary` (`issuer_id`,`primary_id`)','KEY `merge_file_reply` (`reply_id`)','KEY `merge_file_child` (`child_id`)','KEY `merge_file_source` (`source_file_key`)',
            'UNIQUE KEY `merge_file_once` (`primary_id`,`child_id`,`source_file_key`)',
            'FOREIGN KEY (`file_key`) REFERENCES `'.$p.'company_ticket_files` (`file_key`)','FOREIGN KEY (`issuer_id`,`primary_id`) REFERENCES `'.$p.'company_tickets` (`issuer_id`,`id`)','FOREIGN KEY (`reply_id`) REFERENCES `'.$p.self::REPLIES.'` (`reply_id`)',
            'FOREIGN KEY (`child_id`) REFERENCES `'.$p.self::LINKS.'` (`child_id`)','FOREIGN KEY (`source_file_key`) REFERENCES `'.$p.'company_ticket_files` (`file_key`)',
        ]];
    }
    public static function active($db,string$p):bool{require_once __DIR__.'/NativeCompanyTicketVisibility.php';if(!NativeCompanyTicketVisibility::enabled($db,$p))return false;self::assertValid($db,$p);return true;}
    public static function tables($db,string$p):array{return self::active($db,$p)?array_keys(self::definitions($p)):[];}
    public static function ensure($db,string $p):void
    {
        // Validate existing tables BEFORE creating any missing table; never repair suspicious state.
        $missing=[];
        foreach(self::definitions($p) as $table=>$parts) {
            $found=IssuerStorage::query($db,'SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?',[$p.$table])->result_array();
            if($found)self::assertTable($db,$p,$table,$parts); else $missing[$table]=$parts;
        }
        foreach($missing as $table=>$parts){$ddl=self::boundedForeignKeyNames($table,$parts);IssuerStorage::query($db,'CREATE TABLE IF NOT EXISTS `'.$p.$table.'` ('.implode(',',$ddl).') ENGINE=InnoDB');}
        self::assertValid($db,$p);
    }
    /** Use bounded, deterministic symbols because installer prefixes count toward MySQL's 64-byte limit. */
    private static function boundedForeignKeyNames(string $table,array $parts):array
    {
        $symbols=[
            self::LINKS=>['nctm_l_child','nctm_l_primary','nctm_l_actor'],
            self::REPLIES=>['nctm_r_reply','nctm_r_primary','nctm_r_child','nctm_r_staff','nctm_r_contact','nctm_r_client'],
            self::FILES=>['nctm_f_file','nctm_f_primary','nctm_f_reply','nctm_f_child','nctm_f_source'],
        ][$table]??throw new LogicException('native_ticket_merge_schema_invalid');
        $index=0;
        foreach($parts as$key=>$definition){
            if(!str_starts_with($definition,'FOREIGN KEY '))continue;
            if(!isset($symbols[$index]))throw new LogicException('native_ticket_merge_schema_invalid');
            $parts[$key]='CONSTRAINT `'.$symbols[$index++].'` '.$definition;
        }
        if($index!==count($symbols))throw new LogicException('native_ticket_merge_schema_invalid');
        return$parts;
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
                if($actual!==$type||($r['IS_NULLABLE']??null)!==$nullable||!IssuerStorage::sqlNullDefault($r)||strtolower($r['EXTRA']??'')!==(str_contains($tail,'AUTO_INCREMENT')?'auto_increment':''))throw new RuntimeException('issuer_native_ticket_merge_schema_invalid');
                if(preg_match('/COLLATE ([a-z0-9_]+)/',$tail,$c)&&($r['COLLATION_NAME']??null)!==$c[1])throw new RuntimeException('issuer_native_ticket_merge_schema_invalid');
            }
            if(array_diff(array_keys($columns),$expected))throw new RuntimeException('issuer_native_ticket_merge_schema_invalid');
            $engine=IssuerStorage::query($db,'SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?',[$name])->row_array();if(strtoupper($engine['ENGINE']??'')!=='INNODB')throw new RuntimeException('issuer_native_ticket_merge_schema_invalid');
            $indexes=[];foreach(IssuerStorage::query($db,'SELECT INDEX_NAME,COLUMN_NAME,NON_UNIQUE,SUB_PART FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? ORDER BY INDEX_NAME,SEQ_IN_INDEX',[$name])->result_array() as $r){if($r['SUB_PART']!==null)throw new RuntimeException('issuer_native_ticket_merge_schema_invalid');if(isset($indexes[$r['INDEX_NAME']]['non_unique'])&&$indexes[$r['INDEX_NAME']]['non_unique']!==(int)$r['NON_UNIQUE'])throw new RuntimeException('issuer_native_ticket_merge_schema_invalid');$indexes[$r['INDEX_NAME']]['columns'][]=$r['COLUMN_NAME'];$indexes[$r['INDEX_NAME']]['non_unique']=(int)$r['NON_UNIQUE'];}
            $expectedIndexes=[];
            foreach($parts as $definition){
                if(preg_match('/^(UNIQUE )?KEY `([^`]+)` \(([^)]+)\)$/D',$definition,$m)){$key=$m[2];$cols=explode(',',str_replace('`','',$m[3]));$unique=$m[1]!==''?0:1;}
                elseif(preg_match('/^`([^`]+)` .*PRIMARY KEY/',$definition,$m)){$key='PRIMARY';$cols=[$m[1]];$unique=0;}else continue;
                $expectedIndexes[]=$key;
                if(($indexes[$key]['columns']??[])!==$cols||($indexes[$key]['non_unique']??null)!==$unique)throw new RuntimeException('issuer_native_ticket_merge_schema_invalid');
            }
            if(array_diff(array_keys($indexes),$expectedIndexes))throw new RuntimeException('issuer_native_ticket_merge_schema_invalid');
            $foreign=[];foreach(IssuerStorage::query($db,'SELECT CONSTRAINT_NAME,COLUMN_NAME,REFERENCED_TABLE_NAME,REFERENCED_COLUMN_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND REFERENCED_TABLE_SCHEMA=DATABASE() ORDER BY CONSTRAINT_NAME,ORDINAL_POSITION',[$name])->result_array() as $r)$foreign[$r['CONSTRAINT_NAME']][]=$r['COLUMN_NAME'].':'.$r['REFERENCED_TABLE_NAME'].':'.$r['REFERENCED_COLUMN_NAME'];
            $expectedForeign=0;
            foreach($parts as $definition)if(preg_match('/^FOREIGN KEY \(([^)]+)\) REFERENCES `([^`]+)` \(([^)]+)\)$/D',$definition,$m)){$expectedForeign++;$cols=explode(',',str_replace('`','',$m[1]));$refs=explode(',',str_replace('`','',$m[3]));$want=[];foreach($cols as $i=>$col)$want[]=$col.':'.$m[2].':'.$refs[$i];if(!in_array($want,array_values($foreign),true))throw new RuntimeException('issuer_native_ticket_merge_schema_invalid');}
        if(count($foreign)!==$expectedForeign)throw new RuntimeException('issuer_native_ticket_merge_schema_invalid');
        $rules=IssuerStorage::query($db,'SELECT UPDATE_RULE,DELETE_RULE FROM information_schema.REFERENTIAL_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME=?',[$name])->result_array();
        if(count($rules)!==$expectedForeign)throw new RuntimeException('issuer_native_ticket_merge_schema_invalid');
        foreach($rules as $rule)foreach(['UPDATE_RULE','DELETE_RULE'] as $key)if(!in_array(strtoupper($rule[$key]??''),['RESTRICT','NO ACTION'],true))throw new RuntimeException('issuer_native_ticket_merge_schema_invalid');
    }
}
