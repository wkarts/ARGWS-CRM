<?php

declare(strict_types=1);
require_once __DIR__.'/issuers/IssuerTransaction.php';

/** Additive stable checklist identities; historical task JSON is not modified by migration. */
final class NativeCompanyTaskItemSchema
{
    public static function definitions(string $prefix):array
    {
        if(!preg_match('/^[A-Za-z0-9_]+$/D',$prefix))throw new InvalidArgumentException('Invalid prefix');
        $result=[];
        foreach(['project'=>'company_tasks','standalone'=>'company_standalone_tasks'] as $kind=>$parent){
            $item='company_'.$kind.'_checklist_items';$state='company_'.$kind.'_checklist_state';
            $foreign=static fn($table)=> 'CONSTRAINT `nci_'.substr(hash('sha256',$prefix.$table),0,32).'` FOREIGN KEY (`issuer_id`,`task_id`) REFERENCES `'.$prefix.$parent.'` (`issuer_id`,`id`)';
            // NULL position is a retained deletion tombstone, never an active item.
            $result[$item]=['`id` int unsigned NOT NULL AUTO_INCREMENT PRIMARY KEY','`issuer_id` int unsigned NOT NULL','`task_id` int unsigned NOT NULL','`position` int unsigned NULL','`description` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL','`finished` int unsigned NOT NULL','`created_by` int NULL','`finished_by` int NULL','`assigned_id` int NULL','`revision` int unsigned NOT NULL','UNIQUE KEY `task_position` (`issuer_id`,`task_id`,`position`)',$foreign($item)];
            $result[$state]=['`issuer_id` int unsigned NOT NULL','`task_id` int unsigned NOT NULL','`source_hash` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL','`revision` int unsigned NOT NULL','PRIMARY KEY (`issuer_id`,`task_id`)',$foreign($state)];
        }
        return$result;
    }
    private static function sqlite($db):bool{$pdo=$db->pdo??$db->conn_id??null;return$pdo instanceof PDO&&$pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='sqlite';}
    private static function fail():never{throw new RuntimeException('native_task_item_schema_invalid');}
    public static function ensure($db,string $p):void
    {
        IssuerTransaction::assertIndependent($db);
        foreach(self::definitions($p)as$table=>$parts)if($db->table_exists($p.$table))self::assertValid($db,$p,$table);
        foreach(self::definitions($p)as$table=>$parts){
            if(self::sqlite($db)){
                $columns=[];foreach($parts as$part){if(str_starts_with($part,'`id`'))$part='`id` INTEGER PRIMARY KEY AUTOINCREMENT';elseif(str_starts_with($part,'UNIQUE KEY '))$part=preg_replace('/^UNIQUE KEY `[^`]+`/','UNIQUE',$part);$columns[]=preg_replace('/ CHARACTER SET utf8mb4 COLLATE utf8mb4_bin/','',$part);}IssuerStorage::query($db,'CREATE TABLE IF NOT EXISTS `'.$p.$table.'` ('.implode(',',$columns).')');
            }else IssuerStorage::query($db,'CREATE TABLE IF NOT EXISTS `'.$p.$table.'` ('.implode(',',$parts).') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin');
        }
        if(property_exists($db,'data_cache'))unset($db->data_cache['table_names']);self::assertValid($db,$p);
    }
    public static function assertValid($db,string $p,?string $only=null):void
    {
        $sqlite=self::sqlite($db);
        $definitions=self::definitions($p);if($only!==null&&!isset($definitions[$only]))self::fail();
        foreach($definitions as$table=>$parts){if($only!==null&&$table!==$only)continue;$name=$p.$table;if(!$db->table_exists($name))self::fail();$expected=[];foreach($parts as$part)if(preg_match('/^`([^`]+)` (int unsigned|int|varchar\([0-9]+\))(.*)$/D',$part,$m))$expected[$m[1]]=['type'=>$m[2],'null'=>!str_contains($m[3],'NOT NULL'),'auto'=>str_contains($m[3],'AUTO_INCREMENT')];
            if($sqlite){$cols=IssuerStorage::query($db,'PRAGMA table_info(`'.$name.'`)')->result_array();if(array_column($cols,'name')!==array_keys($expected))self::fail();foreach($cols as$col){$want=$expected[$col['name']];if(strtolower($col['type'])!==($want['auto']?'integer':$want['type'])||$col['dflt_value']!==null||(!$want['auto']&&(int)$col['notnull']!==($want['null']?0:1)))self::fail();}
                $keys=[];foreach(IssuerStorage::query($db,'PRAGMA index_list(`'.$name.'`)')->result_array()as$idx){if((int)$idx['unique']!==1||(int)$idx['partial']!==0)self::fail();$keys[]='0:'.implode(',',array_column(IssuerStorage::query($db,'PRAGMA index_info(`'.$idx['name'].'`)')->result_array(),'name'));}if(str_ends_with($table,'_items'))$keys[]='0:id';
                $fk=IssuerStorage::query($db,'PRAGMA foreign_key_list(`'.$name.'`)')->result_array();usort($fk,static fn($x,$y)=>(int)$x['seq']<=>(int)$y['seq']);$actual=array_map(static fn($r)=>[$r['from'],$r['table'],$r['to'],$r['on_delete'],$r['on_update']],$fk);
            }else{$cols=IssuerStorage::query($db,'SHOW FULL COLUMNS FROM `'.$name.'`')->result_array();if(array_column($cols,'Field')!==array_keys($expected))self::fail();foreach($cols as$col){$want=$expected[$col['Field']];if(preg_replace('/^int\([0-9]+\)/','int',strtolower($col['Type']))!==$want['type']||$col['Null']!==($want['null']?'YES':'NO')||$col['Default']!==null||$col['Extra']!==($want['auto']?'auto_increment':'')||$col['Collation']!==(str_starts_with($want['type'],'varchar')?'utf8mb4_bin':null))self::fail();}
                $engine=IssuerStorage::query($db,'SELECT ENGINE,TABLE_COLLATION FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?',[$name])->row_array();if(strtoupper($engine['ENGINE']??'')!=='INNODB'||($engine['TABLE_COLLATION']??'')!=='utf8mb4_bin')self::fail();
                $groups=[];foreach(IssuerStorage::query($db,'SHOW INDEX FROM `'.$name.'`')->result_array()as$idx){if($idx['Sub_part']!==null||$idx['Index_type']!=='BTREE')self::fail();$groups[$idx['Key_name']]['unique']=(int)$idx['Non_unique'];$groups[$idx['Key_name']]['cols'][(int)$idx['Seq_in_index']]=$idx['Column_name'];}$keys=[];foreach($groups as$group){ksort($group['cols']);$keys[]=$group['unique'].':'.implode(',',$group['cols']);}
                $fk=IssuerStorage::query($db,'SELECT k.COLUMN_NAME,k.REFERENCED_TABLE_NAME,k.REFERENCED_COLUMN_NAME,r.DELETE_RULE,r.UPDATE_RULE FROM information_schema.KEY_COLUMN_USAGE k JOIN information_schema.REFERENTIAL_CONSTRAINTS r ON r.CONSTRAINT_SCHEMA=k.CONSTRAINT_SCHEMA AND r.CONSTRAINT_NAME=k.CONSTRAINT_NAME AND r.TABLE_NAME=k.TABLE_NAME WHERE k.TABLE_SCHEMA=DATABASE() AND k.TABLE_NAME=? AND k.REFERENCED_TABLE_SCHEMA=DATABASE() ORDER BY k.ORDINAL_POSITION',[$name])->result_array();$actual=array_map(static fn($r)=>[$r['COLUMN_NAME'],$r['REFERENCED_TABLE_NAME'],$r['REFERENCED_COLUMN_NAME'],$r['DELETE_RULE'],$r['UPDATE_RULE']],$fk);
            }
            $wanted=str_ends_with($table,'_items')?['0:id','0:issuer_id,task_id,position']:['0:issuer_id,task_id'];sort($keys);sort($wanted);if($keys!==$wanted)self::fail();$parent=str_contains($table,'_project_')?'company_tasks':'company_standalone_tasks';$pairs=[['issuer_id',$p.$parent,'issuer_id'],['task_id',$p.$parent,'id']];if(count($actual)!==2)self::fail();foreach($actual as$n=>$fk)if(array_slice($fk,0,3)!==$pairs[$n]||!in_array($fk[3],['RESTRICT','NO ACTION'],true)||!in_array($fk[4],['RESTRICT','NO ACTION'],true))self::fail();
        }
    }
}
