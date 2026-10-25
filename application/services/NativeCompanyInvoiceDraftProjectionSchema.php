<?php

declare(strict_types=1);
require_once __DIR__.'/issuers/IssuerStorage.php';require_once __DIR__.'/issuers/IssuerTransaction.php';
/** Additive exact monetary read projection; never allocates invoice identity. */
final class NativeCompanyInvoiceDraftProjectionSchema
{
    public const TABLE='company_invoice_draft_projection';
    public static function definitions(string $p):array
    {
        if(!preg_match('/^[A-Za-z0-9_]+$/D',$p))throw new InvalidArgumentException('Invalid prefix');
        return [self::TABLE=>[
            '`id` int NOT NULL PRIMARY KEY','`issuer_id` int unsigned NOT NULL','`revision` int NOT NULL',
            '`source_hash` varchar(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL',
            '`state` varchar(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL',
            '`clientid` int NOT NULL','`sale_agent` int NOT NULL','`project_id` int NOT NULL','`currency` int NOT NULL','`date` date NOT NULL','`duedate` date NULL',
            '`customer` mediumtext NOT NULL',
            '`subtotal` decimal(15,2) NOT NULL','`total` decimal(15,2) NOT NULL','`total_tax` decimal(15,2) NOT NULL',
            '`discount_total` decimal(15,2) NOT NULL','`adjustment` decimal(15,2) NOT NULL',
            'KEY `draft_projection_owner` (`issuer_id`,`state`,`id`)',
            'FOREIGN KEY (`id`) REFERENCES `'.$p.'company_invoice_drafts` (`id`)',
            'FOREIGN KEY (`issuer_id`) REFERENCES `'.$p.'issuers_registry` (`id`)'
        ]];
    }
    public static function ensure($db,string $p):void
    {
        $definitions=self::definitions($p);IssuerTransaction::assertIndependent($db);foreach($definitions as $table=>$parts)IssuerStorage::query($db,'CREATE TABLE IF NOT EXISTS `'.$p.$table.'` ('.implode(',',$parts).') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin');
        if(property_exists($db,'data_cache'))unset($db->data_cache['table_names']);
        self::assertValid($db,$p);
    }
    public static function assertValid($db,string $p):void
    {
        self::definitions($p);$conn=$db->pdo??$db->conn_id??null;if($conn instanceof PDO&&$conn->getAttribute(PDO::ATTR_DRIVER_NAME)==='sqlite'){
            $expected=[];foreach(self::definitions($p)[self::TABLE]as$definition)if(preg_match('/^`([^`]+)` ([a-z]+(?:\([0-9,]+\))?(?: unsigned)?)/',$definition,$m))$expected[$m[1]]=$m[2];
            $rows=IssuerStorage::query($db,'PRAGMA table_info(`'.$p.self::TABLE.'`)')->result_array();if(array_column($rows,'name')!==array_keys($expected))throw new RuntimeException('native_invoice_draft_projection_schema_invalid');
            foreach($rows as$r)if(strtolower($r['type'])!==$expected[$r['name']]||$r['dflt_value']!==null||(int)$r['notnull']!==($r['name']==='duedate'?0:1)||(int)$r['pk']!==($r['name']==='id'?1:0))throw new RuntimeException('native_invoice_draft_projection_schema_invalid');
            $fk=IssuerStorage::query($db,'PRAGMA foreign_key_list(`'.$p.self::TABLE.'`)')->result_array();$actual=[];foreach($fk as$r){if(!in_array($r['on_delete'],['RESTRICT','NO ACTION'],true)||!in_array($r['on_update'],['RESTRICT','NO ACTION'],true))throw new RuntimeException('native_invoice_draft_projection_schema_invalid');$actual[]=$r['from'].':'.$r['table'].':'.$r['to'];}sort($actual);$wanted=['id:'.$p.'company_invoice_drafts:id','issuer_id:'.$p.'issuers_registry:id'];sort($wanted);if($actual!==$wanted)throw new RuntimeException('native_invoice_draft_projection_schema_invalid');return;
        }
        foreach(self::definitions($p) as $table=>$parts){
            $name=$p.$table;$columns=[];
            foreach(IssuerStorage::query($db,'SELECT COLUMN_NAME,COLUMN_TYPE,IS_NULLABLE,COLUMN_DEFAULT,COLLATION_NAME,EXTRA FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?',[$name])->result_array() as $r)$columns[$r['COLUMN_NAME']]=$r;
            $expected=[];
            foreach($parts as $definition){
                if(!preg_match('/^`([^`]+)` (decimal\([0-9]+,[0-9]+\)|varchar\([0-9]+\)|bigint unsigned|int unsigned|int|tinyint|mediumblob|mediumtext|text|datetime|date)(.*)$/D',$definition,$m))continue;
                [$all,$column,$type,$tail]=$m;$expected[]=$column;$r=$columns[$column]??[];
                $actual=preg_replace('/^(tinyint|int|bigint)\([0-9]+\)/','$1',strtolower($r['COLUMN_TYPE']??''));$nullable=str_contains($tail,'NOT NULL')?'NO':'YES';
                if($actual!==$type||($r['IS_NULLABLE']??null)!==$nullable||!IssuerStorage::sqlNullDefault($r)||str_contains($tail,'AUTO_INCREMENT')!==str_contains(strtolower($r['EXTRA']??''),'auto_increment'))throw new RuntimeException('native_invoice_draft_projection_schema_invalid');
                if(preg_match('/COLLATE ([a-z0-9_]+)/',$tail,$c)&&($r['COLLATION_NAME']??null)!==$c[1])throw new RuntimeException('native_invoice_draft_projection_schema_invalid');
            }
            if(($columns['customer']['COLLATION_NAME']??null)!=='utf8mb4_bin')throw new RuntimeException('native_invoice_draft_projection_schema_invalid');
            if(array_diff(array_keys($columns),$expected))throw new RuntimeException('native_invoice_draft_projection_schema_invalid');
            $engine=IssuerStorage::query($db,'SELECT ENGINE,TABLE_COLLATION FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?',[$name])->row_array();if(strtoupper($engine['ENGINE']??'')!=='INNODB'||($engine['TABLE_COLLATION']??'')!=='utf8mb4_bin')throw new RuntimeException('native_invoice_draft_projection_schema_invalid');
            $indexes=[];foreach(IssuerStorage::query($db,'SELECT INDEX_NAME,COLUMN_NAME,NON_UNIQUE,SUB_PART FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? ORDER BY INDEX_NAME,SEQ_IN_INDEX',[$name])->result_array() as $r){if($r['SUB_PART']!==null)throw new RuntimeException('native_invoice_draft_projection_schema_invalid');$indexes[$r['INDEX_NAME']]['columns'][]=$r['COLUMN_NAME'];$indexes[$r['INDEX_NAME']]['non_unique']=(int)$r['NON_UNIQUE'];}
            foreach($parts as $definition){
                if(preg_match('/^(UNIQUE )?KEY `([^`]+)` \(([^)]+)\)$/D',$definition,$m)){$key=$m[2];$cols=explode(',',str_replace('`','',$m[3]));$unique=$m[1]!==''?0:1;}
                elseif(preg_match('/^PRIMARY KEY \(([^)]+)\)$/D',$definition,$m)){$key='PRIMARY';$cols=explode(',',str_replace('`','',$m[1]));$unique=0;}
                elseif(preg_match('/^`([^`]+)` .*PRIMARY KEY/',$definition,$m)){$key='PRIMARY';$cols=[$m[1]];$unique=0;}else continue;
                if(($indexes[$key]['columns']??[])!==$cols||($indexes[$key]['non_unique']??null)!==$unique)throw new RuntimeException('native_invoice_draft_projection_schema_invalid');
            }
            $foreign=[];foreach(IssuerStorage::query($db,'SELECT CONSTRAINT_NAME,COLUMN_NAME,REFERENCED_TABLE_NAME,REFERENCED_COLUMN_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND REFERENCED_TABLE_SCHEMA=DATABASE() ORDER BY CONSTRAINT_NAME,ORDINAL_POSITION',[$name])->result_array() as $r)$foreign[$r['CONSTRAINT_NAME']][]=$r['COLUMN_NAME'].':'.$r['REFERENCED_TABLE_NAME'].':'.$r['REFERENCED_COLUMN_NAME'];
            foreach($parts as $definition)if(preg_match('/^FOREIGN KEY \(([^)]+)\) REFERENCES `([^`]+)` \(([^)]+)\)$/D',$definition,$m)){$cols=explode(',',str_replace('`','',$m[1]));$refs=explode(',',str_replace('`','',$m[3]));$want=[];foreach($cols as $i=>$col)$want[]=$col.':'.$m[2].':'.$refs[$i];if(!in_array($want,array_values($foreign),true))throw new RuntimeException('native_invoice_draft_projection_schema_invalid');}
        }
    }
}
