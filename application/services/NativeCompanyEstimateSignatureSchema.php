<?php

declare(strict_types=1);
require_once __DIR__.'/issuers/IssuerStorage.php';
require_once __DIR__.'/issuers/IssuerTransaction.php';

/** Additive private signature receipts. Existing estimates and historical attachments are untouched. */
final class NativeCompanyEstimateSignatureSchema
{
    public const TABLE='company_estimate_signatures';
    private static function sqlite($db):bool{$c=$db->pdo??$db->conn_id??null;return $c instanceof PDO&&$c->getAttribute(PDO::ATTR_DRIVER_NAME)==='sqlite';}
    private static function name(string $p):string{if(!preg_match('/^[A-Za-z0-9_]+$/D',$p)||strlen($p.self::TABLE)>64)throw new InvalidArgumentException('Invalid prefix');return $p.self::TABLE;}
    private static function columns():array{return ['request_key'=>'varchar(64)','issuer_id'=>'int unsigned','document_id'=>'int unsigned','contact_id'=>'int','client_id'=>'int','revision'=>'varchar(64)','signer_firstname'=>'varchar(191)','signer_lastname'=>'varchar(191)','signer_email'=>'varchar(191)','signer_title'=>'varchar(191)','signer_ip'=>'varchar(45)','content_hash'=>'varchar(64)','byte_size'=>'int unsigned','content'=>'mediumblob','created_at'=>'datetime'];}
    public static function ensure($db,string $p):void
    {
        IssuerTransaction::assertIndependent($db);$table=self::name($p);if($db->table_exists($table))self::assertValid($db,$p);
        $sqlite=self::sqlite($db);$parts=[];foreach(self::columns() as$name=>$type){$collation=str_starts_with($type,'varchar')?' CHARACTER SET utf8mb4 COLLATE utf8mb4_bin':'';$parts[]='`'.$name.'` '.$type.($sqlite?'':$collation).' NOT NULL';}
        $parts[]='PRIMARY KEY (`request_key`)';$parts[]=$sqlite?'UNIQUE (`issuer_id`,`document_id`)':'UNIQUE KEY `issuer_document` (`issuer_id`,`document_id`)';if(!$sqlite)$parts[]='KEY `signature_document` (`document_id`)';
        $parts[]=($sqlite?'':'CONSTRAINT `nes_'.substr(hash('sha256',$table.':issuer'),0,32).'` ').'FOREIGN KEY (`issuer_id`) REFERENCES `'.$p.'issuers_registry` (`id`)';$parts[]=($sqlite?'':'CONSTRAINT `nes_'.substr(hash('sha256',$table.':document'),0,32).'` ').'FOREIGN KEY (`document_id`) REFERENCES `'.$p.'company_sales_documents` (`id`)';
        IssuerStorage::query($db,'CREATE TABLE IF NOT EXISTS `'.$table.'` ('.implode(',',$parts).')'.($sqlite?'':' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin'));
        if($sqlite)IssuerStorage::query($db,'CREATE INDEX IF NOT EXISTS `nesi_'.substr(hash('sha256',$table),0,32).'` ON `'.$table.'` (`document_id`)');if(property_exists($db,'data_cache'))unset($db->data_cache['table_names']);self::assertValid($db,$p);
    }
    public static function assertValid($db,string $p):void
    {
        $table=self::name($p);$sqlite=self::sqlite($db);if(!$db->table_exists($table))throw new RuntimeException('native_estimate_signature_schema_invalid');
        $columns=IssuerStorage::query($db,$sqlite?'PRAGMA table_info(`'.$table.'`)':'SHOW FULL COLUMNS FROM `'.$table.'`')->result_array();$names=array_column($columns,$sqlite?'name':'Field');if($names!==array_keys(self::columns()))throw new RuntimeException('native_estimate_signature_schema_invalid');
        foreach($columns as$c){$name=$c[$sqlite?'name':'Field'];$type=strtolower($c[$sqlite?'type':'Type']);$type=preg_replace('/^int\([0-9]+\)/','int',$type);if($type!==self::columns()[$name]||($sqlite?(int)$c['notnull']!==1:$c['Null']!=='NO')||($sqlite?$c['dflt_value']!==null:$c['Default']!==null))throw new RuntimeException('native_estimate_signature_schema_invalid');if(!$sqlite&&$c['Collation']!==(str_starts_with($type,'varchar')?'utf8mb4_bin':null))throw new RuntimeException('native_estimate_signature_schema_invalid');}
        if($sqlite){$keys=[];foreach(IssuerStorage::query($db,'PRAGMA index_list(`'.$table.'`)')->result_array() as$i){if((int)$i['partial']!==0)throw new RuntimeException('native_estimate_signature_schema_invalid');$keys[]=((int)$i['unique']===1?'0:':'1:').implode(',',array_column(IssuerStorage::query($db,'PRAGMA index_info(`'.$i['name'].'`)')->result_array(),'name'));}$fk=IssuerStorage::query($db,'PRAGMA foreign_key_list(`'.$table.'`)')->result_array();$actual=array_map(static fn($r)=>[$r['from'],$r['table'],$r['to'],$r['on_delete'],$r['on_update']],$fk);}
        else{$engine=IssuerStorage::query($db,'SELECT ENGINE,TABLE_COLLATION FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?',[$table])->row_array();if(strtoupper($engine['ENGINE']??'')!=='INNODB'||($engine['TABLE_COLLATION']??'')!=='utf8mb4_bin')throw new RuntimeException('native_estimate_signature_schema_invalid');$groups=[];foreach(IssuerStorage::query($db,'SHOW INDEX FROM `'.$table.'`')->result_array()as$i){if($i['Sub_part']!==null||$i['Index_type']!=='BTREE')throw new RuntimeException('native_estimate_signature_schema_invalid');$groups[$i['Key_name']]['unique']=(int)$i['Non_unique'];$groups[$i['Key_name']]['cols'][(int)$i['Seq_in_index']]=$i['Column_name'];}$keys=[];foreach($groups as$g){ksort($g['cols']);$keys[]=$g['unique'].':'.implode(',',$g['cols']);}$fk=IssuerStorage::query($db,'SELECT k.COLUMN_NAME,k.REFERENCED_TABLE_NAME,k.REFERENCED_COLUMN_NAME,r.DELETE_RULE,r.UPDATE_RULE FROM information_schema.KEY_COLUMN_USAGE k JOIN information_schema.REFERENTIAL_CONSTRAINTS r ON r.CONSTRAINT_SCHEMA=k.CONSTRAINT_SCHEMA AND r.CONSTRAINT_NAME=k.CONSTRAINT_NAME AND r.TABLE_NAME=k.TABLE_NAME WHERE k.TABLE_SCHEMA=DATABASE() AND k.TABLE_NAME=? AND k.REFERENCED_TABLE_SCHEMA=DATABASE()',[$table])->result_array();$actual=array_map(static fn($r)=>[$r['COLUMN_NAME'],$r['REFERENCED_TABLE_NAME'],$r['REFERENCED_COLUMN_NAME'],$r['DELETE_RULE'],$r['UPDATE_RULE']],$fk);}
        sort($keys);if($keys!==['0:issuer_id,document_id','0:request_key','1:document_id']||count($actual)!==2)throw new RuntimeException('native_estimate_signature_schema_invalid');foreach($actual as$f){$valid=($f[0]==='issuer_id'&&$f[1]===$p.'issuers_registry'&&$f[2]==='id')||($f[0]==='document_id'&&$f[1]===$p.'company_sales_documents'&&$f[2]==='id');if(!$valid||!in_array($f[3],['RESTRICT','NO ACTION'],true)||!in_array($f[4],['RESTRICT','NO ACTION'],true))throw new RuntimeException('native_estimate_signature_schema_invalid');}
    }
}
