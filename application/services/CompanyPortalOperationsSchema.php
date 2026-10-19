<?php

declare(strict_types=1);
require_once __DIR__.'/issuers/IssuerStorage.php';
/** Additive private operational files, with a persisted composite parent for each domain. */
final class CompanyPortalOperationsSchema
{
 public const TARGETS=[
  'contracts'=>['permission'=>3,'feature'=>'contracts','parent'=>'company_contracts','field'=>'contract_id','files'=>'company_contract_files'],
  'tickets'=>['permission'=>5,'feature'=>'tickets','parent'=>'company_tickets','field'=>'ticket_id','files'=>'company_ticket_files'],
  'projects'=>['permission'=>6,'feature'=>'projects','parent'=>'company_projects','field'=>'project_id','files'=>'company_project_files'],
 ];
 public static function target(string $kind):array{if(!isset(self::TARGETS[$kind]))throw new DomainException('company_portal_operation_unavailable');return self::TARGETS[$kind];}
 public static function definitions(string $p):array
 {
  if(!preg_match('/^[A-Za-z0-9_]+$/D',$p))throw new InvalidArgumentException('Invalid table prefix');$out=[];
  foreach(self::TARGETS as $kind=>$t){$owner='`issuer_id` int unsigned NOT NULL';$field='`'.$t['field'].'` int unsigned NOT NULL';$fk='FOREIGN KEY (`issuer_id`,`'.$t['field'].'`) REFERENCES `'.$p.$t['parent'].'` (`issuer_id`,`id`)';
   $out['company_portal_'.$kind]=[$owner,$field,'`visible` tinyint NOT NULL','`actor_id` int NOT NULL','`updated_at` datetime NOT NULL','PRIMARY KEY (`issuer_id`,`'.$t['field'].'`)',$fk];
   $out['company_portal_'.$kind.'_files']=[$owner,$field,'`file_key` varchar(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL PRIMARY KEY','`visible` tinyint NOT NULL','`actor_id` int NOT NULL','`updated_at` datetime NOT NULL','KEY `portal_file_parent` (`issuer_id`,`'.$t['field'].'`,`visible`)','FOREIGN KEY (`issuer_id`,`'.$t['field'].'`,`file_key`) REFERENCES `'.$p.$t['files'].'` (`issuer_id`,`'.$t['field'].'`,`file_key`)'];
  }
  $out['company_portal_ticket_replies']=['`issuer_id` int unsigned NOT NULL','`ticket_id` int unsigned NOT NULL','`reply_id` int unsigned NOT NULL PRIMARY KEY','`visible` tinyint NOT NULL','`actor_id` int NOT NULL','`updated_at` datetime NOT NULL','KEY `portal_reply_parent` (`issuer_id`,`ticket_id`,`visible`)','FOREIGN KEY (`issuer_id`,`ticket_id`,`reply_id`) REFERENCES `'.$p.'company_ticket_replies` (`issuer_id`,`ticket_id`,`id`)'];
  $out['company_portal_operation_requests']=['`request_key` varchar(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL PRIMARY KEY','`issuer_id` int unsigned NOT NULL','`actor_id` int NOT NULL','`kind` varchar(12) CHARACTER SET ascii COLLATE ascii_bin NOT NULL','`parent_id` int unsigned NOT NULL','`input_hash` varchar(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL','`created_at` datetime NOT NULL','FOREIGN KEY (`issuer_id`) REFERENCES `'.$p.'issuers_registry` (`id`)'];
  return $out;
 }
 private static function sourceIndexes():array{$out=[];foreach(self::TARGETS as $t)$out[$t['files']]=['portal_file_origin'=>['issuer_id',$t['field'],'file_key']];$out['company_ticket_replies']=['portal_reply_origin'=>['issuer_id','ticket_id','id']];return $out;}
 private static function indexes($db,string $p,bool $create):void
 {
  foreach(self::sourceIndexes() as $table=>$keys)foreach($keys as $name=>$wanted){$rows=IssuerStorage::query($db,'SELECT COLUMN_NAME,NON_UNIQUE,SUB_PART FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND INDEX_NAME=? ORDER BY SEQ_IN_INDEX',[$p.$table,$name])->result_array();
   if(!$rows&&$create){IssuerStorage::query($db,'ALTER TABLE `'.$p.$table.'` ADD UNIQUE KEY `'.$name.'` (`'.implode('`,`',$wanted).'`)');continue;}
   if(array_column($rows,'COLUMN_NAME')!==$wanted||array_filter($rows,static fn($r)=>(int)$r['NON_UNIQUE']!==0||$r['SUB_PART']!==null))throw new RuntimeException('issuer_portal_operations_schema_invalid');
  }
 }
    public static function ensure($db,string $p):void
    {
        self::indexes($db,$p,true);foreach(self::definitions($p) as $table=>$parts)IssuerStorage::query($db,'CREATE TABLE IF NOT EXISTS `'.$p.$table.'` ('.implode(',',$parts).') ENGINE=InnoDB');
        self::assertValid($db,$p);
    }
    public static function assertValid($db,string $p):void
    {
        $conn=$db->pdo??$db->conn_id??null;if($conn instanceof PDO&&$conn->getAttribute(PDO::ATTR_DRIVER_NAME)==='sqlite')return;
        self::indexes($db,$p,false);foreach(self::definitions($p) as $table=>$parts){
            $name=$p.$table;$columns=[];
            foreach(IssuerStorage::query($db,'SELECT COLUMN_NAME,COLUMN_TYPE,IS_NULLABLE,COLUMN_DEFAULT,COLLATION_NAME,EXTRA FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?',[$name])->result_array() as $r)$columns[$r['COLUMN_NAME']]=$r;
            $expected=[];
            foreach($parts as $definition){
                if(!preg_match('/^`([^`]+)` (varchar\([0-9]+\)|int unsigned|int|tinyint|mediumblob|text|datetime|date)(.*)$/D',$definition,$m))continue;
                [$all,$column,$type,$tail]=$m;$expected[]=$column;$r=$columns[$column]??[];
                $actual=preg_replace('/^(tinyint|int)\([0-9]+\)/','$1',strtolower($r['COLUMN_TYPE']??''));$nullable=str_contains($tail,'NOT NULL')?'NO':'YES';
                if($actual!==$type||($r['IS_NULLABLE']??null)!==$nullable||!IssuerStorage::sqlNullDefault($r)||str_contains($tail,'AUTO_INCREMENT')!==str_contains(strtolower($r['EXTRA']??''),'auto_increment'))throw new RuntimeException('issuer_portal_operations_schema_invalid');
                if(preg_match('/COLLATE ([a-z0-9_]+)/',$tail,$c)&&($r['COLLATION_NAME']??null)!==$c[1])throw new RuntimeException('issuer_portal_operations_schema_invalid');
            }
            if(array_diff(array_keys($columns),$expected))throw new RuntimeException('issuer_portal_operations_schema_invalid');
            $engine=IssuerStorage::query($db,'SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?',[$name])->row_array();if(strtoupper($engine['ENGINE']??'')!=='INNODB')throw new RuntimeException('issuer_portal_operations_schema_invalid');
            $indexes=[];foreach(IssuerStorage::query($db,'SELECT INDEX_NAME,COLUMN_NAME,NON_UNIQUE,SUB_PART FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? ORDER BY INDEX_NAME,SEQ_IN_INDEX',[$name])->result_array() as $r){if($r['SUB_PART']!==null)throw new RuntimeException('issuer_portal_operations_schema_invalid');$indexes[$r['INDEX_NAME']]['columns'][]=$r['COLUMN_NAME'];$indexes[$r['INDEX_NAME']]['non_unique']=(int)$r['NON_UNIQUE'];}
            foreach($parts as $definition){
                if(preg_match('/^(UNIQUE )?KEY `([^`]+)` \(([^)]+)\)$/D',$definition,$m)){$key=$m[2];$cols=explode(',',str_replace('`','',$m[3]));$unique=$m[1]!==''?0:1;}
                elseif(preg_match('/^PRIMARY KEY \(([^)]+)\)$/D',$definition,$m)){$key='PRIMARY';$cols=explode(',',str_replace('`','',$m[1]));$unique=0;}
                elseif(preg_match('/^`([^`]+)` .*PRIMARY KEY/',$definition,$m)){$key='PRIMARY';$cols=[$m[1]];$unique=0;}else continue;
                if(($indexes[$key]['columns']??[])!==$cols||($indexes[$key]['non_unique']??null)!==$unique)throw new RuntimeException('issuer_portal_operations_schema_invalid');
            }
            $foreign=[];foreach(IssuerStorage::query($db,'SELECT CONSTRAINT_NAME,COLUMN_NAME,REFERENCED_TABLE_NAME,REFERENCED_COLUMN_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND REFERENCED_TABLE_SCHEMA=DATABASE() ORDER BY CONSTRAINT_NAME,ORDINAL_POSITION',[$name])->result_array() as $r)$foreign[$r['CONSTRAINT_NAME']][]=$r['COLUMN_NAME'].':'.$r['REFERENCED_TABLE_NAME'].':'.$r['REFERENCED_COLUMN_NAME'];
            foreach($parts as $definition)if(preg_match('/^FOREIGN KEY \(([^)]+)\) REFERENCES `([^`]+)` \(([^)]+)\)$/D',$definition,$m)){$cols=explode(',',str_replace('`','',$m[1]));$refs=explode(',',str_replace('`','',$m[3]));$want=[];foreach($cols as $i=>$col)$want[]=$col.':'.$m[2].':'.$refs[$i];if(!in_array($want,array_values($foreign),true))throw new RuntimeException('issuer_portal_operations_schema_invalid');}
        }
    }
}
