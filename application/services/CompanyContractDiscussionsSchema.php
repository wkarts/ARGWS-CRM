<?php

declare(strict_types=1);
require_once __DIR__.'/issuers/IssuerStorage.php';
/** Append-only contract discussion and durable receipts, separate from contract text/snapshot. */
final class CompanyContractDiscussionsSchema
{
 public static function definitions(string $p):array
 {
  if(!preg_match('/^[A-Za-z0-9_]+$/D',$p))throw new InvalidArgumentException('Invalid table prefix');
  return [
   'company_contract_discussions'=>['`id` int unsigned NOT NULL AUTO_INCREMENT PRIMARY KEY','`issuer_id` int unsigned NOT NULL','`contract_id` int unsigned NOT NULL','`client_id` int NOT NULL','`author_type` varchar(7) CHARACTER SET ascii COLLATE ascii_bin NOT NULL','`staff_id` int NULL','`contact_id` int NULL','`author_name` varchar(383) NOT NULL','`body` text CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL','`visible` tinyint NOT NULL','`created_at` datetime NOT NULL','UNIQUE KEY `discussion_origin` (`issuer_id`,`contract_id`,`id`)','KEY `discussion_public` (`issuer_id`,`contract_id`,`visible`,`id`)','FOREIGN KEY (`issuer_id`,`contract_id`,`client_id`) REFERENCES `'.$p.'company_contracts` (`issuer_id`,`id`,`client_id`)','FOREIGN KEY (`staff_id`) REFERENCES `'.$p.'staff` (`staffid`)','FOREIGN KEY (`client_id`,`contact_id`) REFERENCES `'.$p.'contacts` (`userid`,`id`)'],
   'company_contract_comment_requests'=>['`request_key` varchar(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL PRIMARY KEY','`issuer_id` int unsigned NOT NULL','`contract_id` int unsigned NOT NULL','`author_type` varchar(7) CHARACTER SET ascii COLLATE ascii_bin NOT NULL','`actor_id` int NOT NULL','`operation` varchar(12) CHARACTER SET ascii COLLATE ascii_bin NOT NULL','`input_hash` varchar(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL','`result_id` int unsigned NOT NULL','`created_at` datetime NOT NULL','FOREIGN KEY (`issuer_id`,`contract_id`,`result_id`) REFERENCES `'.$p.'company_contract_discussions` (`issuer_id`,`contract_id`,`id`)'],
  ];
 }
 private static function sourceIndexes():array{return ['company_contracts'=>['portal_contract_client'=>['issuer_id','id','client_id']],'contacts'=>['portal_support_contact'=>['userid','id']]];}
 private static function indexes($db,string $p,bool $create):void
 {
  foreach(self::sourceIndexes() as $table=>$keys)foreach($keys as $name=>$wanted){$rows=IssuerStorage::query($db,'SELECT COLUMN_NAME,NON_UNIQUE,SUB_PART FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND INDEX_NAME=? ORDER BY SEQ_IN_INDEX',[$p.$table,$name])->result_array();
   if(!$rows&&$create){IssuerStorage::query($db,'ALTER TABLE `'.$p.$table.'` ADD UNIQUE KEY `'.$name.'` (`'.implode('`,`',$wanted).'`)');continue;}
   if(array_column($rows,'COLUMN_NAME')!==$wanted||array_filter($rows,static fn($r)=>(int)$r['NON_UNIQUE']!==0||$r['SUB_PART']!==null))throw new RuntimeException('issuer_contract_discussions_schema_invalid');
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
                if($actual!==$type||($r['IS_NULLABLE']??null)!==$nullable||!IssuerStorage::sqlNullDefault($r)||str_contains($tail,'AUTO_INCREMENT')!==str_contains(strtolower($r['EXTRA']??''),'auto_increment'))throw new RuntimeException('issuer_contract_discussions_schema_invalid');
                if(preg_match('/COLLATE ([a-z0-9_]+)/',$tail,$c)&&($r['COLLATION_NAME']??null)!==$c[1])throw new RuntimeException('issuer_contract_discussions_schema_invalid');
            }
            if(array_diff(array_keys($columns),$expected))throw new RuntimeException('issuer_contract_discussions_schema_invalid');
            $engine=IssuerStorage::query($db,'SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?',[$name])->row_array();if(strtoupper($engine['ENGINE']??'')!=='INNODB')throw new RuntimeException('issuer_contract_discussions_schema_invalid');
            $indexes=[];foreach(IssuerStorage::query($db,'SELECT INDEX_NAME,COLUMN_NAME,NON_UNIQUE,SUB_PART FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? ORDER BY INDEX_NAME,SEQ_IN_INDEX',[$name])->result_array() as $r){if($r['SUB_PART']!==null)throw new RuntimeException('issuer_contract_discussions_schema_invalid');$indexes[$r['INDEX_NAME']]['columns'][]=$r['COLUMN_NAME'];$indexes[$r['INDEX_NAME']]['non_unique']=(int)$r['NON_UNIQUE'];}
            foreach($parts as $definition){
                if(preg_match('/^(UNIQUE )?KEY `([^`]+)` \(([^)]+)\)$/D',$definition,$m)){$key=$m[2];$cols=explode(',',str_replace('`','',$m[3]));$unique=$m[1]!==''?0:1;}
                elseif(preg_match('/^PRIMARY KEY \(([^)]+)\)$/D',$definition,$m)){$key='PRIMARY';$cols=explode(',',str_replace('`','',$m[1]));$unique=0;}
                elseif(preg_match('/^`([^`]+)` .*PRIMARY KEY/',$definition,$m)){$key='PRIMARY';$cols=[$m[1]];$unique=0;}else continue;
                if(($indexes[$key]['columns']??[])!==$cols||($indexes[$key]['non_unique']??null)!==$unique)throw new RuntimeException('issuer_contract_discussions_schema_invalid');
            }
            $foreign=[];foreach(IssuerStorage::query($db,'SELECT CONSTRAINT_NAME,COLUMN_NAME,REFERENCED_TABLE_NAME,REFERENCED_COLUMN_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND REFERENCED_TABLE_SCHEMA=DATABASE() ORDER BY CONSTRAINT_NAME,ORDINAL_POSITION',[$name])->result_array() as $r)$foreign[$r['CONSTRAINT_NAME']][]=$r['COLUMN_NAME'].':'.$r['REFERENCED_TABLE_NAME'].':'.$r['REFERENCED_COLUMN_NAME'];
            foreach($parts as $definition)if(preg_match('/^FOREIGN KEY \(([^)]+)\) REFERENCES `([^`]+)` \(([^)]+)\)$/D',$definition,$m)){$cols=explode(',',str_replace('`','',$m[1]));$refs=explode(',',str_replace('`','',$m[3]));$want=[];foreach($cols as $i=>$col)$want[]=$col.':'.$m[2].':'.$refs[$i];if(!in_array($want,array_values($foreign),true))throw new RuntimeException('issuer_contract_discussions_schema_invalid');}
        }
    }
}
