<?php

declare(strict_types=1);
require_once __DIR__.'/issuers/IssuerTransaction.php';

/** Core489: private contract form data and a genuinely optional end date. No legacy adoption. */
final class CompanyContractMetadataSchema
{
    public const TABLE='company_contract_metadata';
    private const FIELDS=['issuer_id'=>'int unsigned','contract_id'=>'int unsigned','revision'=>'int unsigned',
        'data_json'=>'mediumtext','content_hash'=>'varchar(64)','frozen'=>'tinyint','updated_at'=>'datetime'];

    public static function definitions(string $p):array
    {
        if(!preg_match('/^[A-Za-z0-9_]+$/D',$p))throw new InvalidArgumentException('Invalid prefix');
        $parts=[];
        foreach(self::FIELDS as $name=>$type)$parts[]='`'.$name.'` '.$type.($name==='data_json'?' CHARACTER SET utf8mb4 COLLATE utf8mb4_bin':($name==='content_hash'?' CHARACTER SET ascii COLLATE ascii_bin':'')).' NOT NULL';
        $parts[]='PRIMARY KEY (`issuer_id`,`contract_id`)';
        $parts[]='FOREIGN KEY (`issuer_id`,`contract_id`) REFERENCES `'.$p.'company_contracts` (`issuer_id`,`id`) ON DELETE RESTRICT ON UPDATE RESTRICT';
        return[self::TABLE=>$parts];
    }
    private static function sqlite($db):bool
    {
        $connection=$db->pdo??$db->conn_id??null;
        return $connection instanceof PDO&&$connection->getAttribute(PDO::ATTR_DRIVER_NAME)==='sqlite';
    }
    public static function available($db,string $p):bool
    {
        self::definitions($p);
        if($db->table_exists($p.self::TABLE))return true;
        if($db->table_exists($p.'migrations'))foreach(IssuerStorage::query($db,'SELECT version FROM '.$p.'migrations')->result_array()as$row)
            if(ctype_digit((string)($row['version']??''))&&(int)$row['version']>=489)throw new RuntimeException('company_contract_metadata_schema_missing');
        return false;
    }
    /** Migration-only DDL; readers and writers never call this method. */
    public static function ensure($db,string $p):array
    {
        self::definitions($p);IssuerTransaction::assertIndependent($db);
        $sqlite=self::sqlite($db);$nullable=self::dateColumn($db,$p);
        require_once __DIR__.'/CompanyContractsSchema.php';
        if($db->table_exists($p.self::TABLE))self::assertValid($db,$p);
        if(!$nullable)CompanyContractsSchema::assertValid($db,$p);
        $before=self::fingerprint($db,$p);
        if(!$nullable){
            // SQLite fixtures explicitly create the evolved parent DDL; production is MySQL.
            if($sqlite)throw new RuntimeException('company_contract_date_fixture_not_migrated');
            IssuerStorage::query($db,'ALTER TABLE `'.$p.'company_contracts` MODIFY `due_date` date NULL');
        }
        foreach(self::definitions($p)as$table=>$parts){
            if($sqlite)$parts=array_map(static fn($part)=>str_replace([' CHARACTER SET utf8mb4 COLLATE utf8mb4_bin',' CHARACTER SET ascii COLLATE ascii_bin'],'',$part),$parts);
            IssuerStorage::query($db,'CREATE TABLE IF NOT EXISTS `'.$p.$table.'` ('.implode(',',$parts).')'.($sqlite?'':' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin'));
        }
        if(property_exists($db,'data_cache'))unset($db->data_cache['table_names']);
        self::assertValid($db,$p);CompanyContractsSchema::assertValid($db,$p);$after=self::fingerprint($db,$p);
        if($after!==$before)throw new RuntimeException('company_contract_migration_rows_changed');
        return ['date_changed'=>!$nullable,'rows'=>$after['rows'],'sha256'=>$after['sha256']];
    }
    private static function fingerprint($db,string $p):array
    {
        $hash=hash_init('sha256');$count=0;$last=0;
        do{
            $rows=IssuerStorage::query($db,'SELECT * FROM `'.$p.'company_contracts` WHERE id>? ORDER BY id LIMIT 500',[$last])->result_array();
            foreach($rows as$row){$id=filter_var($row['id']??null,FILTER_VALIDATE_INT);if($id===false||$id<=$last)self::fail();$last=$id;ksort($row);$row=array_map(static fn($value)=>$value===null?null:(string)$value,$row);hash_update($hash,json_encode($row,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)."\n");$count++;}
        }while(count($rows)===500);
        return ['rows'=>$count,'sha256'=>hash_final($hash)];
    }
    /** Validate exactly the one nullable column allowed by the core contract schema. */
    private static function dateColumn($db,string $p):bool
    {
        if(self::sqlite($db)){
            $rows=IssuerStorage::query($db,'PRAGMA table_info(`'.$p.'company_contracts`)')->result_array();
            $rows=array_values(array_filter($rows,static fn($r)=>$r['name']==='due_date'));
            if(count($rows)!==1||strtolower($rows[0]['type'])!=='date'||$rows[0]['dflt_value']!==null)self::fail();
            return (int)$rows[0]['notnull']===0;
        }
        $rows=IssuerStorage::query($db,'SELECT COLUMN_TYPE,IS_NULLABLE,COLUMN_DEFAULT,EXTRA FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=?',[$p.'company_contracts','due_date'])->result_array();
        if(count($rows)!==1||$rows[0]['COLUMN_TYPE']!=='date'||!in_array($rows[0]['IS_NULLABLE'],['YES','NO'],true)||!IssuerStorage::sqlNullDefault($rows[0])||$rows[0]['EXTRA']!=='')self::fail();
        return $rows[0]['IS_NULLABLE']==='YES';
    }
    public static function assertValid($db,string $p):void
    {
        self::definitions($p);$table=$p.self::TABLE;$rows=static fn($sql,$args=[])=>IssuerStorage::query($db,$sql,$args)->result_array();
        if(!$db->table_exists($table)||!self::dateColumn($db,$p))self::fail();
        if(self::sqlite($db)){
            $columns=$rows('PRAGMA table_info(`'.$table.'`)');if(array_column($columns,'name')!==array_keys(self::FIELDS))self::fail();
            foreach($columns as$r)if(strtolower($r['type'])!==self::FIELDS[$r['name']]||$r['dflt_value']!==null||(int)$r['notnull']!==1||(int)$r['pk']!==(['issuer_id'=>1,'contract_id'=>2][$r['name']]??0))self::fail();
            $foreign=$rows('PRAGMA foreign_key_list(`'.$table.'`)');usort($foreign,static fn($a,$b)=>(int)$a['seq']<=>(int)$b['seq']);
            if(count($foreign)!==2)self::fail();foreach($foreign as$i=>$r)if($r['table']!==$p.'company_contracts'||$r['from']!==['issuer_id','contract_id'][$i]||$r['to']!==['issuer_id','id'][$i]||$r['on_delete']!=='RESTRICT'||$r['on_update']!=='RESTRICT')self::fail();
            $keys=$rows('PRAGMA index_list(`'.$table.'`)');if(count($keys)!==1||(int)$keys[0]['unique']!==1||array_column($rows('PRAGMA index_info(`'.$keys[0]['name'].'`)'),'name')!==['issuer_id','contract_id'])self::fail();return;
        }
        $meta=$rows('SELECT ENGINE,TABLE_COLLATION FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?',[$table])[0]??[];
        if(strtoupper($meta['ENGINE']??'')!=='INNODB'||($meta['TABLE_COLLATION']??'')!=='utf8mb4_bin')self::fail();
        $columns=$rows('SHOW FULL COLUMNS FROM `'.$table.'`');if(array_column($columns,'Field')!==array_keys(self::FIELDS))self::fail();
        foreach($columns as$r){$collation=$r['Field']==='content_hash'?'ascii_bin':($r['Field']==='data_json'?'utf8mb4_bin':null);
            if(preg_replace('/^(int|tinyint)\([0-9]+\)/','$1',strtolower($r['Type']))!==self::FIELDS[$r['Field']]||$r['Null']!=='NO'||!IssuerStorage::sqlNullDefault(['COLUMN_DEFAULT'=>$r['Default']])||$r['Extra']!==''||$r['Collation']!==$collation)self::fail();}
        $keys=$rows('SHOW INDEX FROM `'.$table.'`');usort($keys,static fn($a,$b)=>(int)$a['Seq_in_index']<=>(int)$b['Seq_in_index']);if(count($keys)!==2)self::fail();foreach($keys as$i=>$r)if($r['Key_name']!=='PRIMARY'||(int)$r['Non_unique']!==0||$r['Sub_part']!==null||$r['Index_type']!=='BTREE'||$r['Column_name']!==['issuer_id','contract_id'][$i])self::fail();
        $fk=$rows('SELECT COLUMN_NAME,REFERENCED_TABLE_NAME,REFERENCED_COLUMN_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND REFERENCED_TABLE_SCHEMA=DATABASE() ORDER BY ORDINAL_POSITION',[$table]);if(count($fk)!==2)self::fail();foreach($fk as$i=>$r)if($r['COLUMN_NAME']!==['issuer_id','contract_id'][$i]||$r['REFERENCED_TABLE_NAME']!==$p.'company_contracts'||$r['REFERENCED_COLUMN_NAME']!==['issuer_id','id'][$i])self::fail();
        $rules=$rows('SELECT DELETE_RULE,UPDATE_RULE FROM information_schema.REFERENTIAL_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME=?',[$table]);if(count($rules)!==1||!in_array($rules[0]['DELETE_RULE'],['RESTRICT','NO ACTION'],true)||!in_array($rules[0]['UPDATE_RULE'],['RESTRICT','NO ACTION'],true))self::fail();
    }
    private static function fail():never{throw new RuntimeException('company_contract_metadata_schema_invalid');}
}
