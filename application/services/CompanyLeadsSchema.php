<?php

declare(strict_types=1);
require_once __DIR__.'/issuers/IssuerTransaction.php';

/** Additive private lead storage. Never adopts or changes native legacy leads. */
final class CompanyLeadsSchema
{
    public static function definitions(string $p):array
    {
        if(!preg_match('/^[A-Za-z0-9_]+$/D',$p))throw new InvalidArgumentException('Invalid table prefix');
        $id='`id` int unsigned NOT NULL AUTO_INCREMENT PRIMARY KEY';
        $owner='`issuer_id` int unsigned NOT NULL';
        $text=static fn(string $name,int $length,bool $nullable=false):string=>'`'.$name.'` varchar('.$length.') CHARACTER SET utf8mb4 COLLATE utf8mb4_bin '.($nullable?'NULL':'NOT NULL');
        $ascii=static fn(string $name,int $length,bool $nullable=false):string=>'`'.$name.'` varchar('.$length.') CHARACTER SET ascii COLLATE ascii_bin '.($nullable?'NULL':'NOT NULL');
        $issuerFk='FOREIGN KEY (`issuer_id`) REFERENCES `'.$p.'issuers_registry` (`id`)';
        $leadFk='FOREIGN KEY (`issuer_id`,`lead_id`) REFERENCES `'.$p.'company_leads` (`issuer_id`,`id`)';
        return [
            'company_lead_stages'=>[$id,$owner,$text('name',100),$ascii('category',8),'`position` int unsigned NOT NULL','`active` tinyint NOT NULL','`revision` int unsigned NOT NULL','`created_at` datetime NOT NULL','`updated_at` datetime NOT NULL','UNIQUE KEY `lead_stage_owner` (`issuer_id`,`id`)','UNIQUE KEY `lead_stage_name` (`issuer_id`,`name`)',$issuerFk],
            'company_lead_sources'=>[$id,$owner,$text('name',100),'`active` tinyint NOT NULL','`revision` int unsigned NOT NULL','`created_at` datetime NOT NULL','`updated_at` datetime NOT NULL','UNIQUE KEY `lead_source_owner` (`issuer_id`,`id`)','UNIQUE KEY `lead_source_name` (`issuer_id`,`name`)',$issuerFk],
            'company_leads'=>[$id,$owner,'`created_by` int NOT NULL','`assignee_id` int NOT NULL',$text('name',191),$text('company',191),$text('email',100),$text('phone',64),$ascii('vat',32),$text('description',4000),'`stage_id` int unsigned NOT NULL','`source_id` int unsigned NOT NULL','`notify_assignee` tinyint NOT NULL','`reminder_at` datetime NULL',$text('reminder_timezone',64),'`archived` tinyint NOT NULL','`converted_client_id` int NULL','`converted_contact_id` int NULL','`converted_at` datetime NULL','`revision` int unsigned NOT NULL','`created_at` datetime NOT NULL','`updated_at` datetime NOT NULL','UNIQUE KEY `lead_owner` (`issuer_id`,`id`)','KEY `lead_board` (`issuer_id`,`archived`,`stage_id`,`id`)','KEY `lead_source` (`issuer_id`,`source_id`)',$issuerFk,'FOREIGN KEY (`issuer_id`,`stage_id`) REFERENCES `'.$p.'company_lead_stages` (`issuer_id`,`id`)','FOREIGN KEY (`issuer_id`,`source_id`) REFERENCES `'.$p.'company_lead_sources` (`issuer_id`,`id`)','FOREIGN KEY (`converted_client_id`) REFERENCES `'.$p.'clients` (`userid`)','FOREIGN KEY (`converted_contact_id`) REFERENCES `'.$p.'contacts` (`id`)'],
            'company_lead_notes'=>[$id,$owner,'`lead_id` int unsigned NOT NULL','`actor_id` int NOT NULL',$text('body',4000),'`created_at` datetime NOT NULL','KEY `lead_note_parent` (`issuer_id`,`lead_id`,`id`)',$leadFk],
            'company_lead_files'=>[$ascii('file_key',64).' PRIMARY KEY',$owner,'`lead_id` int unsigned NOT NULL','`actor_id` int NOT NULL',$text('filename',191),$ascii('mime',40),$ascii('content_hash',64),'`byte_size` int unsigned NOT NULL','`content` mediumblob NOT NULL','`active` tinyint NOT NULL','`created_at` datetime NOT NULL','KEY `lead_file_parent` (`issuer_id`,`lead_id`,`active`)',$leadFk],
            'company_lead_imports'=>[$ascii('batch_id',32).' PRIMARY KEY',$owner,'`actor_id` int NOT NULL',$ascii('content_hash',64),'`payload_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL',$ascii('preview_hash',64),$ascii('state',16),'`row_count` int unsigned NOT NULL','`result_json` text CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NULL','`created_at` datetime NOT NULL','`expires_at` datetime NOT NULL','`committed_at` datetime NULL','KEY `lead_import_actor` (`issuer_id`,`actor_id`,`created_at`)',$issuerFk],
            'company_lead_requests'=>[$ascii('request_key',64).' PRIMARY KEY',$owner,'`actor_id` int NOT NULL',$ascii('operation',48),$ascii('input_hash',64),$ascii('object_kind',16),$ascii('object_id',64),'`result_json` text CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL','`created_at` datetime NOT NULL','KEY `lead_request_owner` (`issuer_id`,`actor_id`,`created_at`)',$issuerFk],
            'company_lead_notices'=>[$id,$owner,'`lead_id` int unsigned NOT NULL','`actor_id` int NOT NULL','`recipient_id` int NOT NULL',$ascii('kind',16),$ascii('required_capability',16),'`lead_revision` int unsigned NOT NULL','`due_at` datetime NOT NULL',$ascii('state',16),$ascii('origin_token_id',32,true),$ascii('origin_scope',64,true),'`created_at` datetime NOT NULL','`processed_at` datetime NULL','`acknowledged_at` datetime NULL','KEY `lead_notice_worker` (`state`,`due_at`,`id`)','KEY `lead_notice_inbox` (`issuer_id`,`recipient_id`,`state`,`id`)','KEY `lead_notice_parent` (`issuer_id`,`lead_id`,`id`)',$leadFk,'FOREIGN KEY (`origin_token_id`) REFERENCES `'.$p.'company_api_tokens` (`token_id`)'],
            'company_lead_notice_cursor'=>['`id` int unsigned NOT NULL PRIMARY KEY','`last_id` int unsigned NOT NULL','`updated_at` datetime NOT NULL'],
        ];
    }
    public static function ensure($db,string $p):void
    {
        IssuerTransaction::assertIndependent($db);
        foreach(self::definitions($p) as $table=>$parts)IssuerStorage::query($db,'CREATE TABLE IF NOT EXISTS `'.$p.$table.'` ('.implode(',',$parts).') ENGINE=InnoDB');
        IssuerStorage::query($db,'INSERT IGNORE INTO `'.$p.'company_lead_notice_cursor` (id,last_id,updated_at) VALUES(1,0,?)',[gmdate('Y-m-d H:i:s')]);
        self::assertValid($db,$p);
    }
    public static function assertValid($db,string $p):void
    {
        $conn=$db->pdo??$db->conn_id??null;
        if($conn instanceof PDO&&$conn->getAttribute(PDO::ATTR_DRIVER_NAME)==='sqlite')throw new RuntimeException('issuer_lead_mysql_required');
        foreach(self::definitions($p) as $table=>$parts){
            $name=$p.$table;$columns=[];
            foreach(IssuerStorage::query($db,'SELECT COLUMN_NAME,COLUMN_TYPE,IS_NULLABLE,COLUMN_DEFAULT,COLLATION_NAME,EXTRA FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?',[$name])->result_array() as $r)$columns[$r['COLUMN_NAME']]=$r;
            $expected=[];
            foreach($parts as $definition){
                if(!preg_match('/^`([^`]+)` (varchar\([0-9]+\)|int unsigned|int|tinyint|mediumblob|longtext|text|datetime)(.*)$/D',$definition,$m))continue;
                [, $column,$type,$tail]=$m;$expected[]=$column;$r=$columns[$column]??[];
                $actual=preg_replace('/^(tinyint|int)\([0-9]+\)/','$1',strtolower($r['COLUMN_TYPE']??''));
                if($actual!==$type||($r['IS_NULLABLE']??null)!==(str_contains($tail,'NOT NULL')?'NO':'YES')||!IssuerStorage::sqlNullDefault($r)||str_contains($tail,'AUTO_INCREMENT')!==str_contains(strtolower($r['EXTRA']??''),'auto_increment'))throw new RuntimeException('issuer_lead_schema_invalid');
                if(preg_match('/COLLATE ([a-z0-9_]+)/',$tail,$c)&&($r['COLLATION_NAME']??null)!==$c[1])throw new RuntimeException('issuer_lead_schema_invalid');
            }
            if(array_diff(array_keys($columns),$expected))throw new RuntimeException('issuer_lead_schema_invalid');
            $engine=IssuerStorage::query($db,'SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?',[$name])->row_array();
            if(strtoupper($engine['ENGINE']??'')!=='INNODB')throw new RuntimeException('issuer_lead_schema_invalid');
            $indexes=[];
            foreach(IssuerStorage::query($db,'SELECT INDEX_NAME,COLUMN_NAME,NON_UNIQUE,SUB_PART FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? ORDER BY INDEX_NAME,SEQ_IN_INDEX',[$name])->result_array() as $r){
                if($r['SUB_PART']!==null)throw new RuntimeException('issuer_lead_schema_invalid');
                $indexes[$r['INDEX_NAME']]['columns'][]=$r['COLUMN_NAME'];$indexes[$r['INDEX_NAME']]['non_unique']=(int)$r['NON_UNIQUE'];
            }
            foreach($parts as $definition){
                if(preg_match('/^(UNIQUE )?KEY `([^`]+)` \(([^)]+)\)$/D',$definition,$m)){$key=$m[2];$cols=explode(',',str_replace('`','',$m[3]));$unique=$m[1]!==''?0:1;}
                elseif(preg_match('/^`([^`]+)` .*PRIMARY KEY/',$definition,$m)){$key='PRIMARY';$cols=[$m[1]];$unique=0;}else continue;
                if(($indexes[$key]['columns']??[])!==$cols||($indexes[$key]['non_unique']??null)!==$unique)throw new RuntimeException('issuer_lead_schema_invalid');
            }
            $foreign=[];
            foreach(IssuerStorage::query($db,'SELECT k.CONSTRAINT_NAME,k.COLUMN_NAME,k.REFERENCED_TABLE_NAME,k.REFERENCED_COLUMN_NAME,r.UPDATE_RULE,r.DELETE_RULE FROM information_schema.KEY_COLUMN_USAGE k JOIN information_schema.REFERENTIAL_CONSTRAINTS r ON r.CONSTRAINT_SCHEMA=k.CONSTRAINT_SCHEMA AND r.CONSTRAINT_NAME=k.CONSTRAINT_NAME AND r.TABLE_NAME=k.TABLE_NAME WHERE k.TABLE_SCHEMA=DATABASE() AND k.TABLE_NAME=? AND k.REFERENCED_TABLE_SCHEMA=DATABASE() ORDER BY k.CONSTRAINT_NAME,k.ORDINAL_POSITION',[$name])->result_array() as $r){
                if(!in_array($r['UPDATE_RULE'],['RESTRICT','NO ACTION'],true)||!in_array($r['DELETE_RULE'],['RESTRICT','NO ACTION'],true))throw new RuntimeException('issuer_lead_schema_invalid');
                $foreign[$r['CONSTRAINT_NAME']][]=$r['COLUMN_NAME'].':'.$r['REFERENCED_TABLE_NAME'].':'.$r['REFERENCED_COLUMN_NAME'];
            }
            foreach($parts as $definition)if(preg_match('/^FOREIGN KEY \(([^)]+)\) REFERENCES `([^`]+)` \(([^)]+)\)$/D',$definition,$m)){
                $cols=explode(',',str_replace('`','',$m[1]));$refs=explode(',',str_replace('`','',$m[3]));$want=[];
                foreach($cols as $i=>$col)$want[]=$col.':'.$m[2].':'.$refs[$i];
                if(!in_array($want,array_values($foreign),true))throw new RuntimeException('issuer_lead_schema_invalid');
            }
        }
        $cursor=IssuerStorage::query($db,'SELECT id,last_id FROM `'.$p.'company_lead_notice_cursor`')->result_array();
        if(count($cursor)!==1||(int)$cursor[0]['id']!==1||(int)$cursor[0]['last_id']<0)throw new RuntimeException('issuer_lead_schema_invalid');
    }
}
