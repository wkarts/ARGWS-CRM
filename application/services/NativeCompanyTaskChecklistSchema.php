<?php

declare(strict_types=1);
require_once __DIR__.'/issuers/IssuerTransaction.php';

/** Core-474 checklist templates and durable explicit action receipts. Historical templates are not copied. */
final class NativeCompanyTaskChecklistSchema
{
    public static function definitions(string $p):array
    {
        if(!preg_match('/^[A-Za-z0-9_]+$/D',$p))throw new InvalidArgumentException('Invalid prefix');
        $definitions = [
            'company_task_checklist_templates'=>['`id` int unsigned NOT NULL AUTO_INCREMENT PRIMARY KEY','`issuer_id` int unsigned NOT NULL','`description` varchar(4000) NOT NULL','`revision` int unsigned NOT NULL','`created_by` int NOT NULL','`created_at` datetime NOT NULL','UNIQUE KEY `template_owner` (`issuer_id`,`id`)','FOREIGN KEY (`issuer_id`) REFERENCES `'.$p.'issuers_registry` (`id`)'],
            'company_task_checklist_template_requests'=>['`request_key` varchar(64) NOT NULL','`issuer_id` int unsigned NOT NULL','`actor_id` int NOT NULL','`operation` varchar(16) NOT NULL','`source_kind` varchar(32) NOT NULL','`input_hash` varchar(64) NOT NULL','`result_id` int unsigned NOT NULL','`created_at` datetime NOT NULL','PRIMARY KEY (`request_key`)','KEY `issuer` (`issuer_id`)','FOREIGN KEY (`issuer_id`) REFERENCES `'.$p.'issuers_registry` (`id`)'],
        ];
        foreach($definitions as$table=>&$parts)foreach($parts as$index=>&$part)if(str_starts_with($part,'FOREIGN KEY '))$part='CONSTRAINT `nctfk_'.substr(hash('sha256',$p.$table.':'.$index),0,40).'` '.$part;
        unset($parts,$part);return $definitions;
    }

