<?php

declare(strict_types=1);
require_once __DIR__.'/issuers/IssuerTransaction.php';

/** New table only. Does not adopt global Leads or rewrite the historical CompanyLeads schema. */
final class CompanyLeadMetadataSchema
{
    public const TABLE='company_lead_metadata';
    public static function definitions(string $p):array
    {
        if(!preg_match('/^[A-Za-z0-9_]+$/D',$p))throw new InvalidArgumentException('Invalid table prefix');
        $text=static fn(string $name,int $length):string=>'`'.$name.'` varchar('.$length.') CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL';
        return[self::TABLE=>['`lead_id` int unsigned NOT NULL PRIMARY KEY','`issuer_id` int unsigned NOT NULL',
            $text('title',100),$text('website',150),$text('address',100),$text('city',100),$text('state',50),$text('zip',15),$text('default_language',40),
            '`country` int unsigned NOT NULL','`lead_value` decimal(15,2) NULL','`currency_json` text CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NULL',
            '`lastcontact` datetime NULL','`revision` int unsigned NOT NULL','`updated_at` datetime NOT NULL',
            'KEY `lead_metadata_owner` (`issuer_id`,`lead_id`)','KEY `lead_metadata_contact` (`issuer_id`,`lastcontact`,`lead_id`)',
            'FOREIGN KEY (`issuer_id`,`lead_id`) REFERENCES `'.$p.'company_leads` (`issuer_id`,`id`)']];
    }
    /** Legacy fixtures remain usable before migration; a completed 489 installation must have this table. */
    public static function available($db,string $p):bool
    {
        self::definitions($p);if($db->table_exists($p.self::TABLE))return true;
        if($db->table_exists($p.'migrations'))foreach(IssuerStorage::query($db,'SELECT version FROM '.$p.'migrations')->result_array()as$row)
            if(ctype_digit((string)($row['version']??''))&&(int)$row['version']>=489)throw new RuntimeException('company_lead_metadata_schema_missing');
        return false;
    }
    public static function ensure($db,string $p):void
    {
        IssuerTransaction::assertIndependent($db);$connection=$db->pdo??$db->conn_id??null;$sqlite=$connection instanceof PDO&&$connection->getAttribute(PDO::ATTR_DRIVER_NAME)==='sqlite';
        foreach(self::definitions($p)as$table=>$parts){
            if($sqlite)$parts=array_map(static fn($part)=>str_replace(' CHARACTER SET utf8mb4 COLLATE utf8mb4_bin','',$part),array_filter($parts,static fn($part)=>!str_starts_with($part,'KEY ')));
            IssuerStorage::query($db,'CREATE TABLE IF NOT EXISTS `'.$p.$table.'` ('.implode(',',$parts).')'.($sqlite?'':' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin'));
        }
        if($sqlite)foreach(['lead_metadata_owner'=>'issuer_id,lead_id','lead_metadata_contact'=>'issuer_id,lastcontact,lead_id']as$index=>$columns)
            IssuerStorage::query($db,'CREATE INDEX IF NOT EXISTS `'.$p.$index.'` ON `'.$p.self::TABLE.'` ('.$columns.')');
        if(property_exists($db,'data_cache'))unset($db->data_cache['table_names']);self::assertValid($db,$p);
    }
    private static function fail():never { throw new RuntimeException('company_lead_metadata_schema_invalid'); }
    public static function assertValid($db,string $p):void
    {
        $definitions=self::definitions($p)[self::TABLE];$name=$p.self::TABLE;$connection=$db->pdo??$db->conn_id??null;$sqlite=$connection instanceof PDO&&$connection->getAttribute(PDO::ATTR_DRIVER_NAME)==='sqlite';$expected=[];
        foreach($definitions as$definition)if(preg_match('/^`([^`]+)` (int unsigned|decimal\([0-9]+,[0-9]+\)|varchar\([0-9]+\)|text|datetime)(.*)$/D',$definition,$match))$expected[$match[1]]=[$match[2],str_contains($match[3],'NOT NULL'),str_contains($match[3],'PRIMARY KEY')];
        if($sqlite){
            $rows=IssuerStorage::query($db,'PRAGMA table_info(`'.$name.'`)')->result_array();if(array_column($rows,'name')!==array_keys($expected))self::fail();
            foreach($rows as$row){[$type,$required,$primary]=$expected[$row['name']];if(strtolower($row['type'])!==$type||(bool)$row['notnull']!==$required||($row['dflt_value']??null)!==null||(bool)$row['pk']!==$primary)self::fail();}
            foreach(['lead_metadata_owner'=>['issuer_id','lead_id'],'lead_metadata_contact'=>['issuer_id','lastcontact','lead_id']]as$index=>$columns){$found=false;
                foreach(IssuerStorage::query($db,'PRAGMA index_list(`'.$name.'`)')->result_array()as$row)if($row['name']===$p.$index){$found=(int)$row['unique']===0&&(int)($row['partial']??0)===0&&array_column(IssuerStorage::query($db,'PRAGMA index_info(`'.$p.$index.'`)')->result_array(),'name')===$columns;}
                if(!$found)self::fail();
            }
            $foreign=IssuerStorage::query($db,'PRAGMA foreign_key_list(`'.$name.'`)')->result_array();usort($foreign,static fn($a,$b)=>(int)$a['seq']<=>(int)$b['seq']);
            if(count($foreign)!==2)self::fail();foreach([['issuer_id','issuer_id'],['lead_id','id']]as$index=>[$from,$to]){$row=$foreign[$index];if($row['from']!==$from||$row['table']!==$p.'company_leads'||$row['to']!==$to||!in_array($row['on_delete'],['RESTRICT','NO ACTION'],true)||!in_array($row['on_update'],['RESTRICT','NO ACTION'],true))self::fail();}
            return;
        }
        $rows=IssuerStorage::query($db,'SELECT COLUMN_NAME,COLUMN_TYPE,IS_NULLABLE,COLUMN_DEFAULT,COLLATION_NAME,EXTRA FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? ORDER BY ORDINAL_POSITION',[$name])->result_array();
        if(array_column($rows,'COLUMN_NAME')!==array_keys($expected))self::fail();
        foreach($rows as$row){[$type,$required]=$expected[$row['COLUMN_NAME']];if(preg_replace('/^int\([0-9]+\)/','int',strtolower($row['COLUMN_TYPE']))!==$type||$row['IS_NULLABLE']!==($required?'NO':'YES')||!IssuerStorage::sqlNullDefault($row)||$row['EXTRA']!=='')self::fail();if((str_starts_with($type,'varchar')||$type==='text')&&$row['COLLATION_NAME']!=='utf8mb4_bin')self::fail();}
        $engine=IssuerStorage::query($db,'SELECT ENGINE,TABLE_COLLATION FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?',[$name])->row_array();if(strtoupper($engine['ENGINE']??'')!=='INNODB'||($engine['TABLE_COLLATION']??'')!=='utf8mb4_bin')self::fail();
        $indexes=[];foreach(IssuerStorage::query($db,'SHOW INDEX FROM `'.$name.'`')->result_array()as$row){if($row['Sub_part']!==null||$row['Index_type']!=='BTREE')self::fail();$indexes[$row['Key_name']]['columns'][(int)$row['Seq_in_index']]=$row['Column_name'];$indexes[$row['Key_name']]['unique']=(int)$row['Non_unique']===0;}
        foreach(['PRIMARY'=>[['lead_id'],true],'lead_metadata_owner'=>[['issuer_id','lead_id'],false],'lead_metadata_contact'=>[['issuer_id','lastcontact','lead_id'],false]]as$key=>[$columns,$unique])if(!isset($indexes[$key])||array_values($indexes[$key]['columns'])!==$columns||$indexes[$key]['unique']!==$unique)self::fail();
        $foreign=IssuerStorage::query($db,'SELECT k.COLUMN_NAME,k.REFERENCED_TABLE_NAME,k.REFERENCED_COLUMN_NAME,r.UPDATE_RULE,r.DELETE_RULE FROM information_schema.KEY_COLUMN_USAGE k JOIN information_schema.REFERENTIAL_CONSTRAINTS r ON r.CONSTRAINT_SCHEMA=k.CONSTRAINT_SCHEMA AND r.CONSTRAINT_NAME=k.CONSTRAINT_NAME AND r.TABLE_NAME=k.TABLE_NAME WHERE k.TABLE_SCHEMA=DATABASE() AND k.TABLE_NAME=? AND k.REFERENCED_TABLE_SCHEMA=DATABASE() ORDER BY k.CONSTRAINT_NAME,k.ORDINAL_POSITION',[$name])->result_array();
        if(count($foreign)!==2)self::fail();foreach([['issuer_id','issuer_id'],['lead_id','id']]as$index=>[$from,$to]){$row=$foreign[$index];if($row['COLUMN_NAME']!==$from||$row['REFERENCED_TABLE_NAME']!==$p.'company_leads'||$row['REFERENCED_COLUMN_NAME']!==$to||!in_array($row['DELETE_RULE'],['RESTRICT','NO ACTION'],true)||!in_array($row['UPDATE_RULE'],['RESTRICT','NO ACTION'],true))self::fail();}
    }
}
