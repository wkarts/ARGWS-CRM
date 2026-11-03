<?php

declare(strict_types=1);
require_once __DIR__.'/issuers/IssuerStorage.php';
/** Core-owned knowledge-base storage. Historical articles and groups are never adopted. */
final class NativeCompanyKnowledgeSchema
{
    public const GROUPS = 'company_kb_groups';
    public const ARTICLES = 'company_kb_articles';
    public const FEEDBACK = 'company_kb_feedback';
    public const LEGACY_GROUPS = 'company_kb_legacy_group_links';
    public static function definitions(string $p):array
    {
        if(!preg_match('/^[A-Za-z0-9_]+$/D',$p))throw new InvalidArgumentException('Invalid table prefix');
        $v=static fn($n,$size)=>'`'.$n.'` varchar('.$size.') CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL';
        $base=['`id` int unsigned NOT NULL AUTO_INCREMENT PRIMARY KEY','`issuer_id` int unsigned NOT NULL'];
        $audit=['`revision` int unsigned NOT NULL','`actor_id` int NOT NULL','`created_at` datetime NOT NULL','`updated_at` datetime NOT NULL'];
        $fk=['KEY `actor` (`actor_id`)','FOREIGN KEY (`issuer_id`) REFERENCES `'.$p.'issuers_registry` (`id`)','FOREIGN KEY (`actor_id`) REFERENCES `'.$p.'staff` (`staffid`)'];
        return [
            self::GROUPS=>array_merge($base,[$v('name',191),'`description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL',$v('group_slug',191),$v('color',7),'`active` int NOT NULL','`group_order` int NOT NULL'],$audit,[
                'UNIQUE KEY `issuer_group_slug` (`issuer_id`,`group_slug`)',
                'KEY `issuer_group_order` (`issuer_id`,`group_order`,`id`)',
            ],$fk),
            self::ARTICLES=>array_merge($base,[$v('group_source_kind',40),'`group_source_id` int unsigned NOT NULL',$v('subject',191),$v('slug',191),'`description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL','`active` int NOT NULL','`staff_article` int NOT NULL','`article_order` int NOT NULL'],$audit,[
                'UNIQUE KEY `issuer_article_slug` (`issuer_id`,`slug`)',
                'KEY `issuer_article_group` (`issuer_id`,`group_source_kind`,`group_source_id`,`id`)',
            ],$fk),
            self::LEGACY_GROUPS=>array_merge($base,['`legacy_article_id` int NOT NULL', $v('group_source_kind',40),'`group_source_id` int unsigned NOT NULL'],$audit,[
                'UNIQUE KEY `issuer_legacy_article` (`issuer_id`,`legacy_article_id`)',
            ],$fk),
            self::FEEDBACK=>array_merge($base,[$v('article_source_kind',40),'`article_source_id` int unsigned NOT NULL',$v('respondent_hash',64),'`answer` int NOT NULL','`created_at` datetime NOT NULL',
                'KEY `issuer_feedback_respondent` (`issuer_id`,`article_source_kind`,`article_source_id`,`respondent_hash`,`created_at`)',
                'FOREIGN KEY (`issuer_id`) REFERENCES `'.$p.'issuers_registry` (`id`)',
            ]),
        ];
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
                if($actual!==$type||($r['IS_NULLABLE']??null)!==$nullable||!IssuerStorage::sqlNullDefault($r)||strtolower($r['EXTRA']??'')!==(str_contains($tail,'AUTO_INCREMENT')?'auto_increment':''))throw new RuntimeException('issuer_native_knowledge_schema_invalid');
                if(preg_match('/COLLATE ([a-z0-9_]+)/',$tail,$c)&&($r['COLLATION_NAME']??null)!==$c[1])throw new RuntimeException('issuer_native_knowledge_schema_invalid');
            }
            if(array_diff(array_keys($columns),$expected))throw new RuntimeException('issuer_native_knowledge_schema_invalid');
            $engine=IssuerStorage::query($db,'SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?',[$name])->row_array();if(strtoupper($engine['ENGINE']??'')!=='INNODB')throw new RuntimeException('issuer_native_knowledge_schema_invalid');
            $indexes=[];foreach(IssuerStorage::query($db,'SELECT INDEX_NAME,COLUMN_NAME,NON_UNIQUE,SUB_PART FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? ORDER BY INDEX_NAME,SEQ_IN_INDEX',[$name])->result_array() as $r){if($r['SUB_PART']!==null)throw new RuntimeException('issuer_native_knowledge_schema_invalid');if(isset($indexes[$r['INDEX_NAME']]['non_unique'])&&$indexes[$r['INDEX_NAME']]['non_unique']!==(int)$r['NON_UNIQUE'])throw new RuntimeException('issuer_native_knowledge_schema_invalid');$indexes[$r['INDEX_NAME']]['columns'][]=$r['COLUMN_NAME'];$indexes[$r['INDEX_NAME']]['non_unique']=(int)$r['NON_UNIQUE'];}
            $expectedIndexes=[];
            foreach($parts as $definition){
                if(preg_match('/^(UNIQUE )?KEY `([^`]+)` \(([^)]+)\)$/D',$definition,$m)){$key=$m[2];$cols=explode(',',str_replace('`','',$m[3]));$unique=$m[1]!==''?0:1;}
                elseif(preg_match('/^`([^`]+)` .*PRIMARY KEY/',$definition,$m)){$key='PRIMARY';$cols=[$m[1]];$unique=0;}else continue;
                $expectedIndexes[]=$key;
                if(($indexes[$key]['columns']??[])!==$cols||($indexes[$key]['non_unique']??null)!==$unique)throw new RuntimeException('issuer_native_knowledge_schema_invalid');
            }
            if(array_diff(array_keys($indexes),$expectedIndexes))throw new RuntimeException('issuer_native_knowledge_schema_invalid');
            $foreign=[];foreach(IssuerStorage::query($db,'SELECT CONSTRAINT_NAME,COLUMN_NAME,REFERENCED_TABLE_NAME,REFERENCED_COLUMN_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND REFERENCED_TABLE_SCHEMA=DATABASE() ORDER BY CONSTRAINT_NAME,ORDINAL_POSITION',[$name])->result_array() as $r)$foreign[$r['CONSTRAINT_NAME']][]=$r['COLUMN_NAME'].':'.$r['REFERENCED_TABLE_NAME'].':'.$r['REFERENCED_COLUMN_NAME'];
            $expectedForeign=0;
            foreach($parts as $definition)if(preg_match('/^FOREIGN KEY \(([^)]+)\) REFERENCES `([^`]+)` \(([^)]+)\)$/D',$definition,$m)){$expectedForeign++;$cols=explode(',',str_replace('`','',$m[1]));$refs=explode(',',str_replace('`','',$m[3]));$want=[];foreach($cols as $i=>$col)$want[]=$col.':'.$m[2].':'.$refs[$i];if(!in_array($want,array_values($foreign),true))throw new RuntimeException('issuer_native_knowledge_schema_invalid');}
        if(count($foreign)!==$expectedForeign)throw new RuntimeException('issuer_native_knowledge_schema_invalid');
        $rules=IssuerStorage::query($db,'SELECT UPDATE_RULE,DELETE_RULE FROM information_schema.REFERENTIAL_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME=?',[$name])->result_array();
        if(count($rules)!==$expectedForeign)throw new RuntimeException('issuer_native_knowledge_schema_invalid');
        foreach($rules as $rule)foreach(['UPDATE_RULE','DELETE_RULE'] as $key)if(!in_array(strtoupper($rule[$key]??''),['RESTRICT','NO ACTION'],true))throw new RuntimeException('issuer_native_knowledge_schema_invalid');
    }
}