    public static function ensure($db,string $p):void
    {
        IssuerTransaction::assertIndependent($db);foreach(self::definitions($p)as$t=>$parts)IssuerStorage::query($db,'CREATE TABLE IF NOT EXISTS `'.$p.$t.'` ('.implode(',',$parts).') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin');
        if(property_exists($db,'data_cache'))unset($db->data_cache['table_names']);self::assertValid($db,$p);
    }
    public static function assertValid($db,string $p):void
    {
        $pdo=$db->pdo??$db->conn_id??null;$sqlite=$pdo instanceof PDO&&$pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='sqlite';
        foreach(self::definitions($p)as$table=>$parts){$t=$p.$table;$expected=[];foreach($parts as$part)if(preg_match('/^`([^`]+)` (int unsigned|int|datetime|varchar\([0-9]+\)) (.*)$/D',$part,$m))$expected[$m[1]]=[$m[2],str_contains($m[3],'AUTO_INCREMENT')];
            if(!$db->table_exists($t))self::fail();
            if($sqlite){$rows=IssuerStorage::query($db,'PRAGMA table_info(`'.$t.'`)')->result_array();if(array_column($rows,'name')!==array_keys($expected))self::fail();foreach($rows as$r){[$type,$auto]=$expected[$r['name']];if(strtolower($r['type'])!==($auto?'integer':$type)||$r['dflt_value']!==null||(!$auto&&(int)$r['notnull']!==1))self::fail();}
            }else{
                $rows=IssuerStorage::query($db,'SHOW FULL COLUMNS FROM `'.$t.'`')->result_array();if(array_column($rows,'Field')!==array_keys($expected))self::fail();foreach($rows as$r){[$type,$auto]=$expected[$r['Field']];if(preg_replace('/^int\([0-9]+\)/','int',strtolower($r['Type']))!==$type||$r['Null']!=='NO'||$r['Default']!==null||$r['Extra']!==($auto?'auto_increment':'')||$r['Collation']!==(str_starts_with($type,'varchar')?'utf8mb4_bin':null))self::fail();}
                $engine=IssuerStorage::query($db,'SELECT ENGINE,TABLE_COLLATION FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?',[$t])->row_array();if(strtoupper($engine['ENGINE']??'')!=='INNODB'||($engine['TABLE_COLLATION']??'')!=='utf8mb4_bin')self::fail();
            }
            $keys=[];if($sqlite){foreach(IssuerStorage::query($db,'PRAGMA index_list(`'.$t.'`)')->result_array()as$r){if((int)$r['unique']!==1)self::fail();$keys[]=implode(',',array_column(IssuerStorage::query($db,'PRAGMA index_info(`'.$r['name'].'`)')->result_array(),'name'));}if($table==='company_task_checklist_templates')$keys[]='id';}
            else{self::assertMysqlIndexes($table,IssuerStorage::query($db,'SHOW INDEX FROM `'.$t.'`')->result_array());}
            if($sqlite){$wanted=$table==='company_task_checklist_templates'?['id','issuer_id,id']:['request_key'];sort($keys);sort($wanted);if($keys!==$wanted)self::fail();}
            if($sqlite){$fk=IssuerStorage::query($db,'PRAGMA foreign_key_list(`'.$t.'`)')->result_array();usort($fk,fn($a,$b)=>(int)$a['seq']<=>(int)$b['seq']);$actual=array_map(fn($r)=>[$r['from'],$r['table'],$r['to'],$r['on_delete'],$r['on_update']],$fk);}
            else{$fk=IssuerStorage::query($db,'SELECT k.COLUMN_NAME,k.REFERENCED_TABLE_NAME,k.REFERENCED_COLUMN_NAME,r.DELETE_RULE,r.UPDATE_RULE FROM information_schema.KEY_COLUMN_USAGE k JOIN information_schema.REFERENTIAL_CONSTRAINTS r ON r.CONSTRAINT_SCHEMA=k.CONSTRAINT_SCHEMA AND r.CONSTRAINT_NAME=k.CONSTRAINT_NAME AND r.TABLE_NAME=k.TABLE_NAME WHERE k.TABLE_SCHEMA=DATABASE() AND k.TABLE_NAME=? AND k.REFERENCED_TABLE_SCHEMA=DATABASE() ORDER BY k.ORDINAL_POSITION',[$t])->result_array();$actual=array_map(fn($r)=>[$r['COLUMN_NAME'],$r['REFERENCED_TABLE_NAME'],$r['REFERENCED_COLUMN_NAME'],$r['DELETE_RULE'],$r['UPDATE_RULE']],$fk);}
            $wantedFk=[['issuer_id',$p.'issuers_registry','id']];if(count($actual)!==count($wantedFk))self::fail();foreach($actual as$i=>$fk)if(array_slice($fk,0,3)!==$wantedFk[$i]||!in_array($fk[3],['NO ACTION','RESTRICT'],true)||!in_array($fk[4],['NO ACTION','RESTRICT'],true))self::fail();
        }
    }
    private static function assertMysqlIndexes(string$table,array$rows):void
    {
        $keys=[];foreach($rows as$r){$name=$r['Key_name'];$unique=(int)$r['Non_unique'];$seq=(int)$r['Seq_in_index'];
            if(!in_array($unique,[0,1],true)||$seq<1||$r['Sub_part']!==null||$r['Index_type']!=='BTREE'||isset($keys[$name]['columns'][$seq])||isset($keys[$name]['non_unique'])&&$keys[$name]['non_unique']!==$unique)self::fail();
            $keys[$name]['non_unique']=$unique;$keys[$name]['columns'][$seq]=$r['Column_name'];
        }
        $signatures=[];foreach($keys as$key){ksort($key['columns']);if(array_keys($key['columns'])!==range(1,count($key['columns'])))self::fail();$signatures[]=$key['non_unique'].':'.implode(',',$key['columns']);}
        $wanted=$table==='company_task_checklist_templates'?['0:id','0:issuer_id,id']:['0:request_key','1:issuer_id'];sort($signatures);sort($wanted);if($signatures!==$wanted)self::fail();
    }
    private static function fail():never{throw new RuntimeException('native_task_checklist_schema_invalid');}
}
