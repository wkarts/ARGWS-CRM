<?php

declare(strict_types=1);
require_once __DIR__.'/issuers/IssuerTransaction.php';

/** Native project configuration is explicitly declared, never inferred from historical active projects or billing origins. */
final class NativeCompanyProjectMetadataSchema
{
    public const TABLE='company_project_native_metadata';
    public const FIELDS=['issuer_id'=>'int unsigned','project_id'=>'int unsigned','revision'=>'int unsigned',
        'workflow_status'=>'int unsigned','billing_type'=>'int unsigned','currency_id'=>'int',
        'project_cost'=>'varchar(32)','project_rate_per_hour'=>'varchar(32)','estimated_hours'=>'varchar(32)',
        'progress_from_tasks'=>'tinyint','progress'=>'int unsigned','hourly_rate_id'=>'int unsigned'];
    private const NULLABLE=['project_cost','estimated_hours','hourly_rate_id'];
    public static function definitions(string$p):array
    {
        if(!preg_match('/^[A-Za-z0-9_]+$/D',$p))throw new InvalidArgumentException('Invalid prefix');
        $parts=[];foreach(self::FIELDS as$name=>$type)$parts[]='`'.$name.'` '.$type.(str_starts_with($type,'varchar')?' CHARACTER SET ascii COLLATE ascii_bin':'').(in_array($name,self::NULLABLE,true)?' NULL':' NOT NULL');
        $parts[]='PRIMARY KEY (`issuer_id`,`project_id`)';
        $parts[]='FOREIGN KEY (`issuer_id`,`project_id`) REFERENCES `'.$p.'company_projects` (`issuer_id`,`id`) ON DELETE RESTRICT ON UPDATE RESTRICT';
        return[self::TABLE=>$parts];
    }
    public static function ensure($db,string$p):void
    {
        IssuerTransaction::assertIndependent($db);
        foreach(self::definitions($p)as$table=>$parts)IssuerStorage::query($db,'CREATE TABLE IF NOT EXISTS `'.$p.$table.'` ('.implode(',',$parts).') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin');
        if(property_exists($db,'data_cache'))unset($db->data_cache['table_names']);self::assertValid($db,$p);
    }
    public static function assertValid($db,string$p):void
    {
        self::definitions($p);$table=$p.self::TABLE;
        $pdo=$db->pdo??$db->conn_id??null;$sqlite=$pdo instanceof PDO&&$pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='sqlite';
        $rows=static fn($sql,$args=[])=>IssuerStorage::query($db,$sql,$args)->result_array();
        if(!$db->table_exists($table))self::fail();
        if($sqlite){
            $columns=$rows('PRAGMA table_info(`'.$table.'`)');if(array_column($columns,'name')!==array_keys(self::FIELDS))self::fail();
            foreach($columns as$r)if(strtolower($r['type'])!==self::FIELDS[$r['name']]||$r['dflt_value']!==null||(int)$r['notnull']!==(in_array($r['name'],self::NULLABLE,true)?0:1)||(int)$r['pk']!==(['issuer_id'=>1,'project_id'=>2][$r['name']]??0))self::fail();
            $foreign=$rows('PRAGMA foreign_key_list(`'.$table.'`)');usort($foreign,static fn($a,$b)=>(int)$a['seq']<=>(int)$b['seq']);
            if(count($foreign)!==2)self::fail();foreach($foreign as$i=>$r)if($r['table']!==$p.'company_projects'||$r['from']!==['issuer_id','project_id'][$i]||$r['to']!==['issuer_id','id'][$i]||$r['on_delete']!=='RESTRICT'||$r['on_update']!=='RESTRICT')self::fail();
            $keys=$rows('PRAGMA index_list(`'.$table.'`)');if(count($keys)!==1||(int)$keys[0]['unique']!==1||array_column($rows('PRAGMA index_info(`'.$keys[0]['name'].'`)'),'name')!==['issuer_id','project_id'])self::fail();return;
        }
        $meta=$rows('SELECT ENGINE,TABLE_COLLATION FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?',[$table])[0]??[];
        if(strtoupper($meta['ENGINE']??'')!=='INNODB'||($meta['TABLE_COLLATION']??'')!=='utf8mb4_bin')self::fail();
        $columns=$rows('SHOW FULL COLUMNS FROM `'.$table.'`');if(array_column($columns,'Field')!==array_keys(self::FIELDS))self::fail();
        foreach($columns as$r)if(preg_replace('/^(int|tinyint)\([0-9]+\)/','$1',strtolower($r['Type']))!==self::FIELDS[$r['Field']]||$r['Null']!==(in_array($r['Field'],self::NULLABLE,true)?'YES':'NO')||!IssuerStorage::sqlNullDefault(['COLUMN_DEFAULT'=>$r['Default']])||$r['Extra']!==''||$r['Collation']!==(str_starts_with(self::FIELDS[$r['Field']],'varchar')?'ascii_bin':null))self::fail();
        $keys=$rows('SHOW INDEX FROM `'.$table.'`');usort($keys,static fn($a,$b)=>(int)$a['Seq_in_index']<=>(int)$b['Seq_in_index']);if(count($keys)!==2)self::fail();foreach($keys as$i=>$r)if($r['Key_name']!=='PRIMARY'||(int)$r['Non_unique']!==0||$r['Sub_part']!==null||$r['Index_type']!=='BTREE'||$r['Column_name']!==['issuer_id','project_id'][$i])self::fail();
        $fk=$rows('SELECT COLUMN_NAME,REFERENCED_TABLE_NAME,REFERENCED_COLUMN_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND REFERENCED_TABLE_SCHEMA=DATABASE() ORDER BY ORDINAL_POSITION',[$table]);if(count($fk)!==2)self::fail();foreach($fk as$i=>$r)if($r['COLUMN_NAME']!==['issuer_id','project_id'][$i]||$r['REFERENCED_TABLE_NAME']!==$p.'company_projects'||$r['REFERENCED_COLUMN_NAME']!==['issuer_id','id'][$i])self::fail();
        $rules=$rows('SELECT DELETE_RULE,UPDATE_RULE FROM information_schema.REFERENTIAL_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME=?',[$table]);if(count($rules)!==1||!in_array($rules[0]['DELETE_RULE'],['RESTRICT','NO ACTION'],true)||!in_array($rules[0]['UPDATE_RULE'],['RESTRICT','NO ACTION'],true))self::fail();
    }
    private static function fail():never{throw new RuntimeException('native_project_metadata_schema_invalid');}
}
