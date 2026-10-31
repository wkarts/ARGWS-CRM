<?php

declare(strict_types=1);
require_once __DIR__.'/issuers/IssuerStorage.php';
/** Core-owned header state. No adoption of global notification/history rows. */
final class NativeCompanyHeaderSchema
{
    public const NOTIFICATIONS = 'company_native_notifications';
    public const HISTORY = 'company_native_search_history';
    public static function definitions(string $p):array
    {
        if(!preg_match('/^[A-Za-z0-9_]+$/D',$p))throw new InvalidArgumentException('Invalid table prefix');
        $text=static fn($n,$size)=>'`'.$n.'` varchar('.$size.') CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL';
        return [
            self::NOTIFICATIONS=>[
                '`id` int unsigned NOT NULL AUTO_INCREMENT PRIMARY KEY',
                '`issuer_id` int unsigned NOT NULL','`recipient_id` int NOT NULL',$text('actor_kind',16),'`actor_id` int NULL','`contact_actor_id` int NULL',
                $text('source_kind',40),'`source_id` int unsigned NOT NULL','`resource_id` bigint unsigned NOT NULL',
                $text('event_key',128),$text('title',191),$text('body',2000),
                '`created_at` datetime NOT NULL','`read_at` datetime NULL','`inline_read_at` datetime NULL',
                'UNIQUE KEY `notification_event` (`issuer_id`,`recipient_id`,`event_key`)',
                'KEY `notification_recipient` (`issuer_id`,`recipient_id`,`id`)',
                'KEY `notification_staff` (`recipient_id`)','KEY `notification_actor` (`actor_id`)','KEY `notification_contact_actor` (`contact_actor_id`)',
                'FOREIGN KEY (`issuer_id`) REFERENCES `'.$p.'issuers_registry` (`id`)',
                'FOREIGN KEY (`recipient_id`) REFERENCES `'.$p.'staff` (`staffid`)',
                'FOREIGN KEY (`actor_id`) REFERENCES `'.$p.'staff` (`staffid`)',
                'FOREIGN KEY (`contact_actor_id`) REFERENCES `'.$p.'contacts` (`id`)',
            ],
            self::HISTORY=>[
                '`id` int unsigned NOT NULL AUTO_INCREMENT PRIMARY KEY','`issuer_id` int unsigned NOT NULL','`staff_id` int NOT NULL',
                $text('query_text',191),'`revision` int unsigned NOT NULL','`created_at` datetime NOT NULL','`updated_at` datetime NOT NULL',
                'UNIQUE KEY `search_query` (`issuer_id`,`staff_id`,`query_text`)',
                'KEY `search_owner` (`issuer_id`,`staff_id`,`id`)', 'KEY `search_staff` (`staff_id`)',
                'FOREIGN KEY (`issuer_id`) REFERENCES `'.$p.'issuers_registry` (`id`)',
                'FOREIGN KEY (`staff_id`) REFERENCES `'.$p.'staff` (`staffid`)',
            ],
        ];
    }
    private static function sqlite($db):bool
    {
        $pdo=$db->pdo??$db->conn_id??null;return $pdo instanceof PDO&&$pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='sqlite';
    }
    /** SQLite executes local integration contracts only; MySQL metadata remains independently mandatory in CI. */
    private static function sqliteDdl(string$p,string$table,array$parts):array
    {
        $columns=[];$indexes=[];
        foreach($parts as$part){
            if(str_starts_with($part,'KEY ')){preg_match('/^KEY `([^`]+)` (.+)$/D',$part,$m);$indexes[]='CREATE INDEX `'.$p.$table.'_'.$m[1].'` ON `'.$p.$table.'` '.$m[2];continue;}
            if(str_starts_with($part,'UNIQUE KEY ')){$columns[]=preg_replace('/^UNIQUE KEY `[^`]+`/','UNIQUE',$part);continue;}
            if(str_starts_with($part,'`id`')){$columns[]='`id` INTEGER PRIMARY KEY AUTOINCREMENT';continue;}
            $columns[]=preg_replace('/ CHARACTER SET [a-z0-9_]+ COLLATE [a-z0-9_]+/','',str_replace(['bigint unsigned','int unsigned'],['bigint','int'],$part));
        }
        return array_merge(['CREATE TABLE `'.$p.$table.'` ('.implode(',',$columns).')'],$indexes);
    }
    private static function assertSqlite($db,string$p,string$table,array$parts):void
    {
        $fail=static fn()=>throw new RuntimeException('issuer_native_header_schema_invalid');$name=$p.$table;
        $actual=IssuerStorage::query($db,'PRAGMA table_info(`'.$name.'`)')->result_array();$columns=[];$indexes=[];$foreign=[];
        foreach($parts as$part){
            if(preg_match('/^`([^`]+)` (varchar\([0-9]+\)|bigint unsigned|int unsigned|int|datetime)(.*)$/D',$part,$m)){
                $columns[]=['name'=>$m[1],'type'=>$m[1]==='id'?'INTEGER':strtoupper(str_replace(' unsigned','',$m[2])),'notnull'=>$m[1]==='id'?0:(str_contains($m[3],'NOT NULL')?1:0),'pk'=>$m[1]==='id'?1:0];
            }elseif(preg_match('/^(UNIQUE )?KEY `[^`]+` \(([^)]+)\)$/D',$part,$m))$indexes[]=[($m[1]!==''?1:0),str_replace('`','',$m[2])];
            elseif(preg_match('/^FOREIGN KEY \(`([^`]+)`\) REFERENCES `([^`]+)` \(`([^`]+)`\)$/D',$part,$m))$foreign[]=$m[1].':'.$m[2].':'.$m[3];
        }
        if(count($actual)!==count($columns))$fail();foreach($actual as$i=>$r){$r['type']=strtoupper((string)$r['type']);foreach($columns[$i]as$key=>$value)if((string)($r[$key]??'')!==(string)$value)$fail();if($r['dflt_value']!==null)$fail();}
        $sql=IssuerStorage::query($db,'SELECT sql FROM sqlite_master WHERE type=? AND name=?',['table',$name])->row_array()['sql']??'';if(!str_contains(strtoupper($sql),'AUTOINCREMENT'))$fail();
        $found=[];foreach(IssuerStorage::query($db,'PRAGMA index_list(`'.$name.'`)')->result_array()as$idx){if((int)$idx['partial']!==0||!preg_match('/^[A-Za-z0-9_]+$/D',$idx['name']))$fail();$cols=[];foreach(IssuerStorage::query($db,'PRAGMA index_xinfo(`'.$idx['name'].'`)')->result_array()as$c){if((int)$c['key']!==1)continue;if((int)$c['cid']<0||(int)$c['desc']!==0||strtoupper($c['coll'])!=='BINARY')$fail();$cols[]=$c['name'];}$found[]=[(int)$idx['unique'],implode(',',$cols)];}sort($indexes);sort($found);if($indexes!==$found)$fail();
        $found=[];foreach(IssuerStorage::query($db,'PRAGMA foreign_key_list(`'.$name.'`)')->result_array()as$fk){if((int)$fk['seq']!==0||!in_array($fk['on_update'],['RESTRICT','NO ACTION'],true)||!in_array($fk['on_delete'],['RESTRICT','NO ACTION'],true))$fail();$found[]=$fk['from'].':'.$fk['table'].':'.$fk['to'];}sort($foreign);sort($found);if($foreign!==$found)$fail();
    }
    public static function ensure($db,string $p):void
    {
        if(self::sqlite($db)){
            $missing=[];foreach(self::definitions($p)as$table=>$parts){$found=IssuerStorage::query($db,'SELECT name FROM sqlite_master WHERE type=? AND name=?',['table',$p.$table])->result_array();if($found)self::assertSqlite($db,$p,$table,$parts);else$missing[$table]=$parts;}
            foreach($missing as$table=>$parts)foreach(self::sqliteDdl($p,$table,$parts)as$sql)IssuerStorage::query($db,$sql);self::assertValid($db,$p);return;
        }
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
            if(self::sqlite($db)){self::assertSqlite($db,$p,$table,$parts);return;}
            $name=$p.$table;$columns=[];
            foreach(IssuerStorage::query($db,'SELECT COLUMN_NAME,COLUMN_TYPE,IS_NULLABLE,COLUMN_DEFAULT,COLLATION_NAME,EXTRA FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?',[$name])->result_array() as $r)$columns[$r['COLUMN_NAME']]=$r;
            $expected=[];
            foreach($parts as $definition){
                if(!preg_match('/^`([^`]+)` (varchar\([0-9]+\)|bigint unsigned|int unsigned|int|text|datetime|date)(.*)$/D',$definition,$m))continue;
                [$all,$column,$type,$tail]=$m;$expected[]=$column;$r=$columns[$column]??[];
                $actual=preg_replace('/^(bigint|int)\([0-9]+\)/','$1',strtolower($r['COLUMN_TYPE']??''));$nullable=str_contains($tail,'NOT NULL')?'NO':'YES';
                if($actual!==$type||($r['IS_NULLABLE']??null)!==$nullable||!IssuerStorage::sqlNullDefault($r)||strtolower($r['EXTRA']??'')!==(str_contains($tail,'AUTO_INCREMENT')?'auto_increment':''))throw new RuntimeException('issuer_native_header_schema_invalid');
                if(preg_match('/COLLATE ([a-z0-9_]+)/',$tail,$c)&&($r['COLLATION_NAME']??null)!==$c[1])throw new RuntimeException('issuer_native_header_schema_invalid');
            }
            if(array_diff(array_keys($columns),$expected))throw new RuntimeException('issuer_native_header_schema_invalid');
            $engine=IssuerStorage::query($db,'SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?',[$name])->row_array();if(strtoupper($engine['ENGINE']??'')!=='INNODB')throw new RuntimeException('issuer_native_header_schema_invalid');
            $indexes=[];foreach(IssuerStorage::query($db,'SELECT INDEX_NAME,COLUMN_NAME,NON_UNIQUE,SUB_PART FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? ORDER BY INDEX_NAME,SEQ_IN_INDEX',[$name])->result_array() as $r){if($r['SUB_PART']!==null)throw new RuntimeException('issuer_native_header_schema_invalid');if(isset($indexes[$r['INDEX_NAME']]['non_unique'])&&$indexes[$r['INDEX_NAME']]['non_unique']!==(int)$r['NON_UNIQUE'])throw new RuntimeException('issuer_native_header_schema_invalid');$indexes[$r['INDEX_NAME']]['columns'][]=$r['COLUMN_NAME'];$indexes[$r['INDEX_NAME']]['non_unique']=(int)$r['NON_UNIQUE'];}
            $expectedIndexes=[];
            foreach($parts as $definition){
                if(preg_match('/^(UNIQUE )?KEY `([^`]+)` \(([^)]+)\)$/D',$definition,$m)){$key=$m[2];$cols=explode(',',str_replace('`','',$m[3]));$unique=$m[1]!==''?0:1;}
                elseif(preg_match('/^`([^`]+)` .*PRIMARY KEY/',$definition,$m)){$key='PRIMARY';$cols=[$m[1]];$unique=0;}else continue;
                $expectedIndexes[]=$key;
                if(($indexes[$key]['columns']??[])!==$cols||($indexes[$key]['non_unique']??null)!==$unique)throw new RuntimeException('issuer_native_header_schema_invalid');
            }
            if(array_diff(array_keys($indexes),$expectedIndexes))throw new RuntimeException('issuer_native_header_schema_invalid');
            $foreign=[];foreach(IssuerStorage::query($db,'SELECT CONSTRAINT_NAME,COLUMN_NAME,REFERENCED_TABLE_NAME,REFERENCED_COLUMN_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND REFERENCED_TABLE_SCHEMA=DATABASE() ORDER BY CONSTRAINT_NAME,ORDINAL_POSITION',[$name])->result_array() as $r)$foreign[$r['CONSTRAINT_NAME']][]=$r['COLUMN_NAME'].':'.$r['REFERENCED_TABLE_NAME'].':'.$r['REFERENCED_COLUMN_NAME'];
            $expectedForeign=0;
            foreach($parts as $definition)if(preg_match('/^FOREIGN KEY \(([^)]+)\) REFERENCES `([^`]+)` \(([^)]+)\)$/D',$definition,$m)){$expectedForeign++;$cols=explode(',',str_replace('`','',$m[1]));$refs=explode(',',str_replace('`','',$m[3]));$want=[];foreach($cols as $i=>$col)$want[]=$col.':'.$m[2].':'.$refs[$i];if(!in_array($want,array_values($foreign),true))throw new RuntimeException('issuer_native_header_schema_invalid');}
        if(count($foreign)!==$expectedForeign)throw new RuntimeException('issuer_native_header_schema_invalid');
        $rules=IssuerStorage::query($db,'SELECT UPDATE_RULE,DELETE_RULE FROM information_schema.REFERENTIAL_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME=?',[$name])->result_array();
        if(count($rules)!==$expectedForeign)throw new RuntimeException('issuer_native_header_schema_invalid');
        foreach($rules as $rule)foreach(['UPDATE_RULE','DELETE_RULE'] as $key)if(!in_array(strtoupper($rule[$key]??''),['RESTRICT','NO ACTION'],true))throw new RuntimeException('issuer_native_header_schema_invalid');
    }
}
