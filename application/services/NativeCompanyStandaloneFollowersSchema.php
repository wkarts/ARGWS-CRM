<?php
declare(strict_types=1);
require_once __DIR__.'/issuers/IssuerTransaction.php';
/** Missing original follower action on the existing standalone task domain; no inference from assignees. */
final class NativeCompanyStandaloneFollowersSchema
{
    public const TABLE='company_standalone_followers';
    public static function definitions(string$p):array
    {
        if(!preg_match('/^[A-Za-z0-9_]+$/D',$p))throw new InvalidArgumentException('Invalid prefix');
        return[self::TABLE=>['`issuer_id` int unsigned NOT NULL','`task_id` int unsigned NOT NULL','`staff_id` int NOT NULL','PRIMARY KEY (`issuer_id`,`task_id`,`staff_id`)','FOREIGN KEY (`issuer_id`,`task_id`) REFERENCES `'.$p.'company_standalone_tasks` (`issuer_id`,`id`) ON DELETE RESTRICT ON UPDATE RESTRICT']];
    }
    public static function ensure($db,string$p):void
    {
        IssuerTransaction::assertIndependent($db);foreach(self::definitions($p)as$t=>$parts)IssuerStorage::query($db,'CREATE TABLE IF NOT EXISTS '.$p.$t.'('.implode(',',$parts).') ENGINE=InnoDB');self::assertValid($db,$p);
    }
    public static function assertValid($db,string$p):void
    {
        self::definitions($p);$t=$p.self::TABLE;$c=$db->pdo??$db->conn_id??null;$sqlite=$c instanceof PDO&&$c->getAttribute(PDO::ATTR_DRIVER_NAME)==='sqlite';$want=['issuer_id'=>'int unsigned','task_id'=>'int unsigned','staff_id'=>'int'];
        if($sqlite){$cols=IssuerStorage::query($db,'PRAGMA table_info('.$t.')')->result_array();if(array_column($cols,'name')!==array_keys($want))self::fail();foreach($cols as$index=>$col)if(strtolower($col['type'])!==$want[$col['name']]||(int)$col['notnull']!==1||$col['dflt_value']!==null||(int)$col['pk']!==$index+1)self::fail();$fks=IssuerStorage::query($db,'PRAGMA foreign_key_list('.$t.')')->result_array();usort($fks,fn($a,$b)=>$a['seq']<=>$b['seq']);if(count($fks)!==2)self::fail();foreach($fks as$index=>$fk)if($fk['table']!==$p.'company_standalone_tasks'||$fk['from']!==['issuer_id','task_id'][$index]||$fk['to']!==['issuer_id','id'][$index]||$fk['on_delete']!=='RESTRICT'||$fk['on_update']!=='RESTRICT')self::fail();return;}
        $cols=IssuerStorage::query($db,'SHOW FULL COLUMNS FROM '.$t)->result_array();if(array_column($cols,'Field')!==array_keys($want))self::fail();foreach($cols as$col)if(preg_replace('/^int\([0-9]+\)/','int',strtolower($col['Type']))!==$want[$col['Field']]||$col['Null']!=='NO'||$col['Default']!==null||$col['Extra']!==''||$col['Collation']!==null)self::fail();$engine=IssuerStorage::query($db,'SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?',[$t])->row_array();if(strtoupper($engine['ENGINE']??'')!=='INNODB')self::fail();
        $keys=IssuerStorage::query($db,'SHOW INDEX FROM '.$t)->result_array();if(count($keys)!==3)self::fail();foreach($keys as$index=>$key)if($key['Key_name']!=='PRIMARY'||(int)$key['Non_unique']!==0||$key['Column_name']!==array_keys($want)[$index]||(int)$key['Seq_in_index']!==$index+1||$key['Sub_part']!==null||$key['Index_type']!=='BTREE')self::fail();
        $fks=IssuerStorage::query($db,'SELECT k.COLUMN_NAME,k.REFERENCED_TABLE_NAME,k.REFERENCED_COLUMN_NAME,r.DELETE_RULE,r.UPDATE_RULE FROM information_schema.KEY_COLUMN_USAGE k JOIN information_schema.REFERENTIAL_CONSTRAINTS r ON r.CONSTRAINT_SCHEMA=k.CONSTRAINT_SCHEMA AND r.TABLE_NAME=k.TABLE_NAME AND r.CONSTRAINT_NAME=k.CONSTRAINT_NAME WHERE k.TABLE_SCHEMA=DATABASE() AND k.TABLE_NAME=? AND k.REFERENCED_TABLE_SCHEMA=DATABASE() ORDER BY k.ORDINAL_POSITION',[$t])->result_array();if(count($fks)!==2)self::fail();foreach($fks as$index=>$fk)if($fk['COLUMN_NAME']!==['issuer_id','task_id'][$index]||$fk['REFERENCED_TABLE_NAME']!==$p.'company_standalone_tasks'||$fk['REFERENCED_COLUMN_NAME']!==['issuer_id','id'][$index]||!in_array($fk['DELETE_RULE'],['RESTRICT','NO ACTION'],true)||!in_array($fk['UPDATE_RULE'],['RESTRICT','NO ACTION'],true))self::fail();
    }
    private static function fail():never{throw new RuntimeException('native_task_followers_schema_invalid');}
}
