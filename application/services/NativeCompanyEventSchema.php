<?php

declare(strict_types=1);
require_once __DIR__.'/issuers/IssuerStorage.php';

/** Additive storage for native calendar events. Legacy events are never adopted. */
final class NativeCompanyEventSchema
{
    private const COLUMNS = ['eventid'=>'int unsigned','issuer_id'=>'int unsigned','userid'=>'int','title'=>'varchar(191)','description'=>'text','start'=>'datetime','end'=>'datetime','public'=>'tinyint unsigned','color'=>'varchar(7)','reminder_before'=>'int unsigned','reminder_before_type'=>'varchar(7)','isstartnotified'=>'tinyint unsigned','revision'=>'int unsigned'];
    private static function table(string $p):string{if(!preg_match('/^[A-Za-z0-9_]+$/D',$p))throw new InvalidArgumentException('Invalid prefix');return $p.'company_events';}
    private static function sqlite($db):bool{$c=$db->pdo??$db->conn_id??null;return$c instanceof PDO&&$c->getAttribute(PDO::ATTR_DRIVER_NAME)==='sqlite';}
    public static function ensure($db,string $p):void
    {
        $t=self::table($p);
        if($db->table_exists($t)){self::assertValid($db,$p);return;}
        if(self::sqlite($db)){
            IssuerStorage::query($db,"CREATE TABLE {$t} (eventid INTEGER NOT NULL PRIMARY KEY AUTOINCREMENT,issuer_id INTEGER NOT NULL,userid INTEGER NOT NULL,title TEXT NOT NULL,description TEXT NOT NULL,start TEXT NOT NULL,end TEXT NULL,public INTEGER NOT NULL,color TEXT NOT NULL,reminder_before INTEGER NOT NULL,reminder_before_type TEXT NOT NULL,isstartnotified INTEGER NOT NULL,revision INTEGER NOT NULL,UNIQUE(issuer_id,eventid),FOREIGN KEY(issuer_id) REFERENCES {$p}issuers_registry(id))");
            IssuerStorage::query($db,"CREATE INDEX {$t}_issuer_start ON {$t}(issuer_id,start,eventid)");
        }else IssuerStorage::query($db,"CREATE TABLE `{$t}` (
            eventid INT UNSIGNED NOT NULL AUTO_INCREMENT,
            issuer_id INT UNSIGNED NOT NULL, userid INT NOT NULL,
            title VARCHAR(191) NOT NULL, description TEXT NOT NULL,
            start DATETIME NOT NULL, end DATETIME NULL,
            public TINYINT UNSIGNED NOT NULL, color VARCHAR(7) NOT NULL,
            reminder_before INT UNSIGNED NOT NULL, reminder_before_type VARCHAR(7) NOT NULL,
            isstartnotified TINYINT UNSIGNED NOT NULL, revision INT UNSIGNED NOT NULL,
            PRIMARY KEY(eventid), UNIQUE KEY issuer_event(issuer_id,eventid),
            KEY issuer_start(issuer_id,start,eventid),
            FOREIGN KEY(issuer_id) REFERENCES `{$p}issuers_registry`(id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        if(property_exists($db,'data_cache'))unset($db->data_cache['table_names']);
        self::assertValid($db,$p);
    }
    private static function assertSqlite($db,string $p):void
    {
        $t=self::table($p);$rows=IssuerStorage::query($db,'PRAGMA table_info('.$t.')')->result_array();if(array_column($rows,'name')!==array_keys(self::COLUMNS))throw new RuntimeException('native_event_schema_invalid');
        foreach($rows as$r){$name=$r['name'];$type=str_contains(self::COLUMNS[$name],'int')?'INTEGER':'TEXT';if(strtoupper($r['type'])!==$type||(int)$r['notnull']!==($name==='end'?0:1)||$r['dflt_value']!==null||(int)$r['pk']!==($name==='eventid'?1:0))throw new RuntimeException('native_event_schema_invalid');}
        $indexes=[];foreach(IssuerStorage::query($db,'PRAGMA index_list('.$t.')')->result_array()as$i){if((int)$i['partial']!==0||!preg_match('/^[A-Za-z0-9_]+$/D',$i['name']))throw new RuntimeException('native_event_schema_invalid');$cols=IssuerStorage::query($db,'PRAGMA index_info('.$i['name'].')')->result_array();$indexes[]=(string)$i['unique'].':'.implode(',',array_column($cols,'name'));}sort($indexes);if($indexes!==['0:issuer_id,start,eventid','1:issuer_id,eventid'])throw new RuntimeException('native_event_schema_invalid');
        $fk=IssuerStorage::query($db,'PRAGMA foreign_key_list('.$t.')')->result_array();if(count($fk)!==1||$fk[0]['from']!=='issuer_id'||$fk[0]['table']!==$p.'issuers_registry'||$fk[0]['to']!=='id'||!in_array($fk[0]['on_delete'],['RESTRICT','NO ACTION'],true)||!in_array($fk[0]['on_update'],['RESTRICT','NO ACTION'],true))throw new RuntimeException('native_event_schema_invalid');
    }
    public static function assertValid($db,string $p):void
    {
        $t=self::table($p);
        if(self::sqlite($db)){self::assertSqlite($db,$p);return;}
        $rows=IssuerStorage::query($db,"SHOW FULL COLUMNS FROM `{$t}`")->result_array();
        if(array_column($rows,'Field')!==array_keys(self::COLUMNS))throw new RuntimeException('native_event_schema_invalid');
        foreach($rows as$r){$name=$r['Field'];$type=preg_replace('/\b(int|tinyint)\(\d+\)/','$1',strtolower($r['Type']));$text=in_array($name,['title','description','color','reminder_before_type'],true);
            if($type!==self::COLUMNS[$name]||$r['Null']!==($name==='end'?'YES':'NO')||$r['Default']!==null||$r['Extra']!==($name==='eventid'?'auto_increment':'')||$r['Collation']!==($text?'utf8mb4_unicode_ci':null))throw new RuntimeException('native_event_schema_invalid');
        }
        $engine=IssuerStorage::query($db,'SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?',[$t])->row_array();
        if(strtoupper($engine['ENGINE']??'')!=='INNODB')throw new RuntimeException('native_event_schema_invalid');
        $keys=[];foreach(IssuerStorage::query($db,"SHOW INDEX FROM `{$t}`")->result_array()as$r){if($r['Sub_part']!==null||$r['Index_type']!=='BTREE')throw new RuntimeException('native_event_schema_invalid');$keys[$r['Key_name']]['unique']=(int)$r['Non_unique'];$keys[$r['Key_name']]['columns'][(int)$r['Seq_in_index']]=$r['Column_name'];}
        $expected=['PRIMARY'=>['unique'=>0,'columns'=>[1=>'eventid']],'issuer_event'=>['unique'=>0,'columns'=>[1=>'issuer_id',2=>'eventid']],'issuer_start'=>['unique'=>1,'columns'=>[1=>'issuer_id',2=>'start',3=>'eventid']]];ksort($keys);ksort($expected);if($keys!==$expected)throw new RuntimeException('native_event_schema_invalid');
        $fks=IssuerStorage::query($db,'SELECT k.COLUMN_NAME,k.REFERENCED_TABLE_NAME,k.REFERENCED_COLUMN_NAME,r.DELETE_RULE,r.UPDATE_RULE FROM information_schema.KEY_COLUMN_USAGE k JOIN information_schema.REFERENTIAL_CONSTRAINTS r ON r.CONSTRAINT_SCHEMA=k.CONSTRAINT_SCHEMA AND r.CONSTRAINT_NAME=k.CONSTRAINT_NAME AND r.TABLE_NAME=k.TABLE_NAME WHERE k.TABLE_SCHEMA=DATABASE() AND k.TABLE_NAME=? AND k.REFERENCED_TABLE_NAME IS NOT NULL',[$t])->result_array();
        if(count($fks)!==1||$fks[0]['COLUMN_NAME']!=='issuer_id'||$fks[0]['REFERENCED_TABLE_NAME']!==$p.'issuers_registry'||$fks[0]['REFERENCED_COLUMN_NAME']!=='id'||!in_array($fks[0]['DELETE_RULE'],['RESTRICT','NO ACTION'],true)||!in_array($fks[0]['UPDATE_RULE'],['RESTRICT','NO ACTION'],true))throw new RuntimeException('native_event_schema_invalid');
    }
}
