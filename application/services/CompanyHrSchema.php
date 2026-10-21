<?php

declare(strict_types=1);
require_once __DIR__.'/issuers/IssuerStorage.php';
require_once __DIR__.'/issuers/IssuerTransaction.php';

/** Private employment storage. Only a native migration may call ensure(). */
final class CompanyHrSchema
{
    public static function definitions(string $p): array
    {
        if (!preg_match('/^[A-Za-z0-9_]+$/D', $p)) { throw new InvalidArgumentException('Invalid prefix'); }
        $text = static fn($n, $l) => '`'.$n.'` varchar('.$l.') CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL';
        $word = static fn($n, $l=32) => '`'.$n.'` varchar('.$l.') CHARACTER SET ascii COLLATE ascii_bin NOT NULL';
        $json = static fn($n) => '`'.$n.'` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL';
        $base = ['`id` int unsigned NOT NULL AUTO_INCREMENT PRIMARY KEY','`issuer_id` int unsigned NOT NULL'];
        $version = ['`revision` int unsigned NOT NULL','`created_at` datetime NOT NULL','`updated_at` datetime NOT NULL'];
        $owner = static fn($n) => 'UNIQUE KEY `'.$n.'_owner` (`issuer_id`,`id`)';
        $fk = static fn($fields, $table, $targets) => 'FOREIGN KEY ('.$fields.') REFERENCES `'.$p.$table.'` ('.$targets.') ON DELETE RESTRICT ON UPDATE RESTRICT';
        $issuer = $fk('`issuer_id`','issuers_registry','`id`');
        $employee = $fk('`issuer_id`,`employee_id`','company_hr_employees','`issuer_id`,`id`');
        $defs = [];
        $defs['company_hr_units'] = array_merge($base, [$text('name',120),'`parent_id` int unsigned NULL','`active` tinyint NOT NULL'], $version, [$owner('hr_unit'),'UNIQUE KEY `hr_unit_name` (`issuer_id`,`name`)','KEY `hr_unit_parent` (`issuer_id`,`parent_id`)',$issuer,$fk('`issuer_id`,`parent_id`','company_hr_units','`issuer_id`,`id`')]);
        $defs['company_hr_positions'] = array_merge($base, [$text('name',120),'`unit_id` int unsigned NOT NULL',$text('description',4000),'`active` tinyint NOT NULL'], $version, [$owner('hr_position'),'UNIQUE KEY `hr_position_name` (`issuer_id`,`unit_id`,`name`)',$fk('`issuer_id`,`unit_id`','company_hr_units','`issuer_id`,`id`')]);
        $defs['company_hr_workplaces'] = array_merge($base, [$text('name',120),$text('address',500),'`active` tinyint NOT NULL'], $version, [$owner('hr_workplace'),'UNIQUE KEY `hr_workplace_name` (`issuer_id`,`name`)',$issuer]);
        $defs['company_hr_contract_types'] = array_merge($base, [$text('name',120),$text('description',4000),'`active` tinyint NOT NULL'], $version, [$owner('hr_contract_type'),'UNIQUE KEY `hr_contract_type_name` (`issuer_id`,`name`)',$issuer]);
        $defs['company_hr_employees'] = array_merge($base, ['`staff_id` int NOT NULL',$text('employee_code',32),'`unit_id` int unsigned NOT NULL','`position_id` int unsigned NOT NULL','`workplace_id` int unsigned NOT NULL','`manager_id` int unsigned NULL','`joined_on` date NOT NULL','`left_on` date NULL',$word('state',16)], $version, [$owner('hr_employee'),'UNIQUE KEY `hr_employee_staff` (`issuer_id`,`staff_id`)','UNIQUE KEY `hr_employee_code` (`issuer_id`,`employee_code`)','KEY `hr_employee_unit` (`issuer_id`,`unit_id`)','KEY `hr_employee_position` (`issuer_id`,`position_id`)','KEY `hr_employee_workplace` (`issuer_id`,`workplace_id`)','KEY `hr_employee_manager` (`issuer_id`,`manager_id`)',$fk('`issuer_id`,`unit_id`','company_hr_units','`issuer_id`,`id`'),$fk('`issuer_id`,`position_id`','company_hr_positions','`issuer_id`,`id`'),$fk('`issuer_id`,`workplace_id`','company_hr_workplaces','`issuer_id`,`id`'),$fk('`issuer_id`,`manager_id`','company_hr_employees','`issuer_id`,`id`')]);
        $defs['company_hr_profiles'] = ['`issuer_id` int unsigned NOT NULL','`employee_id` int unsigned NOT NULL',$json('data_json'),$word('data_hash',64),'`revision` int unsigned NOT NULL','`updated_at` datetime NOT NULL','PRIMARY KEY (`issuer_id`,`employee_id`)',$employee];
        $defs['company_hr_records'] = array_merge($base, ['`employee_id` int unsigned NOT NULL',$word('kind',16),$word('state',16),$json('data_json'),$word('data_hash',64),'`actor_id` int NOT NULL'], $version, [$owner('hr_record'),'KEY `hr_record_employee` (`issuer_id`,`employee_id`,`id`)',$employee]);
        $defs['company_hr_contract_counters'] = ['`issuer_id` int unsigned NOT NULL PRIMARY KEY','`next_number` int unsigned NOT NULL','`revision` int unsigned NOT NULL',$issuer];
        $defs['company_hr_contracts'] = array_merge($base, ['`employee_id` int unsigned NOT NULL','`type_id` int unsigned NOT NULL','`number` int unsigned NOT NULL','`starts_on` date NOT NULL','`ends_on` date NULL',$word('state',16),$json('terms_json'),$word('terms_hash',64),$json('snapshot_json'),$word('snapshot_hash',64),'`acknowledged_at` datetime NULL','`acknowledged_by` int NULL'], $version, [$owner('hr_contract'),'UNIQUE KEY `hr_contract_number` (`issuer_id`,`number`)','KEY `hr_contract_employee` (`issuer_id`,`employee_id`,`id`)','KEY `hr_contract_type` (`issuer_id`,`type_id`)',$employee,$fk('`issuer_id`,`type_id`','company_hr_contract_types','`issuer_id`,`id`')]);
        $defs['company_hr_courses'] = array_merge($base, [$text('title',191),$text('description',4000),$json('questions_json'),$word('questions_hash',64),'`pass_percent` int unsigned NOT NULL','`max_attempts` int unsigned NOT NULL','`active` tinyint NOT NULL'], $version, [$owner('hr_course'),$issuer]);
        $defs['company_hr_assignments'] = array_merge($base, ['`employee_id` int unsigned NOT NULL','`course_id` int unsigned NOT NULL','`actor_id` int NOT NULL','`due_at` datetime NOT NULL',$text('timezone',64),$json('contract_json'),$word('contract_hash',64),$word('state',16),'`attempt_count` int unsigned NOT NULL','`notify_opt_in` tinyint NOT NULL'], $version, [$owner('hr_assignment'),'KEY `hr_assignment_employee` (`issuer_id`,`employee_id`,`id`)','KEY `hr_assignment_course` (`issuer_id`,`course_id`)',$employee,$fk('`issuer_id`,`course_id`','company_hr_courses','`issuer_id`,`id`')]);
        $defs['company_hr_attempts'] = array_merge($base, ['`employee_id` int unsigned NOT NULL','`assignment_id` int unsigned NOT NULL','`attempt_number` int unsigned NOT NULL','`actor_id` int NOT NULL',$json('answers_json'),$word('answers_hash',64),'`correct_count` int unsigned NOT NULL','`question_count` int unsigned NOT NULL','`score` int unsigned NOT NULL','`passed` tinyint NOT NULL','`created_at` datetime NOT NULL'], [$owner('hr_attempt'),'UNIQUE KEY `hr_attempt_number` (`issuer_id`,`assignment_id`,`attempt_number`)','KEY `hr_attempt_employee` (`issuer_id`,`employee_id`)',$employee,$fk('`issuer_id`,`assignment_id`','company_hr_assignments','`issuer_id`,`id`')]);
        $defs['company_hr_access'] = array_merge($base, ['`employee_id` int unsigned NOT NULL','`actor_id` int NOT NULL',$word('purpose',16),'`object_id` int unsigned NOT NULL',$word('secret_hash',64),$json('contract_json'),$word('contract_hash',64),'`active` tinyint NOT NULL','`expires_at` datetime NOT NULL'], $version, [$owner('hr_access'),'UNIQUE KEY `hr_access_secret` (`secret_hash`)','KEY `hr_access_employee` (`issuer_id`,`employee_id`,`id`)',$employee]);
        $defs['company_hr_files'] = array_merge($base, ['`employee_id` int unsigned NULL',$word('purpose',16),'`object_id` int unsigned NOT NULL',$text('name',191),$word('mime',64),'`size` int unsigned NOT NULL',$word('sha256',64),'`content` mediumblob NOT NULL','`hidden` tinyint NOT NULL','`actor_id` int NOT NULL'], $version, [$owner('hr_file'),'KEY `hr_file_object` (`issuer_id`,`purpose`,`object_id`,`id`)','KEY `hr_file_employee` (`issuer_id`,`employee_id`)',$issuer,$employee]);
        $defs['company_hr_notices'] = array_merge($base, ['`employee_id` int unsigned NOT NULL','`assignment_id` int unsigned NOT NULL','`actor_id` int NOT NULL',$word('event',16),'`scheduled_at` datetime NOT NULL',$word('state',16),'`read_at` datetime NULL','`created_at` datetime NOT NULL'], [$owner('hr_notice'),'UNIQUE KEY `hr_notice_event` (`issuer_id`,`assignment_id`,`event`)','KEY `hr_notice_employee` (`issuer_id`,`employee_id`,`id`)','KEY `hr_notice_discovery` (`state`,`id`)',$employee,$fk('`issuer_id`,`assignment_id`','company_hr_assignments','`issuer_id`,`id`')]);
        $defs['company_hr_articles'] = array_merge($base, [$text('title',191),$text('body',10000),'`published` tinyint NOT NULL'], $version, [$owner('hr_article'),$issuer]);
        $defs['company_hr_requests'] = [$word('request_key',64).' PRIMARY KEY','`issuer_id` int unsigned NOT NULL','`actor_id` int NOT NULL',$word('operation',48),$word('input_hash',64),$word('object_kind',24),'`object_id` int unsigned NOT NULL',$json('result_json'),$word('result_hash',64),'`audit_id` int unsigned NOT NULL','`created_at` datetime NOT NULL','KEY `hr_request_owner` (`issuer_id`,`object_kind`,`object_id`)',$issuer];
        $defs['company_hr_cursor'] = ['`id` tinyint unsigned NOT NULL PRIMARY KEY','`last_id` int unsigned NOT NULL','`upper_id` int unsigned NOT NULL','`revision` int unsigned NOT NULL'];
        return $defs;
    }

