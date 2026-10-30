<?php

declare(strict_types=1);
require_once __DIR__.'/issuers/IssuerTransaction.php';

/** Additive memberships. Existing responsible/assignee is evidence; followers are never promoted. */
final class NativeCompanyWorkAssignmentsSchema
{
    public static function definitions():array
    {
        return [
            'company_project_members'=>['parent'=>'company_projects','resource'=>'project_id','source'=>'responsible_id'],
            'company_task_assignees'=>['parent'=>'company_tasks','resource'=>'task_id','source'=>'assignee_id'],
            'company_standalone_assignees'=>['parent'=>'company_standalone_tasks','resource'=>'task_id','source'=>'assignee_id'],
        ];
    }
    private static function fail():never{throw new RuntimeException('native_work_assignments_schema_invalid');}
    public static function ensure($db,string $p):void
    {
        if(!preg_match('/^[A-Za-z0-9_]+$/D',$p))throw new InvalidArgumentException('Invalid prefix');IssuerTransaction::assertIndependent($db);
        $connection=$db->pdo??$db->conn_id??null;$sqlite=$connection instanceof PDO&&$connection->getAttribute(PDO::ATTR_DRIVER_NAME)==='sqlite';
        foreach(self::definitions() as $table=>$d){
            $resource=$d['resource'];$parent=$d['parent'];
            IssuerStorage::query($db,'CREATE TABLE IF NOT EXISTS `'.$p.$table.'` (`issuer_id` int unsigned NOT NULL,`'.$resource.'` int unsigned NOT NULL,`staff_id` int NOT NULL,`active` tinyint NOT NULL,`revision` int unsigned NOT NULL,`origin` varchar(32)'.($sqlite?'':' CHARACTER SET ascii COLLATE ascii_bin').' NOT NULL,PRIMARY KEY (`issuer_id`,`'.$resource.'`,`staff_id`),FOREIGN KEY (`issuer_id`,`'.$resource.'`) REFERENCES `'.$p.$parent.'` (`issuer_id`,`id`) ON DELETE RESTRICT ON UPDATE RESTRICT)'.($sqlite?'':' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin'));
        }
        if(isset($db->data_cache['table_names']))unset($db->data_cache['table_names']);self::assertValid($db,$p);
        $tables=array_merge(['issuers_lock'],array_keys(self::definitions()),array_column(self::definitions(),'parent'));
        IssuerTransaction::run($db,$p,$tables,function()use($db,$p):void{
            foreach(self::definitions() as $table=>$d){
                $resource=$d['resource'];$parent=$d['parent'];$source=$d['source'];$origin='historical_'.$source;
                // No delete/update of source records. Existing explicit revocations survive migration replay.
                IssuerStorage::query($db,'INSERT INTO '.$p.$table.'(issuer_id,'.$resource.',staff_id,active,revision,origin) SELECT n.issuer_id,n.id,n.'.$source.',1,1,? FROM '.$p.$parent.' n WHERE n.'.$source.'>0 AND NOT EXISTS(SELECT 1 FROM '.$p.$table.' m WHERE m.issuer_id=n.issuer_id AND m.'.$resource.'=n.id AND m.staff_id=n.'.$source.')',[$origin]);
                $missing=IssuerStorage::query($db,'SELECT COUNT(*) n FROM '.$p.$parent.' n WHERE n.'.$source.'>0 AND NOT EXISTS(SELECT 1 FROM '.$p.$table.' m WHERE m.issuer_id=n.issuer_id AND m.'.$resource.'=n.id AND m.staff_id=n.'.$source.')')->row_array();
                if((int)($missing['n']??-1)!==0)throw new RuntimeException('native_work_assignments_backfill_unconfirmed');
            }
        });
    }
    public static function assertValid($db,string $p):void
    {
        if(!preg_match('/^[A-Za-z0-9_]+$/D',$p))throw new InvalidArgumentException('Invalid prefix');$conn=$db->pdo??$db->conn_id??null;$sqlite=$conn instanceof PDO&&$conn->getAttribute(PDO::ATTR_DRIVER_NAME)==='sqlite';
        $rows=static fn($sql,$params=[])=>IssuerStorage::query($db,$sql,$params)->result_array();
        foreach(self::definitions() as $table=>$d){$name=$p.$table;$resource=$d['resource'];$expected=['issuer_id'=>'int unsigned',$resource=>'int unsigned','staff_id'=>'int','active'=>'tinyint','revision'=>'int unsigned','origin'=>'varchar(32)'];
            if(!$db->table_exists($name))self::fail();
            if($sqlite){
                $columns=$rows('PRAGMA table_info(`'.$name.'`)');if(array_column($columns,'name')!==array_keys($expected))self::fail();
                foreach($columns as $r)if(strtolower($r['type'])!==$expected[$r['name']]||(int)$r['notnull']!==1||$r['dflt_value']!==null||(int)$r['pk']!==(['issuer_id'=>1,$resource=>2,'staff_id'=>3,'active'=>0,'revision'=>0,'origin'=>0][$r['name']]))self::fail();
                $foreign=$rows('PRAGMA foreign_key_list(`'.$name.'`)');if(count($foreign)!==2)self::fail();usort($foreign,fn($a,$b)=>(int)$a['seq']<=>(int)$b['seq']);foreach($foreign as $n=>$r)if($r['table']!==$p.$d['parent']||$r['from']!==['issuer_id',$resource][$n]||$r['to']!==['issuer_id','id'][$n]||$r['on_delete']!=='RESTRICT'||$r['on_update']!=='RESTRICT')self::fail();continue;
            }
            $meta=$rows('SELECT ENGINE,TABLE_COLLATION FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?',[$name])[0]??[];
            if(strtoupper($meta['ENGINE']??'')!=='INNODB'||($meta['TABLE_COLLATION']??'')!=='utf8mb4_bin')self::fail();
            $columns=$rows('SELECT COLUMN_NAME,COLUMN_TYPE,IS_NULLABLE,COLUMN_DEFAULT,COLLATION_NAME,EXTRA FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? ORDER BY ORDINAL_POSITION',[$name]);
            if(array_column($columns,'COLUMN_NAME')!==array_keys($expected))self::fail();foreach($columns as $r){$type=preg_replace('/^(int|tinyint)\([0-9]+\)/','$1',strtolower($r['COLUMN_TYPE']));if($type!==$expected[$r['COLUMN_NAME']]||$r['IS_NULLABLE']!=='NO'||!IssuerStorage::sqlNullDefault($r)||$r['EXTRA']!==''||$r['COLLATION_NAME']!==($r['COLUMN_NAME']==='origin'?'ascii_bin':null))self::fail();}
            $indexes=$rows('SELECT INDEX_NAME,COLUMN_NAME,NON_UNIQUE,SUB_PART FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? ORDER BY INDEX_NAME,SEQ_IN_INDEX',[$name]);
            if(count($indexes)!==3)self::fail();foreach($indexes as $n=>$r)if($r['INDEX_NAME']!=='PRIMARY'||$r['COLUMN_NAME']!==['issuer_id',$resource,'staff_id'][$n]||(int)$r['NON_UNIQUE']!==0||$r['SUB_PART']!==null)self::fail();
            $fk=$rows('SELECT COLUMN_NAME,REFERENCED_TABLE_NAME,REFERENCED_COLUMN_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND REFERENCED_TABLE_NAME IS NOT NULL ORDER BY ORDINAL_POSITION',[$name]);
            if(count($fk)!==2)self::fail();foreach($fk as $n=>$r)if($r['COLUMN_NAME']!==['issuer_id',$resource][$n]||$r['REFERENCED_TABLE_NAME']!==$p.$d['parent']||$r['REFERENCED_COLUMN_NAME']!==['issuer_id','id'][$n])self::fail();
            $rules=$rows('SELECT DELETE_RULE,UPDATE_RULE FROM information_schema.REFERENTIAL_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME=?',[$name]);if(count($rules)!==1||!in_array($rules[0]['DELETE_RULE'],['RESTRICT','NO ACTION'],true)||!in_array($rules[0]['UPDATE_RULE'],['RESTRICT','NO ACTION'],true))self::fail();
        }
    }
}
