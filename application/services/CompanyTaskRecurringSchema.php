<?php
declare(strict_types=1);
require_once __DIR__.'/issuers/IssuerStorage.php';
require_once __DIR__.'/issuers/IssuerTransaction.php';

/** Additive task calendar ledger. Only the native migration invokes ensure(). */
final class CompanyTaskRecurringSchema
{
    public static function definitions(string $p):array
    {
        if(!preg_match('/^[A-Za-z0-9_]+$/D',$p))throw new InvalidArgumentException('Invalid prefix');
        $hash=static fn($n)=>'`'.$n.'` varchar(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL';
        $word=static fn($n)=>'`'.$n.'` varchar(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL';
        $json=static fn($n)=>'`'.$n.'` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL';
        return [
            'company_task_schedules'=>[
                '`id` int unsigned NOT NULL AUTO_INCREMENT PRIMARY KEY','`issuer_id` int unsigned NOT NULL','`actor_id` int NOT NULL',
                $word('kind'),'`source_id` int unsigned NOT NULL',$hash('request_key'),$hash('input_hash'),$hash('contract_hash'),$json('contract_json'),
                $word('state'),'`revision` int unsigned NOT NULL','`completed_cycles` int unsigned NOT NULL','`next_date` date NULL',
                '`created_at` datetime NOT NULL','`updated_at` datetime NOT NULL',
                'UNIQUE KEY `task_schedule_owner` (`issuer_id`,`id`)','UNIQUE KEY `task_schedule_creation` (`request_key`)',
                'KEY `task_schedule_source` (`issuer_id`,`kind`,`source_id`,`id`)','KEY `task_schedule_discovery` (`state`,`id`)',
                'FOREIGN KEY (`issuer_id`) REFERENCES `'.$p.'issuers_registry` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT',
            ],
            'company_task_schedule_runs'=>[
                '`issuer_id` int unsigned NOT NULL','`schedule_id` int unsigned NOT NULL','`cycle` int unsigned NOT NULL','`revision` int unsigned NOT NULL',
                $word('kind'),'`run_date` date NOT NULL','`task_id` int unsigned NOT NULL',$hash('request_key'),$hash('contract_hash'),$hash('birth_hash'),$json('birth_json'),'`created_at` datetime NOT NULL',
                'PRIMARY KEY (`issuer_id`,`schedule_id`,`cycle`)','UNIQUE KEY `task_schedule_run_key` (`request_key`)',
                'UNIQUE KEY `task_schedule_child` (`issuer_id`,`kind`,`task_id`)',
                'FOREIGN KEY (`issuer_id`,`schedule_id`) REFERENCES `'.$p.'company_task_schedules` (`issuer_id`,`id`) ON DELETE RESTRICT ON UPDATE RESTRICT',
            ],
            'company_task_schedule_requests'=>[
                $hash('request_key').' PRIMARY KEY','`issuer_id` int unsigned NOT NULL','`actor_id` int NOT NULL','`schedule_id` int unsigned NOT NULL',
                $word('action'),$hash('input_hash'),$hash('result_hash'),$json('result_json'),'`created_at` datetime NOT NULL',
                'KEY `task_schedule_request_owner` (`issuer_id`,`schedule_id`)',
                'FOREIGN KEY (`issuer_id`,`schedule_id`) REFERENCES `'.$p.'company_task_schedules` (`issuer_id`,`id`) ON DELETE RESTRICT ON UPDATE RESTRICT',
            ],
            'company_task_schedule_cursor'=>[
                '`id` tinyint unsigned NOT NULL PRIMARY KEY','`last_id` int unsigned NOT NULL','`upper_id` int unsigned NOT NULL','`revision` int unsigned NOT NULL',
            ],
        ];
    }
    public static function ensure($db,string $p):void
    {
        IssuerTransaction::assertIndependent($db);
        foreach(self::definitions($p)as$t=>$parts)IssuerStorage::query($db,'CREATE TABLE IF NOT EXISTS `'.$p.$t.'` ('.implode(',',$parts).') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin');
        IssuerStorage::query($db,'INSERT INTO '.$p.'company_task_schedule_cursor(id,last_id,upper_id,revision) SELECT 1,0,0,1 WHERE NOT EXISTS (SELECT 1 FROM '.$p.'company_task_schedule_cursor WHERE id=1)');
        if(isset($db->data_cache['table_names']))unset($db->data_cache['table_names']);self::assertValid($db,$p);
    }
    private static function fail():never{throw new RuntimeException('issuer_task_recurring_schema_invalid');}
    public static function assertValid($db,string $p):void
    {
        $rows=static fn($sql,$args=[])=>IssuerStorage::query($db,$sql,$args)->result_array();
        foreach(self::definitions($p)as$t=>$parts){
            $name=$p.$t;$meta=$rows('SELECT ENGINE,TABLE_COLLATION FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?',[$name])[0]??[];
            if(strtoupper($meta['ENGINE']??'')!=='INNODB'||($meta['TABLE_COLLATION']??'')!=='utf8mb4_bin')self::fail();
            $columns=$rows('SELECT COLUMN_NAME,COLUMN_TYPE,IS_NULLABLE,COLUMN_DEFAULT,COLLATION_NAME,EXTRA FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? ORDER BY ORDINAL_POSITION',[$name]);$wanted=[];
            foreach($parts as$d)if(preg_match('/^`([^`]+)` (varchar\([0-9]+\)|int unsigned|int|tinyint unsigned|mediumtext|datetime|date)(.*)$/D',$d,$m)){
                $n=count($wanted);$wanted[]=$m[1];$r=$columns[$n]??[];$type=preg_replace('/^(tinyint|int)\([0-9]+\)/','$1',strtolower($r['COLUMN_TYPE']??''));
                $collation=null;if(preg_match('/COLLATE ([a-z0-9_]+)/',$m[3],$cm))$collation=$cm[1];
                if(($r['COLUMN_NAME']??null)!==$m[1]||$type!==$m[2]||($r['IS_NULLABLE']??null)!==(str_contains($m[3],'NOT NULL')?'NO':'YES')||!IssuerStorage::sqlNullDefault($r)||($r['COLLATION_NAME']??null)!==$collation||strtolower($r['EXTRA']??'')!==(str_contains($m[3],'AUTO_INCREMENT')?'auto_increment':''))self::fail();
            }
            if(count($columns)!==count($wanted))self::fail();
            $indexes=[];foreach($rows('SELECT INDEX_NAME,COLUMN_NAME,NON_UNIQUE,SUB_PART FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? ORDER BY INDEX_NAME,SEQ_IN_INDEX',[$name])as$r){if($r['SUB_PART']!==null)self::fail();$indexes[$r['INDEX_NAME']]['columns'][]=$r['COLUMN_NAME'];$indexes[$r['INDEX_NAME']]['non_unique']=(int)$r['NON_UNIQUE'];}
            $required=[];
            foreach($parts as$d){
                if(preg_match('/^(UNIQUE )?KEY `([^`]+)` \(([^)]+)\)$/D',$d,$m))$required[$m[2]]=['columns'=>explode(',',str_replace('`','',$m[3])),'non_unique'=>$m[1]!==''?0:1];
                elseif(preg_match('/^PRIMARY KEY \(([^)]+)\)$/D',$d,$m))$required['PRIMARY']=['columns'=>explode(',',str_replace('`','',$m[1])),'non_unique'=>0];
                elseif(preg_match('/^`([^`]+)` .*PRIMARY KEY/',$d,$m))$required['PRIMARY']=['columns'=>[$m[1]],'non_unique'=>0];
            }
            ksort($required);ksort($indexes);if($required!==$indexes)self::fail();
            $expected=[];foreach($parts as$d)if(preg_match('/^FOREIGN KEY \(([^)]+)\) REFERENCES `([^`]+)` \(([^)]+)\) ON DELETE RESTRICT ON UPDATE RESTRICT$/D',$d,$m)){
                $fields=explode(',',str_replace('`','',$m[1]));$target=explode(',',str_replace('`','',$m[3]));$f=[];foreach($fields as$n=>$field)$f[]=$field.':'.$m[2].':'.$target[$n];$expected[]=implode('|',$f);
            }
            $foreign=[];foreach($rows('SELECT CONSTRAINT_NAME,COLUMN_NAME,REFERENCED_TABLE_NAME,REFERENCED_COLUMN_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND REFERENCED_TABLE_SCHEMA=DATABASE() ORDER BY CONSTRAINT_NAME,ORDINAL_POSITION',[$name])as$r)$foreign[$r['CONSTRAINT_NAME']][]=$r['COLUMN_NAME'].':'.$r['REFERENCED_TABLE_NAME'].':'.$r['REFERENCED_COLUMN_NAME'];
            $actual=array_map(static fn($v)=>implode('|',$v),array_values($foreign));sort($actual);sort($expected);if($actual!==$expected)self::fail();
            $rules=$rows('SELECT DELETE_RULE,UPDATE_RULE FROM information_schema.REFERENTIAL_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME=?',[$name]);if(count($rules)!==count($expected))self::fail();foreach($rules as$r)if(!in_array($r['DELETE_RULE'],['RESTRICT','NO ACTION'],true)||!in_array($r['UPDATE_RULE'],['RESTRICT','NO ACTION'],true))self::fail();
        }
    }
}
