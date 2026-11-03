<?php
declare(strict_types=1);
require_once __DIR__.'/issuers/IssuerTransaction.php';
/** Additive 474 contract. NULL means scalar tax is absent, never a zero-rate placeholder. */
final class NativeCompanyExpenseFormSchema
{
    public const TABLE='company_expense_native_forms';
    public const FIELDS=['expense_id'=>'int unsigned','issuer_id'=>'int unsigned','revision'=>'int unsigned','actor_id'=>'int','expense_revision'=>'int unsigned','profile_json'=>'longtext','basis_hash'=>'varchar(64)','audit_id'=>'bigint unsigned'];
    private static function sqlite($db):bool{$c=$db->pdo??$db->conn_id??null;return$c instanceof PDO&&$c->getAttribute(PDO::ATTR_DRIVER_NAME)==='sqlite';}
    public static function definitions(string$p):array
    {
        if(!preg_match('/^[A-Za-z0-9_]+$/D',$p)||strlen($p.self::TABLE)>64)throw new InvalidArgumentException('Invalid physical table prefix');
        $parts=[];foreach(self::FIELDS as$name=>$type)$parts[]='`'.$name.'` '.$type.($name==='profile_json'?' CHARACTER SET utf8mb4 COLLATE utf8mb4_bin':($name==='basis_hash'?' CHARACTER SET ascii COLLATE ascii_bin':'')).' NOT NULL';
        $parts[]='PRIMARY KEY (`expense_id`)';
        foreach(['expense_id'=>'company_expenses','issuer_id'=>'issuers_registry']as$column=>$target)$parts[]='CONSTRAINT `nef_'.substr(hash('sha256',$p.self::TABLE.'.'.$column),0,40).'` FOREIGN KEY (`'.$column.'`) REFERENCES `'.$p.$target.'` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT';
        return[self::TABLE=>$parts];
    }
    public static function installed($db,string$p):bool
    {
        self::definitions($p);$rows=IssuerStorage::query($db,self::sqlite($db)?"SELECT name FROM sqlite_master WHERE type='table' AND name=?":'SELECT TABLE_NAME AS name FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?',[$p.self::TABLE])->result_array();if(count($rows)>1)self::fail();return count($rows)===1;
    }
    /** Strict metadata inspection is shared by migration and runtime; it never updates a row. */
    private static function scalarColumn($db,string$p):array
    {
        if(self::sqlite($db)){$rows=IssuerStorage::query($db,'PRAGMA table_info(`'.$p.'company_expenses`)')->result_array();$rows=array_values(array_filter($rows,fn($r)=>$r['name']==='tax_rate'));if(count($rows)!==1||strtolower($rows[0]['type'])!=='int unsigned'||$rows[0]['dflt_value']!==null)self::fail();return['nullable'=>(int)$rows[0]['notnull']===0];}
        $rows=IssuerStorage::query($db,'SELECT COLUMN_TYPE,IS_NULLABLE,COLUMN_DEFAULT,EXTRA,COLLATION_NAME,COLUMN_COMMENT FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=?',[$p.'company_expenses','tax_rate'])->result_array();
        if(count($rows)!==1)self::fail();$r=$rows[0];$type=preg_replace('/^int\([0-9]+\)/','int',strtolower($r['COLUMN_TYPE']));
        if($type!=='int unsigned'||!in_array($r['IS_NULLABLE'],['YES','NO'],true)||!IssuerStorage::sqlNullDefault($r)||$r['EXTRA']!==''||$r['COLLATION_NAME']!==null||$r['COLUMN_COMMENT']!=='')self::fail();
        $engine=IssuerStorage::query($db,'SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?',[$p.'company_expenses'])->row_array();if(strtoupper($engine['ENGINE']??'')!=='INNODB')self::fail();return['nullable'=>$r['IS_NULLABLE']==='YES'];
    }
    public static function ensure($db,string$p):void
    {
        $definitions=self::definitions($p);IssuerTransaction::assertIndependent($db);$column=self::scalarColumn($db,$p);
        foreach($definitions as$table=>$parts)IssuerStorage::query($db,'CREATE TABLE IF NOT EXISTS `'.$p.$table.'` ('.implode(',',$parts).') ENGINE=InnoDB');
        if(!$column['nullable']){if(self::sqlite($db))throw new RuntimeException('issuer_native_expense_form_mysql_upgrade_required');IssuerStorage::query($db,'ALTER TABLE `'.$p.'company_expenses` MODIFY `tax_rate` INT UNSIGNED NULL DEFAULT NULL');}
        self::assertValid($db,$p);
    }
    public static function assertValid($db,string$p):void
    {
        self::definitions($p);if(!self::scalarColumn($db,$p)['nullable'])self::fail();$table=$p.self::TABLE;
        $rows=IssuerStorage::query($db,self::sqlite($db)?'PRAGMA table_info(`'.$table.'`)':'SELECT COLUMN_NAME,COLUMN_TYPE,IS_NULLABLE,COLUMN_DEFAULT,EXTRA,COLLATION_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? ORDER BY ORDINAL_POSITION',self::sqlite($db)?[]:[$table])->result_array();if(count($rows)!==count(self::FIELDS))self::fail();
        foreach(array_keys(self::FIELDS)as$i=>$field){$r=$rows[$i];$type=preg_replace('/^(int|bigint)\([0-9]+\)/','$1',strtolower($r['type']??$r['COLUMN_TYPE']??''));if(($r['name']??$r['COLUMN_NAME']??null)!==$field||$type!==self::FIELDS[$field]||!IssuerStorage::sqlNullDefault(['COLUMN_DEFAULT'=>$r['dflt_value']??$r['COLUMN_DEFAULT']??null]))self::fail();if(self::sqlite($db)){if((int)$r['notnull']!==1||(int)$r['pk']!==($field==='expense_id'?1:0))self::fail();}elseif($r['IS_NULLABLE']!=='NO'||$r['EXTRA']!==''||$r['COLLATION_NAME']!==match($field){'profile_json'=>'utf8mb4_bin','basis_hash'=>'ascii_bin',default=>null})self::fail();}
        if(self::sqlite($db)){$foreign=IssuerStorage::query($db,'PRAGMA foreign_key_list(`'.$table.'`)')->result_array();$actual=[];foreach($foreign as$r){if(!in_array($r['on_delete'],['RESTRICT','NO ACTION'],true)||!in_array($r['on_update'],['RESTRICT','NO ACTION'],true))self::fail();$actual[]=$r['from'].':'.$r['table'].':'.$r['to'];}}
        else{$engine=IssuerStorage::query($db,'SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?',[$table])->row_array();if(strtoupper($engine['ENGINE']??'')!=='INNODB')self::fail();$pk=IssuerStorage::query($db,'SELECT COLUMN_NAME FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND INDEX_NAME=? ORDER BY SEQ_IN_INDEX',[$table,'PRIMARY'])->result_array();if(array_column($pk,'COLUMN_NAME')!==['expense_id'])self::fail();$foreign=IssuerStorage::query($db,'SELECT k.COLUMN_NAME,k.REFERENCED_TABLE_NAME,k.REFERENCED_COLUMN_NAME,r.DELETE_RULE,r.UPDATE_RULE FROM information_schema.KEY_COLUMN_USAGE k JOIN information_schema.REFERENTIAL_CONSTRAINTS r ON r.CONSTRAINT_SCHEMA=k.CONSTRAINT_SCHEMA AND r.CONSTRAINT_NAME=k.CONSTRAINT_NAME AND r.TABLE_NAME=k.TABLE_NAME WHERE k.TABLE_SCHEMA=DATABASE() AND k.TABLE_NAME=? AND k.REFERENCED_TABLE_SCHEMA=DATABASE()',[$table])->result_array();$actual=[];foreach($foreign as$r){if(!in_array($r['DELETE_RULE'],['RESTRICT','NO ACTION'],true)||!in_array($r['UPDATE_RULE'],['RESTRICT','NO ACTION'],true))self::fail();$actual[]=$r['COLUMN_NAME'].':'.$r['REFERENCED_TABLE_NAME'].':'.$r['REFERENCED_COLUMN_NAME'];}}
        sort($actual);$expected=['expense_id:'.$p.'company_expenses:id','issuer_id:'.$p.'issuers_registry:id'];sort($expected);if($actual!==$expected)self::fail();
    }
    private static function fail():never{throw new RuntimeException('issuer_native_expense_form_schema_invalid');}
}
