<?php

declare(strict_types=1);
require_once __DIR__.'/issuers/IssuerStorage.php';
require_once __DIR__.'/issuers/IssuerTransaction.php';

/** Additive, issuer-owned visual state for native project milestones. */
final class CompanyMilestoneVisualSchema
{
    public static function definitions(string $p):array
    {
        if(!preg_match('/^[A-Za-z0-9_]+$/D',$p))throw new InvalidArgumentException('Invalid table prefix');
        return ['company_milestone_visuals'=>[
            '`issuer_id` int unsigned NOT NULL',
            '`project_id` int unsigned NOT NULL',
            '`milestone_id` int unsigned NOT NULL',
            '`color` varchar(7) CHARACTER SET ascii COLLATE ascii_bin NOT NULL',
            '`revision` int unsigned NOT NULL',
            '`actor_id` int NOT NULL',
            '`updated_at` datetime NOT NULL',
            'PRIMARY KEY (`issuer_id`,`project_id`,`milestone_id`)',
            'FOREIGN KEY (`issuer_id`,`project_id`,`milestone_id`) REFERENCES `'.$p.'company_project_milestones` (`issuer_id`,`project_id`,`id`)',
        ]];
    }

    public static function ensure($db,string $p):void
    {
        $definitions=self::definitions($p);IssuerTransaction::assertIndependent($db);
        foreach($definitions as $table=>$parts)IssuerStorage::query($db,'CREATE TABLE IF NOT EXISTS `'.$p.$table.'` ('.implode(',',$parts).') ENGINE=InnoDB');
        self::assertValid($db,$p);
    }

    public static function assertValid($db,string $p):void
    {
        $definitions=self::definitions($p);$conn=$db->pdo??$db->conn_id??null;
        if($conn instanceof PDO&&$conn->getAttribute(PDO::ATTR_DRIVER_NAME)==='sqlite')return;
        foreach($definitions as $table=>$parts){
            $name=$p.$table;$columns=[];
            foreach(IssuerStorage::query($db,'SELECT COLUMN_NAME,COLUMN_TYPE,IS_NULLABLE,COLUMN_DEFAULT,COLLATION_NAME,EXTRA FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?',[$name])->result_array() as $row)$columns[$row['COLUMN_NAME']]=$row;
            $expected=[];
            foreach($parts as $definition){
                if(!preg_match('/^`([^`]+)` (varchar\([0-9]+\)|int unsigned|int|datetime)(.*)$/D',$definition,$match))continue;
                $column=$match[1];$type=$match[2];$tail=$match[3];$expected[]=$column;$actual=preg_replace('/^int\([0-9]+\)/','int',strtolower($columns[$column]['COLUMN_TYPE']??''));$nullable=str_contains($tail,'NOT NULL')?'NO':'YES';
                if($actual!==$type||($columns[$column]['IS_NULLABLE']??null)!==$nullable||!IssuerStorage::sqlNullDefault($columns[$column]??[])||str_contains($tail,'AUTO_INCREMENT')!==str_contains(strtolower($columns[$column]['EXTRA']??''),'auto_increment'))throw new RuntimeException('issuer_milestone_visual_schema_invalid');
                if(preg_match('/COLLATE ([a-z0-9_]+)/',$tail,$collation)&&($columns[$column]['COLLATION_NAME']??null)!==$collation[1])throw new RuntimeException('issuer_milestone_visual_schema_invalid');
            }
            if(array_diff(array_keys($columns),$expected))throw new RuntimeException('issuer_milestone_visual_schema_invalid');
            $engine=IssuerStorage::query($db,'SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?',[$name])->row_array();if(strtoupper($engine['ENGINE']??'')!=='INNODB')throw new RuntimeException('issuer_milestone_visual_schema_invalid');
            $indexes=[];foreach(IssuerStorage::query($db,'SELECT INDEX_NAME,COLUMN_NAME,NON_UNIQUE,SUB_PART FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? ORDER BY INDEX_NAME,SEQ_IN_INDEX',[$name])->result_array() as $row){if($row['SUB_PART']!==null)throw new RuntimeException('issuer_milestone_visual_schema_invalid');$indexes[$row['INDEX_NAME']]['columns'][]=$row['COLUMN_NAME'];$indexes[$row['INDEX_NAME']]['non_unique']=(int)$row['NON_UNIQUE'];}
            if(($indexes['PRIMARY']['columns']??[])!==['issuer_id','project_id','milestone_id']||($indexes['PRIMARY']['non_unique']??null)!==0)throw new RuntimeException('issuer_milestone_visual_schema_invalid');
            $foreign=[];foreach(IssuerStorage::query($db,'SELECT CONSTRAINT_NAME,COLUMN_NAME,REFERENCED_TABLE_NAME,REFERENCED_COLUMN_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND REFERENCED_TABLE_SCHEMA=DATABASE() ORDER BY CONSTRAINT_NAME,ORDINAL_POSITION',[$name])->result_array() as $row)$foreign[$row['CONSTRAINT_NAME']][]=$row['COLUMN_NAME'].':'.$row['REFERENCED_TABLE_NAME'].':'.$row['REFERENCED_COLUMN_NAME'];
            $wanted=['issuer_id:'.$p.'company_project_milestones:issuer_id','project_id:'.$p.'company_project_milestones:project_id','milestone_id:'.$p.'company_project_milestones:id'];if(!in_array($wanted,array_values($foreign),true))throw new RuntimeException('issuer_milestone_visual_schema_invalid');
        }
    }
}
