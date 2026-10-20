<?php

declare(strict_types=1);
require_once __DIR__.'/issuers/IssuerStorage.php';require_once __DIR__.'/issuers/IssuerTransaction.php';
/** Additive native own comment versions/replies and private attachment provenance. */
final class CompanyProjectDiscussionExtrasSchema
{
    public static function definitions(string $p):array
    {
        if(!preg_match('/^[A-Za-z0-9_]+$/D',$p))throw new InvalidArgumentException('Invalid table prefix');$i='`issuer_id` int unsigned NOT NULL';$project='`project_id` int unsigned NOT NULL';$thread='`discussion_id` int unsigned NOT NULL';$post='`post_id` int unsigned NOT NULL';$client='`client_id` int NOT NULL';$key='varchar(64) CHARACTER SET ascii COLLATE ascii_bin';$name='varchar(383) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin';$hash=static fn($field,$nullable=false)=>'`'.$field.'` '.$key.($nullable?' NULL':' NOT NULL');$pf='FOREIGN KEY (`issuer_id`,`project_id`,`discussion_id`,`post_id`) REFERENCES `'.$p.'company_project_discussion_posts` (`issuer_id`,`project_id`,`discussion_id`,`id`)';$parent=[$i,$project,$thread,$post];$rf=static fn($field)=>'FOREIGN KEY (`'.$field.'`) REFERENCES `'.$p.'company_project_discussion_requests` (`request_key`)';
        return [
          'company_discussion_post_heads'=>array_merge($parent,['`revision` int unsigned NOT NULL',$hash('origin_hash'),$hash('current_hash'),$hash('version_hash'),$hash('origin_request_key'),'`updated_at` datetime NOT NULL','PRIMARY KEY (`issuer_id`,`project_id`,`discussion_id`,`post_id`)',$pf,$rf('origin_request_key')]),
          'company_discussion_post_versions'=>array_merge($parent,['`revision` int unsigned NOT NULL','`body` text CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL',$hash('body_hash'),$hash('source_hash'),$hash('previous_hash',true),$hash('version_hash'),'`editor_type` varchar(8) CHARACTER SET ascii COLLATE ascii_bin NOT NULL','`editor_id` int NOT NULL','`editor_name` '.$name.' NOT NULL',$hash('editor_name_hash'),'`operation` varchar(8) CHARACTER SET ascii COLLATE ascii_bin NOT NULL',$hash('request_key'),'`created_at` datetime NOT NULL','PRIMARY KEY (`issuer_id`,`project_id`,`discussion_id`,`post_id`,`revision`)','UNIQUE KEY `discussion_version_request` (`request_key`)',$pf,$rf('request_key')]),
          'company_discussion_post_links'=>array_merge($parent,['`parent_id` int unsigned NOT NULL','`project_revision` int unsigned NOT NULL','`collection_revision` int unsigned NOT NULL','`thread_revision` int unsigned NOT NULL',$hash('request_key'),'`created_at` datetime NOT NULL','PRIMARY KEY (`issuer_id`,`project_id`,`discussion_id`,`post_id`)','KEY `discussion_reply_parent` (`issuer_id`,`project_id`,`discussion_id`,`parent_id`)',$pf,'FOREIGN KEY (`issuer_id`,`project_id`,`discussion_id`,`parent_id`) REFERENCES `'.$p.'company_project_discussion_posts` (`issuer_id`,`project_id`,`discussion_id`,`id`)',$rf('request_key')]),
          'company_discussion_files'=>array_merge([$hash('file_key').' PRIMARY KEY'],$parent,[$client,'`author_type` varchar(8) CHARACTER SET ascii COLLATE ascii_bin NOT NULL','`staff_id` int NULL','`contact_id` int NULL','`author_name` '.$name.' NOT NULL','`filename` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL','`mime` varchar(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL','`byte_size` int unsigned NOT NULL',$hash('content_hash'),'`content` mediumblob NOT NULL','`active` tinyint NOT NULL',$hash('origin_request_key'),'`created_at` datetime NOT NULL','UNIQUE KEY `discussion_file_origin` (`issuer_id`,`project_id`,`discussion_id`,`post_id`,`file_key`)',$pf,$rf('origin_request_key'),'FOREIGN KEY (`client_id`,`contact_id`) REFERENCES `'.$p.'contacts` (`userid`,`id`)']),
          'company_discussion_file_shares'=>array_merge([$hash('file_key').' PRIMARY KEY'],$parent,['`audience` varchar(8) CHARACTER SET ascii COLLATE ascii_bin NOT NULL',$hash('published_hash',true),$hash('post_version_hash',true),'`actor_id` int NOT NULL','`updated_at` datetime NOT NULL','FOREIGN KEY (`issuer_id`,`project_id`,`discussion_id`,`post_id`,`file_key`) REFERENCES `'.$p.'company_discussion_files` (`issuer_id`,`project_id`,`discussion_id`,`post_id`,`file_key`)',$pf]),
          'company_discussion_receipt_seals'=>array_merge([$hash('request_key').' PRIMARY KEY'],$parent,[$client,'`author_type` varchar(8) CHARACTER SET ascii COLLATE ascii_bin NOT NULL','`actor_id` int NOT NULL',$hash('input_hash'),$hash('result_hash'),'`created_at` datetime NOT NULL',$pf,$rf('request_key')]),
        ];
    }
    public static function ensure($db,string $p):void
    {
        $definitions=self::definitions($p);IssuerTransaction::assertIndependent($db);foreach($definitions as $table=>$parts)IssuerStorage::query($db,'CREATE TABLE IF NOT EXISTS `'.$p.$table.'` ('.implode(',',$parts).') ENGINE=InnoDB');
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
                if(!preg_match('/^`([^`]+)` (varchar\([0-9]+\)|int unsigned|int|tinyint|mediumblob|text|datetime|date)(.*)$/D',$definition,$m))continue;
                [$all,$column,$type,$tail]=$m;$expected[]=$column;$r=$columns[$column]??[];
                $actual=preg_replace('/^(tinyint|int)\([0-9]+\)/','$1',strtolower($r['COLUMN_TYPE']??''));$nullable=str_contains($tail,'NOT NULL')?'NO':'YES';
                if($actual!==$type||($r['IS_NULLABLE']??null)!==$nullable||!IssuerStorage::sqlNullDefault($r)||str_contains($tail,'AUTO_INCREMENT')!==str_contains(strtolower($r['EXTRA']??''),'auto_increment'))throw new RuntimeException('issuer_project_discussion_extras_schema_invalid');
                if(preg_match('/COLLATE ([a-z0-9_]+)/',$tail,$c)&&($r['COLLATION_NAME']??null)!==$c[1])throw new RuntimeException('issuer_project_discussion_extras_schema_invalid');
            }
            if(array_diff(array_keys($columns),$expected))throw new RuntimeException('issuer_project_discussion_extras_schema_invalid');
            $engine=IssuerStorage::query($db,'SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?',[$name])->row_array();if(strtoupper($engine['ENGINE']??'')!=='INNODB')throw new RuntimeException('issuer_project_discussion_extras_schema_invalid');
            $indexes=[];foreach(IssuerStorage::query($db,'SELECT INDEX_NAME,COLUMN_NAME,NON_UNIQUE,SUB_PART FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? ORDER BY INDEX_NAME,SEQ_IN_INDEX',[$name])->result_array() as $r){if($r['SUB_PART']!==null)throw new RuntimeException('issuer_project_discussion_extras_schema_invalid');$indexes[$r['INDEX_NAME']]['columns'][]=$r['COLUMN_NAME'];$indexes[$r['INDEX_NAME']]['non_unique']=(int)$r['NON_UNIQUE'];}
            foreach($parts as $definition){
                if(preg_match('/^(UNIQUE )?KEY `([^`]+)` \(([^)]+)\)$/D',$definition,$m)){$key=$m[2];$cols=explode(',',str_replace('`','',$m[3]));$unique=$m[1]!==''?0:1;}
                elseif(preg_match('/^PRIMARY KEY \(([^)]+)\)$/D',$definition,$m)){$key='PRIMARY';$cols=explode(',',str_replace('`','',$m[1]));$unique=0;}
                elseif(preg_match('/^`([^`]+)` .*PRIMARY KEY/',$definition,$m)){$key='PRIMARY';$cols=[$m[1]];$unique=0;}else continue;
                if(($indexes[$key]['columns']??[])!==$cols||($indexes[$key]['non_unique']??null)!==$unique)throw new RuntimeException('issuer_project_discussion_extras_schema_invalid');
            }
            $foreign=[];foreach(IssuerStorage::query($db,'SELECT CONSTRAINT_NAME,COLUMN_NAME,REFERENCED_TABLE_NAME,REFERENCED_COLUMN_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND REFERENCED_TABLE_SCHEMA=DATABASE() ORDER BY CONSTRAINT_NAME,ORDINAL_POSITION',[$name])->result_array() as $r)$foreign[$r['CONSTRAINT_NAME']][]=$r['COLUMN_NAME'].':'.$r['REFERENCED_TABLE_NAME'].':'.$r['REFERENCED_COLUMN_NAME'];
            foreach($parts as $definition)if(preg_match('/^FOREIGN KEY \(([^)]+)\) REFERENCES `([^`]+)` \(([^)]+)\)$/D',$definition,$m)){$cols=explode(',',str_replace('`','',$m[1]));$refs=explode(',',str_replace('`','',$m[3]));$want=[];foreach($cols as $i=>$col)$want[]=$col.':'.$m[2].':'.$refs[$i];if(!in_array($want,array_values($foreign),true))throw new RuntimeException('issuer_project_discussion_extras_schema_invalid');}
        }
    }
}