    public static function ensure($db,string $p): void
    {
        IssuerTransaction::assertIndependent($db);
        foreach (self::definitions($p) as $t=>$parts) { IssuerStorage::query($db,'CREATE TABLE IF NOT EXISTS `'.$p.$t.'` ('.implode(',',$parts).') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin'); }
        if (isset($db->data_cache['table_names'])) { unset($db->data_cache['table_names']); }
        self::assertValid($db,$p);
        // Confirm the DDL before seeding. A successful driver return does not prove
        // that the infrastructure cursor exists, and retry must preserve its progress.
        IssuerTransaction::run($db,$p,['issuers_lock','company_hr_cursor'],static function()use($db,$p):void{
            $read=static function()use($db,$p):array{
                $rows=IssuerStorage::query($db,'SELECT id,last_id,upper_id,revision FROM '.$p.'company_hr_cursor ORDER BY id FOR UPDATE')->result_array();
                $out=[];
                foreach($rows as $row){
                    $r=[];
                    foreach(['id','last_id','upper_id','revision'] as $field){
                        $v=$row[$field]??null;
                        if(!(is_int($v)||(is_string($v)&&preg_match('/^(0|[1-9][0-9]*)$/D',$v)))||(float)$v>2147483647||(int)$v<0)throw new RuntimeException('issuer_hr_write_unconfirmed');
                        $r[$field]=(int)$v;
                    }
                    if($r['id']!==1||$r['last_id']>$r['upper_id']||$r['revision']<1||$out!==[])throw new RuntimeException('issuer_hr_write_unconfirmed');
                    $out[]=$r;
                }
                return $out;
            };
            $wanted=$read();
            if($wanted===[])$wanted=[['id'=>1,'last_id'=>0,'upper_id'=>0,'revision'=>1]];
            IssuerStorage::query($db,'INSERT INTO '.$p.'company_hr_cursor(id,last_id,upper_id,revision) SELECT 1,0,0,1 WHERE NOT EXISTS (SELECT 1 FROM '.$p.'company_hr_cursor WHERE id=1)');
            if($read()!==$wanted)throw new RuntimeException('issuer_hr_write_unconfirmed');
        });
    }

