<?php

declare(strict_types=1);
require_once __DIR__.'/issuers/IssuerStorage.php';
final class CompanyStandaloneTasksSchema
{
 public static function definitions(string $p):array
 {
  if(!preg_match('/^[A-Za-z0-9_]+$/D',$p))throw new InvalidArgumentException('Invalid table prefix');$owner='`issuer_id` int unsigned NOT NULL';$text=fn($n,$size)=>'`'.$n.'` varchar('.$size.') CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL';
  return [
   'company_standalone_tasks'=>['`id` int unsigned NOT NULL AUTO_INCREMENT PRIMARY KEY',$owner,'`assignee_id` int NOT NULL',$text('name',191),$text('description',4000),$text('status',20),'`due_date` date NOT NULL','`checklist_json` text CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL','`revision` int unsigned NOT NULL','`created_at` datetime NOT NULL','`updated_at` datetime NOT NULL','UNIQUE KEY `standalone_task_owner` (`issuer_id`,`id`)','FOREIGN KEY (`issuer_id`) REFERENCES `'.$p.'issuers_registry` (`id`)'],
   'company_standalone_comments'=>['`id` int unsigned NOT NULL AUTO_INCREMENT PRIMARY KEY',$owner,'`task_id` int unsigned NOT NULL','`actor_id` int NOT NULL',$text('body',4000),'`created_at` datetime NOT NULL','KEY `standalone_comment_task` (`issuer_id`,`task_id`,`id`)','FOREIGN KEY (`issuer_id`,`task_id`) REFERENCES `'.$p.'company_standalone_tasks` (`issuer_id`,`id`)'],
   'company_standalone_timers'=>['`id` int unsigned NOT NULL AUTO_INCREMENT PRIMARY KEY',$owner,'`task_id` int unsigned NOT NULL','`actor_id` int NOT NULL','`started_at` datetime NOT NULL','`stopped_at` datetime NULL','`seconds` int unsigned NOT NULL','KEY `standalone_timer_actor` (`actor_id`,`stopped_at`)','KEY `standalone_timer_task` (`issuer_id`,`task_id`,`id`)','FOREIGN KEY (`issuer_id`,`task_id`) REFERENCES `'.$p.'company_standalone_tasks` (`issuer_id`,`id`)'],
  ];
 }
    public static function ensure($db,string $p):void
    {
        foreach(self::definitions($p) as $table=>$parts)IssuerStorage::query($db,'CREATE TABLE IF NOT EXISTS `'.$p.$table.'` ('.implode(',',$parts).') ENGINE=InnoDB');
        self::assertValid($db,$p);
    }
    public static function assertValid($db,string $p):void
    {
        $conn=$db->pdo??$db->conn_id??null;if($conn instanceof PDO&&$conn->getAttribute(PDO::ATTR_DRIVER_NAME)==='sqlite')return;
        foreach(self::definitions($p) as $table=>$parts){
            $name=$p.$table;$columns=[];
            foreach(IssuerStorage::query($db,'SELECT COLUMN_NAME,COLUMN_TYPE,IS_NULLABLE,COLUMN_DEFAULT,COLLATION_NAME,EXTRA FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?',[$name])->result_array() as $r)$columns[$r['COLUMN_NAME']]=$r;
            $expected=[];
            foreach($parts as $definition){
                if(!preg_match('/^`([^`]+)` (varchar\([0-9]+\)|int unsigned|int|text|datetime|date)(.*)$/D',$definition,$m))continue;
                [$all,$column,$type,$tail]=$m;$expected[]=$column;$r=$columns[$column]??[];
                $actual=preg_replace('/^(int)\([0-9]+\)/','$1',strtolower($r['COLUMN_TYPE']??''));$nullable=str_contains($tail,'NOT NULL')?'NO':'YES';
                if($actual!==$type||!(($r['IS_NULLABLE']??null)===$nullable||($table==='company_standalone_tasks'&&in_array($column,['due_date','assignee_id'],true)&&($r['IS_NULLABLE']??null)==='YES'))||!IssuerStorage::sqlNullDefault($r)||str_contains($tail,'AUTO_INCREMENT')!==str_contains(strtolower($r['EXTRA']??''),'auto_increment'))throw new RuntimeException('issuer_standalone_task_schema_invalid');
                if(preg_match('/COLLATE ([a-z0-9_]+)/',$tail,$c)&&($r['COLLATION_NAME']??null)!==$c[1])throw new RuntimeException('issuer_standalone_task_schema_invalid');
            }
            if($table==='company_standalone_tasks'){require_once __DIR__.'/NativeCompanyTaskFormSchema.php';NativeCompanyTaskFormSchema::assertAdditions($columns);$expected=array_merge($expected,array_keys(NativeCompanyTaskFormSchema::ADDITIONS));}
            if(array_diff(array_keys($columns),$expected))throw new RuntimeException('issuer_standalone_task_schema_invalid');
            $engine=IssuerStorage::query($db,'SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?',[$name])->row_array();if(strtoupper($engine['ENGINE']??'')!=='INNODB')throw new RuntimeException('issuer_standalone_task_schema_invalid');
            $indexes=[];foreach(IssuerStorage::query($db,'SELECT INDEX_NAME,COLUMN_NAME,NON_UNIQUE,SUB_PART FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? ORDER BY INDEX_NAME,SEQ_IN_INDEX',[$name])->result_array() as $r){if($r['SUB_PART']!==null)throw new RuntimeException('issuer_standalone_task_schema_invalid');$indexes[$r['INDEX_NAME']]['columns'][]=$r['COLUMN_NAME'];$indexes[$r['INDEX_NAME']]['non_unique']=(int)$r['NON_UNIQUE'];}
            foreach($parts as $definition){
                if(preg_match('/^(UNIQUE )?KEY `([^`]+)` \(([^)]+)\)$/D',$definition,$m)){$key=$m[2];$cols=explode(',',str_replace('`','',$m[3]));$unique=$m[1]!==''?0:1;}
                elseif(preg_match('/^`([^`]+)` .*PRIMARY KEY/',$definition,$m)){$key='PRIMARY';$cols=[$m[1]];$unique=0;}else continue;
                if(($indexes[$key]['columns']??[])!==$cols||($indexes[$key]['non_unique']??null)!==$unique)throw new RuntimeException('issuer_standalone_task_schema_invalid');
            }
            $foreign=[];foreach(IssuerStorage::query($db,'SELECT CONSTRAINT_NAME,COLUMN_NAME,REFERENCED_TABLE_NAME,REFERENCED_COLUMN_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND REFERENCED_TABLE_SCHEMA=DATABASE() ORDER BY CONSTRAINT_NAME,ORDINAL_POSITION',[$name])->result_array() as $r)$foreign[$r['CONSTRAINT_NAME']][]=$r['COLUMN_NAME'].':'.$r['REFERENCED_TABLE_NAME'].':'.$r['REFERENCED_COLUMN_NAME'];
            foreach($parts as $definition)if(preg_match('/^FOREIGN KEY \(([^)]+)\) REFERENCES `([^`]+)` \(([^)]+)\)$/D',$definition,$m)){$cols=explode(',',str_replace('`','',$m[1]));$refs=explode(',',str_replace('`','',$m[3]));$want=[];foreach($cols as $i=>$col)$want[]=$col.':'.$m[2].':'.$refs[$i];if(!in_array($want,array_values($foreign),true))throw new RuntimeException('issuer_standalone_task_schema_invalid');}
        }
    }
}
