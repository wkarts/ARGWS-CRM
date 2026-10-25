<?php
declare(strict_types=1);
require_once __DIR__.'/issuers/IssuerStorage.php';
/** Company-owned native spam rules; no adoption of global rules and no DDL on reads. */
final class NativeCompanySpamSchema
{
    public const TABLE='company_spam_filters';
    public const COMMANDS='company_spam_filter_commands';
    public static function definitions(string $p):array
    {
        if(!preg_match('/^[A-Za-z0-9_]+$/D',$p))throw new InvalidArgumentException('Invalid prefix');
        return[self::TABLE=>[
            '`id` int unsigned NOT NULL AUTO_INCREMENT PRIMARY KEY','`issuer_id` int unsigned NOT NULL',
            '`type` varchar(40) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL','`rel_type` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL','`value` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL',
            '`revision` int unsigned NOT NULL','`actor_id` int NOT NULL','`created_at` datetime NOT NULL','`updated_at` datetime NOT NULL',
            'KEY `spam_scope` (`issuer_id`,`rel_type`,`type`)','KEY `spam_actor` (`actor_id`)',
            'FOREIGN KEY (`issuer_id`) REFERENCES `'.$p.'issuers_registry` (`id`)','FOREIGN KEY (`actor_id`) REFERENCES `'.$p.'staff` (`staffid`)',
        ],self::COMMANDS=>[
            '`issuer_id` int unsigned NOT NULL','`request_key` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL',
            '`operation` varchar(16) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL','`input_hash` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL',
            '`result_id` int unsigned NOT NULL','`actor_id` int NOT NULL','`created_at` datetime NOT NULL',
            'PRIMARY KEY (`issuer_id`,`request_key`)','KEY `spam_command_actor` (`actor_id`)',
            'FOREIGN KEY (`issuer_id`) REFERENCES `'.$p.'issuers_registry` (`id`)','FOREIGN KEY (`actor_id`) REFERENCES `'.$p.'staff` (`staffid`)',
        ]];
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
                if($actual!==$type||($r['IS_NULLABLE']??null)!==$nullable||!IssuerStorage::sqlNullDefault($r)||strtolower($r['EXTRA']??'')!==(str_contains($tail,'AUTO_INCREMENT')?'auto_increment':''))throw new RuntimeException('issuer_native_spam_schema_invalid');
                if(preg_match('/COLLATE ([a-z0-9_]+)/',$tail,$c)&&($r['COLLATION_NAME']??null)!==$c[1])throw new RuntimeException('issuer_native_spam_schema_invalid');
            }
            if(array_diff(array_keys($columns),$expected))throw new RuntimeException('issuer_native_spam_schema_invalid');
            $engine=IssuerStorage::query($db,'SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?',[$name])->row_array();if(strtoupper($engine['ENGINE']??'')!=='INNODB')throw new RuntimeException('issuer_native_spam_schema_invalid');
            $indexes=[];foreach(IssuerStorage::query($db,'SELECT INDEX_NAME,COLUMN_NAME,NON_UNIQUE,SUB_PART FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? ORDER BY INDEX_NAME,SEQ_IN_INDEX',[$name])->result_array() as $r){if($r['SUB_PART']!==null)throw new RuntimeException('issuer_native_spam_schema_invalid');if(isset($indexes[$r['INDEX_NAME']]['non_unique'])&&$indexes[$r['INDEX_NAME']]['non_unique']!==(int)$r['NON_UNIQUE'])throw new RuntimeException('issuer_native_spam_schema_invalid');$indexes[$r['INDEX_NAME']]['columns'][]=$r['COLUMN_NAME'];$indexes[$r['INDEX_NAME']]['non_unique']=(int)$r['NON_UNIQUE'];}
            $expectedIndexes=[];
            foreach($parts as $definition){
                if(preg_match('/^(UNIQUE )?KEY `([^`]+)` \(([^)]+)\)$/D',$definition,$m)){$key=$m[2];$cols=explode(',',str_replace('`','',$m[3]));$unique=$m[1]!==''?0:1;}
                elseif(preg_match('/^`([^`]+)` .*PRIMARY KEY/',$definition,$m)){$key='PRIMARY';$cols=[$m[1]];$unique=0;}else continue;
                $expectedIndexes[]=$key;
                if(($indexes[$key]['columns']??[])!==$cols||($indexes[$key]['non_unique']??null)!==$unique)throw new RuntimeException('issuer_native_spam_schema_invalid');
            }
            if(array_diff(array_keys($indexes),$expectedIndexes))throw new RuntimeException('issuer_native_spam_schema_invalid');
            $foreign=[];foreach(IssuerStorage::query($db,'SELECT CONSTRAINT_NAME,COLUMN_NAME,REFERENCED_TABLE_NAME,REFERENCED_COLUMN_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND REFERENCED_TABLE_SCHEMA=DATABASE() ORDER BY CONSTRAINT_NAME,ORDINAL_POSITION',[$name])->result_array() as $r)$foreign[$r['CONSTRAINT_NAME']][]=$r['COLUMN_NAME'].':'.$r['REFERENCED_TABLE_NAME'].':'.$r['REFERENCED_COLUMN_NAME'];
            $expectedForeign=0;
            foreach($parts as $definition)if(preg_match('/^FOREIGN KEY \(([^)]+)\) REFERENCES `([^`]+)` \(([^)]+)\)$/D',$definition,$m)){$expectedForeign++;$cols=explode(',',str_replace('`','',$m[1]));$refs=explode(',',str_replace('`','',$m[3]));$want=[];foreach($cols as $i=>$col)$want[]=$col.':'.$m[2].':'.$refs[$i];if(!in_array($want,array_values($foreign),true))throw new RuntimeException('issuer_native_spam_schema_invalid');}
        if(count($foreign)!==$expectedForeign)throw new RuntimeException('issuer_native_spam_schema_invalid');
        $rules=IssuerStorage::query($db,'SELECT UPDATE_RULE,DELETE_RULE FROM information_schema.REFERENTIAL_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME=?',[$name])->result_array();
        if(count($rules)!==$expectedForeign)throw new RuntimeException('issuer_native_spam_schema_invalid');
        foreach($rules as $rule)foreach(['UPDATE_RULE','DELETE_RULE'] as $key)if(!in_array(strtoupper($rule[$key]??''),['RESTRICT','NO ACTION'],true))throw new RuntimeException('issuer_native_spam_schema_invalid');
    }
}
