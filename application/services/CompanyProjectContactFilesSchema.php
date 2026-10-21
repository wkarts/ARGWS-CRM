<?php

declare(strict_types=1);
require_once __DIR__.'/issuers/IssuerStorage.php';require_once __DIR__.'/issuers/IssuerTransaction.php';
/** Opt-in native project file uploads, contact provenance and immutable private receipts. */
final class CompanyProjectContactFilesSchema
{
    public static function definitions(string $p):array
    {
        if(!preg_match('/^[A-Za-z0-9_]+$/D',$p))throw new InvalidArgumentException('Invalid table prefix');$i='`issuer_id` int unsigned NOT NULL';$project='`project_id` int unsigned NOT NULL';$client='`client_id` int NOT NULL';$contact='`contact_id` int NOT NULL';$file='`file_key` varchar(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL';$pf='FOREIGN KEY (`issuer_id`,`project_id`) REFERENCES `'.$p.'company_projects` (`issuer_id`,`id`)';$cf='FOREIGN KEY (`client_id`,`contact_id`) REFERENCES `'.$p.'contacts` (`userid`,`id`)';
        return [
          'company_project_file_upload_rules'=>[$i,$project,'`upload_files` tinyint NOT NULL','`project_files` tinyint NOT NULL','`revision` int unsigned NOT NULL','`actor_id` int NOT NULL','`updated_at` datetime NOT NULL','PRIMARY KEY (`issuer_id`,`project_id`)',$pf],
          'company_project_file_states'=>[$i,$project,'`revision` int unsigned NOT NULL','PRIMARY KEY (`issuer_id`,`project_id`)',$pf],
          'company_project_contact_files'=>[$file.' PRIMARY KEY',$i,$project,$client,$contact,'`author_name` varchar(383) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL','`created_at` datetime NOT NULL','UNIQUE KEY `contact_project_file` (`issuer_id`,`project_id`,`file_key`)','FOREIGN KEY (`issuer_id`,`project_id`,`file_key`) REFERENCES `'.$p.'company_project_files` (`issuer_id`,`project_id`,`file_key`)',$pf,$cf],
          'company_project_file_rule_requests'=>['`request_key` varchar(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL PRIMARY KEY',$i,$project,'`actor_id` int NOT NULL','`input_hash` varchar(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL','`created_at` datetime NOT NULL',$pf],
          'company_project_file_requests'=>['`request_key` varchar(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL PRIMARY KEY',$i,$project,$client,$contact,$file,'`operation` varchar(12) CHARACTER SET ascii COLLATE ascii_bin NOT NULL','`input_hash` varchar(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL','`result_json` text CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL','`created_at` datetime NOT NULL','FOREIGN KEY (`issuer_id`,`project_id`,`file_key`) REFERENCES `'.$p.'company_project_contact_files` (`issuer_id`,`project_id`,`file_key`)',$pf,$cf],
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
                if($actual!==$type||($r['IS_NULLABLE']??null)!==$nullable||!IssuerStorage::sqlNullDefault($r)||str_contains($tail,'AUTO_INCREMENT')!==str_contains(strtolower($r['EXTRA']??''),'auto_increment'))throw new RuntimeException('issuer_project_contact_files_schema_invalid');
                if(preg_match('/COLLATE ([a-z0-9_]+)/',$tail,$c)&&($r['COLLATION_NAME']??null)!==$c[1])throw new RuntimeException('issuer_project_contact_files_schema_invalid');
            }
            if(array_diff(array_keys($columns),$expected))throw new RuntimeException('issuer_project_contact_files_schema_invalid');
            $engine=IssuerStorage::query($db,'SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?',[$name])->row_array();if(strtoupper($engine['ENGINE']??'')!=='INNODB')throw new RuntimeException('issuer_project_contact_files_schema_invalid');
            $indexes=[];foreach(IssuerStorage::query($db,'SELECT INDEX_NAME,COLUMN_NAME,NON_UNIQUE,SUB_PART FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? ORDER BY INDEX_NAME,SEQ_IN_INDEX',[$name])->result_array() as $r){if($r['SUB_PART']!==null)throw new RuntimeException('issuer_project_contact_files_schema_invalid');$indexes[$r['INDEX_NAME']]['columns'][]=$r['COLUMN_NAME'];$indexes[$r['INDEX_NAME']]['non_unique']=(int)$r['NON_UNIQUE'];}
            foreach($parts as $definition){
                if(preg_match('/^(UNIQUE )?KEY `([^`]+)` \(([^)]+)\)$/D',$definition,$m)){$key=$m[2];$cols=explode(',',str_replace('`','',$m[3]));$unique=$m[1]!==''?0:1;}
                elseif(preg_match('/^PRIMARY KEY \(([^)]+)\)$/D',$definition,$m)){$key='PRIMARY';$cols=explode(',',str_replace('`','',$m[1]));$unique=0;}
                elseif(preg_match('/^`([^`]+)` .*PRIMARY KEY/',$definition,$m)){$key='PRIMARY';$cols=[$m[1]];$unique=0;}else continue;
                if(($indexes[$key]['columns']??[])!==$cols||($indexes[$key]['non_unique']??null)!==$unique)throw new RuntimeException('issuer_project_contact_files_schema_invalid');
            }
            $foreign=[];foreach(IssuerStorage::query($db,'SELECT CONSTRAINT_NAME,COLUMN_NAME,REFERENCED_TABLE_NAME,REFERENCED_COLUMN_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND REFERENCED_TABLE_SCHEMA=DATABASE() ORDER BY CONSTRAINT_NAME,ORDINAL_POSITION',[$name])->result_array() as $r)$foreign[$r['CONSTRAINT_NAME']][]=$r['COLUMN_NAME'].':'.$r['REFERENCED_TABLE_NAME'].':'.$r['REFERENCED_COLUMN_NAME'];
            foreach($parts as $definition)if(preg_match('/^FOREIGN KEY \(([^)]+)\) REFERENCES `([^`]+)` \(([^)]+)\)$/D',$definition,$m)){$cols=explode(',',str_replace('`','',$m[1]));$refs=explode(',',str_replace('`','',$m[3]));$want=[];foreach($cols as $i=>$col)$want[]=$col.':'.$m[2].':'.$refs[$i];if(!in_array($want,array_values($foreign),true))throw new RuntimeException('issuer_project_contact_files_schema_invalid');}
        }
    }
}
