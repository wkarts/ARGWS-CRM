<?php
declare(strict_types=1);
require_once __DIR__.'/issuers/IssuerStorage.php';require_once __DIR__.'/issuers/IssuerTransaction.php';
/** Additive issuer-owned offline modes. Native gateway/provider receipts remain separate. */
final class NativeCompanyPaymentModesSchema
{
    public const TABLE='company_payment_modes';
    public static function columns():array{return ['id'=>'int unsigned','issuer_id'=>'int unsigned','name'=>'varchar(100)','description'=>'mediumtext','active'=>'int','show_on_pdf'=>'int','selected_by_default'=>'int','invoices_only'=>'int','expenses_only'=>'int','revision'=>'int unsigned','created_by'=>'int'];}
    public static function definitions(string $p):array
    {
        if(!preg_match('/^[A-Za-z0-9_]+$/D',$p))throw new InvalidArgumentException('Invalid prefix');$parts=[];
        foreach(self::columns()as$name=>$type)$parts[]='`'.$name.'` '.$type.' NOT NULL'.($name==='id'?' AUTO_INCREMENT PRIMARY KEY':'');
        $parts[]='UNIQUE KEY `issuer_mode` (`issuer_id`,`id`)';$parts[]='FOREIGN KEY (`issuer_id`) REFERENCES `'.$p.'issuers_registry` (`id`)';
        return [self::TABLE=>$parts];
    }
    public static function ensure($db,string$p):void
    {
        IssuerTransaction::assertIndependent($db);foreach(self::definitions($p)as$table=>$parts)IssuerStorage::query($db,'CREATE TABLE IF NOT EXISTS `'.$p.$table.'` ('.implode(',',$parts).') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin');
        if(property_exists($db,'data_cache'))unset($db->data_cache['table_names']);self::assertValid($db,$p);
    }
    private static function fail():never{throw new RuntimeException('native_payment_modes_schema_invalid');}
    public static function assertValid($db,string$p):void
    {
        self::definitions($p);$table=$p.self::TABLE;if(!$db->table_exists($table))self::fail();
        $pdo=$db->pdo??$db->conn_id??null;$sqlite=$pdo instanceof PDO&&$pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='sqlite';$columns=self::columns();
        if($sqlite){
            $rows=IssuerStorage::query($db,'PRAGMA table_info(`'.$table.'`)')->result_array();if(array_column($rows,'name')!==array_keys($columns))self::fail();
            foreach($rows as$r)if(strtolower($r['type'])!==($r['name']==='id'?'integer':$columns[$r['name']])||$r['dflt_value']!==null||($r['name']!=='id'&&(int)$r['notnull']!==1))self::fail();
            $keys=['id'];foreach(IssuerStorage::query($db,'PRAGMA index_list(`'.$table.'`)')->result_array()as$r){if((int)$r['unique']!==1||(int)$r['partial']!==0)self::fail();$keys[]=implode(',',array_column(IssuerStorage::query($db,'PRAGMA index_info(`'.$r['name'].'`)')->result_array(),'name'));}
            $fks=IssuerStorage::query($db,'PRAGMA foreign_key_list(`'.$table.'`)')->result_array();if(count($fks)!==1)self::fail();$fk=$fks[0];$actual=[$fk['from'],$fk['table'],$fk['to'],$fk['on_delete'],$fk['on_update']];
        }else{
            $rows=IssuerStorage::query($db,'SHOW FULL COLUMNS FROM `'.$table.'`')->result_array();if(array_column($rows,'Field')!==array_keys($columns))self::fail();
            foreach($rows as$r)if(preg_replace('/^int\([0-9]+\)/','int',strtolower($r['Type']))!==$columns[$r['Field']]||$r['Null']!=='NO'||$r['Default']!==null||$r['Extra']!==($r['Field']==='id'?'auto_increment':'')||$r['Collation']!==((str_starts_with($columns[$r['Field']],'varchar')||$columns[$r['Field']]==='mediumtext')?'utf8mb4_bin':null))self::fail();
            $engine=IssuerStorage::query($db,'SELECT ENGINE,TABLE_COLLATION FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?',[$table])->row_array();if(strtoupper($engine['ENGINE']??'')!=='INNODB'||($engine['TABLE_COLLATION']??'')!=='utf8mb4_bin')self::fail();
            $keys=[];foreach(IssuerStorage::query($db,'SHOW INDEX FROM `'.$table.'`')->result_array()as$r){if((int)$r['Non_unique']!==0||$r['Sub_part']!==null||$r['Index_type']!=='BTREE')self::fail();$keys[$r['Key_name']][(int)$r['Seq_in_index']]=$r['Column_name'];}$keys=array_map(static fn($k)=>implode(',',$k),array_values($keys));
            $fks=IssuerStorage::query($db,'SELECT k.COLUMN_NAME,k.REFERENCED_TABLE_NAME,k.REFERENCED_COLUMN_NAME,r.DELETE_RULE,r.UPDATE_RULE FROM information_schema.KEY_COLUMN_USAGE k JOIN information_schema.REFERENTIAL_CONSTRAINTS r ON r.CONSTRAINT_SCHEMA=k.CONSTRAINT_SCHEMA AND r.CONSTRAINT_NAME=k.CONSTRAINT_NAME AND r.TABLE_NAME=k.TABLE_NAME WHERE k.TABLE_SCHEMA=DATABASE() AND k.TABLE_NAME=? AND k.REFERENCED_TABLE_SCHEMA=DATABASE()',[$table])->result_array();if(count($fks)!==1)self::fail();$fk=$fks[0];$actual=[$fk['COLUMN_NAME'],$fk['REFERENCED_TABLE_NAME'],$fk['REFERENCED_COLUMN_NAME'],$fk['DELETE_RULE'],$fk['UPDATE_RULE']];
        }
        sort($keys);if($keys!==['id','issuer_id,id']||array_slice($actual,0,3)!==['issuer_id',$p.'issuers_registry','id']||!in_array($actual[3],['NO ACTION','RESTRICT'],true)||!in_array($actual[4],['NO ACTION','RESTRICT'],true))self::fail();
    }
}