    private static function fail(): never { throw new RuntimeException('issuer_hr_schema_invalid'); }

    public static function assertValid($db,string $p): void
    {
        $rows=static fn($sql,$args=[])=>IssuerStorage::query($db,$sql,$args)->result_array();
        foreach(self::definitions($p) as $t=>$parts){
            $name=$p.$t;$meta=$rows('SELECT ENGINE,TABLE_COLLATION FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?',[$name])[0]??[];
            if(strtoupper($meta['ENGINE']??'')!=='INNODB'||($meta['TABLE_COLLATION']??'')!=='utf8mb4_bin')self::fail();
            $columns=$rows('SELECT COLUMN_NAME,COLUMN_TYPE,IS_NULLABLE,COLUMN_DEFAULT,COLLATION_NAME,EXTRA FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? ORDER BY ORDINAL_POSITION',[$name]);$wanted=[];
            foreach($parts as $d)if(preg_match('/^`([^`]+)` (varchar\([0-9]+\)|int unsigned|int|tinyint unsigned|tinyint|mediumtext|mediumblob|datetime|date)(.*)$/D',$d,$m)){
                $n=count($wanted);$wanted[]=$m[1];$r=$columns[$n]??[];$type=preg_replace('/^(tinyint|int)\([0-9]+\)/','$1',strtolower($r['COLUMN_TYPE']??''));
                $collation=null;if(preg_match('/COLLATE ([a-z0-9_]+)/',$m[3],$cm))$collation=$cm[1];
                if(($r['COLUMN_NAME']??null)!==$m[1]||$type!==$m[2]||($r['IS_NULLABLE']??null)!==(str_contains($m[3],'NOT NULL')?'NO':'YES')||!IssuerStorage::sqlNullDefault($r)||($r['COLLATION_NAME']??null)!==$collation||strtolower($r['EXTRA']??'')!==(str_contains($m[3],'AUTO_INCREMENT')?'auto_increment':''))self::fail();
            }
            if(count($columns)!==count($wanted))self::fail();
            $indexes=[];foreach($rows('SELECT INDEX_NAME,COLUMN_NAME,NON_UNIQUE,SUB_PART FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? ORDER BY INDEX_NAME,SEQ_IN_INDEX',[$name]) as $r){if($r['SUB_PART']!==null)self::fail();$indexes[$r['INDEX_NAME']]['columns'][]=$r['COLUMN_NAME'];$indexes[$r['INDEX_NAME']]['non_unique']=(int)$r['NON_UNIQUE'];}
            $required=[];foreach($parts as $d){
                if(preg_match('/^(UNIQUE )?KEY `([^`]+)` \(([^)]+)\)$/D',$d,$m))$required[$m[2]]=['columns'=>explode(',',str_replace('`','',$m[3])),'non_unique'=>$m[1]!==''?0:1];
                elseif(preg_match('/^PRIMARY KEY \(([^)]+)\)$/D',$d,$m))$required['PRIMARY']=['columns'=>explode(',',str_replace('`','',$m[1])),'non_unique'=>0];
                elseif(preg_match('/^`([^`]+)` .*PRIMARY KEY/',$d,$m))$required['PRIMARY']=['columns'=>[$m[1]],'non_unique'=>0];
            }
            ksort($required);ksort($indexes);if($required!==$indexes)self::fail();
            $expected=[];foreach($parts as $d)if(preg_match('/^FOREIGN KEY \(([^)]+)\) REFERENCES `([^`]+)` \(([^)]+)\) ON DELETE RESTRICT ON UPDATE RESTRICT$/D',$d,$m)){
                $fields=explode(',',str_replace('`','',$m[1]));$target=explode(',',str_replace('`','',$m[3]));$f=[];foreach($fields as $n=>$field)$f[]=$field.':'.$m[2].':'.$target[$n];$expected[]=implode('|',$f);
            }
            $foreign=[];foreach($rows('SELECT CONSTRAINT_NAME,COLUMN_NAME,REFERENCED_TABLE_NAME,REFERENCED_COLUMN_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND REFERENCED_TABLE_SCHEMA=DATABASE() ORDER BY CONSTRAINT_NAME,ORDINAL_POSITION',[$name]) as $r)$foreign[$r['CONSTRAINT_NAME']][]=$r['COLUMN_NAME'].':'.$r['REFERENCED_TABLE_NAME'].':'.$r['REFERENCED_COLUMN_NAME'];
            $actual=array_map(static fn($v)=>implode('|',$v),array_values($foreign));sort($actual);sort($expected);if($actual!==$expected)self::fail();
            $rules=$rows('SELECT DELETE_RULE,UPDATE_RULE FROM information_schema.REFERENTIAL_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME=?',[$name]);if(count($rules)!==count($expected))self::fail();foreach($rules as $r)if(!in_array($r['DELETE_RULE'],['RESTRICT','NO ACTION'],true)||!in_array($r['UPDATE_RULE'],['RESTRICT','NO ACTION'],true))self::fail();
        }
    }
}
