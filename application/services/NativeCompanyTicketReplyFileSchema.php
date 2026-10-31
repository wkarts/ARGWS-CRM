<?php

declare(strict_types=1);
require_once __DIR__.'/issuers/IssuerTransaction.php';
/** Explicit provenance for new reply attachments; historic files are never guessed or backfilled. */
final class NativeCompanyTicketReplyFileSchema
{
    public const TABLE='company_ticket_reply_files';
    private static function sqlite($db):bool{$pdo=$db->pdo??$db->conn_id??null;return$pdo instanceof PDO&&$pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='sqlite';}
    private static function fail():never{throw new RuntimeException('native_ticket_reply_file_schema_invalid');}
    public static function ensure($db,string$p):void
    {
        if(!preg_match('/^[A-Za-z0-9_]+$/D',$p))throw new InvalidArgumentException('Invalid prefix');IssuerTransaction::assertIndependent($db);$table=$p.self::TABLE;
        if($db->table_exists($table)){self::assertValid($db,$p);return;}
        $key='CONSTRAINT `ntrf_'.substr(hash('sha256',$table.':file'),0,30).'` FOREIGN KEY (`file_key`) REFERENCES `'.$p.'company_ticket_files` (`file_key`)';$reply='CONSTRAINT `ntrf_'.substr(hash('sha256',$table.':reply'),0,30).'` FOREIGN KEY (`reply_id`) REFERENCES `'.$p.'company_ticket_replies` (`id`)';
        $columns=['`file_key` varchar(64) '.(self::sqlite($db)?'':'CHARACTER SET ascii COLLATE ascii_bin ').'NOT NULL PRIMARY KEY','`issuer_id` int unsigned NOT NULL','`ticket_id` int unsigned NOT NULL','`reply_id` int unsigned NOT NULL','`content_hash` varchar(64) '.(self::sqlite($db)?'':'CHARACTER SET ascii COLLATE ascii_bin ').'NOT NULL','`actor_id` int NOT NULL','`created_at` datetime NOT NULL',$key,$reply];
        if(!self::sqlite($db))$columns[]='KEY `reply_owner` (`issuer_id`,`ticket_id`,`reply_id`)';IssuerStorage::query($db,'CREATE TABLE `'.$table.'` ('.implode(',',$columns).')'.(self::sqlite($db)?'':' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin'));
        if(self::sqlite($db))IssuerStorage::query($db,'CREATE INDEX `'.$table.'_owner` ON `'.$table.'` (`issuer_id`,`ticket_id`,`reply_id`)');if(property_exists($db,'data_cache'))unset($db->data_cache['table_names']);self::assertValid($db,$p);
    }
    public static function assertValid($db,string$p):void
    {
        if(!preg_match('/^[A-Za-z0-9_]+$/D',$p))throw new InvalidArgumentException('Invalid prefix');$t=$p.self::TABLE;if(!$db->table_exists($t))self::fail();$names=['file_key','issuer_id','ticket_id','reply_id','content_hash','actor_id','created_at'];$types=['varchar(64)','int unsigned','int unsigned','int unsigned','varchar(64)','int','datetime'];
        if(self::sqlite($db)){$rows=IssuerStorage::query($db,'PRAGMA table_info(`'.$t.'`)')->result_array();if(array_column($rows,'name')!==$names)self::fail();foreach($rows as$n=>$r)if(strtolower($r['type'])!==$types[$n]||(int)$r['notnull']!==1||$r['dflt_value']!==null||(int)$r['pk']!==($n===0?1:0))self::fail();$keys=[];foreach(IssuerStorage::query($db,'PRAGMA index_list(`'.$t.'`)')->result_array()as$r){if((int)$r['partial']!==0)self::fail();$keys[]=(int)$r['unique'].':'.implode(',',array_column(IssuerStorage::query($db,'PRAGMA index_info(`'.$r['name'].'`)')->result_array(),'name'));}$fks=IssuerStorage::query($db,'PRAGMA foreign_key_list(`'.$t.'`)')->result_array();$actual=array_map(static fn($r)=>[$r['from'],$r['table'],$r['to'],$r['on_delete'],$r['on_update']],$fks);
        }else{$rows=IssuerStorage::query($db,'SHOW FULL COLUMNS FROM `'.$t.'`')->result_array();if(array_column($rows,'Field')!==$names)self::fail();foreach($rows as$n=>$r)if(preg_replace('/^int\([0-9]+\)/','int',strtolower($r['Type']))!==$types[$n]||$r['Null']!=='NO'||$r['Default']!==null||$r['Extra']!==''||$r['Collation']!==(in_array($n,[0,4],true)?'ascii_bin':null))self::fail();$meta=IssuerStorage::query($db,'SELECT ENGINE,TABLE_COLLATION FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?',[$t])->row_array();if(strtoupper($meta['ENGINE']??'')!=='INNODB'||($meta['TABLE_COLLATION']??'')!=='utf8mb4_bin')self::fail();$groups=[];foreach(IssuerStorage::query($db,'SHOW INDEX FROM `'.$t.'`')->result_array()as$r){if($r['Sub_part']!==null||$r['Index_type']!=='BTREE')self::fail();$groups[$r['Key_name']]['unique']=(int)$r['Non_unique'];$groups[$r['Key_name']]['cols'][(int)$r['Seq_in_index']]=$r['Column_name'];}$keys=[];foreach($groups as$g){ksort($g['cols']);$keys[]=(1-$g['unique']).':'.implode(',',$g['cols']);}$fks=IssuerStorage::query($db,'SELECT k.COLUMN_NAME,k.REFERENCED_TABLE_NAME,k.REFERENCED_COLUMN_NAME,r.DELETE_RULE,r.UPDATE_RULE FROM information_schema.KEY_COLUMN_USAGE k JOIN information_schema.REFERENTIAL_CONSTRAINTS r ON r.CONSTRAINT_SCHEMA=k.CONSTRAINT_SCHEMA AND r.CONSTRAINT_NAME=k.CONSTRAINT_NAME AND r.TABLE_NAME=k.TABLE_NAME WHERE k.TABLE_SCHEMA=DATABASE() AND k.TABLE_NAME=? AND k.REFERENCED_TABLE_SCHEMA=DATABASE()',[$t])->result_array();$actual=array_map(static fn($r)=>[$r['COLUMN_NAME'],$r['REFERENCED_TABLE_NAME'],$r['REFERENCED_COLUMN_NAME'],$r['DELETE_RULE'],$r['UPDATE_RULE']],$fks);}
        // MySQL creates an additional index for reply_id's foreign key.
        $wanted=['1:file_key','0:issuer_id,ticket_id,reply_id'];if(!self::sqlite($db))$wanted[]='0:reply_id';sort($keys);sort($wanted);if($keys!==$wanted)self::fail();$wantedFk=[['file_key',$p.'company_ticket_files','file_key'],['reply_id',$p.'company_ticket_replies','id']];$got=[];foreach($actual as$fk){if(!in_array($fk[3],['RESTRICT','NO ACTION'],true)||!in_array($fk[4],['RESTRICT','NO ACTION'],true))self::fail();$got[]=array_slice($fk,0,3);}sort($got);sort($wantedFk);if($got!==$wantedFk)self::fail();
    }
}
