<?php
declare(strict_types=1);
require_once __DIR__.'/issuers/IssuerStorage.php';
require_once __DIR__.'/issuers/IssuerTransaction.php';
/** Additive relation only: selecting a project never creates a billing origin or receipt. */
final class CompanyInvoiceProjectSchema
{
    public const TABLE='company_invoice_native_relations';
    public static function fields():array{return ['issuer_id'=>'int unsigned','invoice_id'=>'int','client_id'=>'int',
        'source_kind'=>'varchar(32)','source_id'=>'int unsigned','source_revision'=>'int unsigned'];}
    private static function fail():never{throw new RuntimeException('issuer_invoice_project_schema_invalid');}
    private static function sqlite($db):bool{$c=$db->pdo??$db->conn_id??null;return $c instanceof PDO&&$c->getAttribute(PDO::ATTR_DRIVER_NAME)==='sqlite';}
    public static function ensure($db,string $p):void
    {
        if(!preg_match('/^[A-Za-z0-9_]+$/D',$p))throw new InvalidArgumentException('Invalid prefix');
        IssuerTransaction::assertIndependent($db);$parts=[];
        foreach(self::fields()as$column=>$type)$parts[]='`'.$column.'` '.$type.' NOT NULL';
        $parts[]='PRIMARY KEY(issuer_id,invoice_id)';$parts[]='UNIQUE(invoice_id)';
        foreach(['issuer_id'=>['issuers_registry','id'],'invoice_id'=>['invoices','id'],'client_id'=>['clients','userid']]as$field=>[$table,$key])
            $parts[]="FOREIGN KEY($field) REFERENCES {$p}{$table}($key) ON DELETE RESTRICT ON UPDATE RESTRICT";
        IssuerStorage::query($db,'CREATE TABLE IF NOT EXISTS '.$p.self::TABLE.' ('.implode(',',$parts).')'.(self::sqlite($db)?'':' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin'));
        if(isset($db->data_cache['table_names']))unset($db->data_cache['table_names']);
        self::assertValid($db,$p);
    }
    public static function assertValid($db,string $p):void
    {
        if(!preg_match('/^[A-Za-z0-9_]+$/D',$p))throw new InvalidArgumentException('Invalid prefix');
        $t=$p.self::TABLE;$rows=static fn($sql,$args=[])=>IssuerStorage::query($db,$sql,$args)->result_array();
        if(!$db->table_exists($t))self::fail();
        $targets=['issuer_id'=>[$p.'issuers_registry','id'],'invoice_id'=>[$p.'invoices','id'],'client_id'=>[$p.'clients','userid']];
        if(self::sqlite($db)){
            $cols=$rows('PRAGMA table_info('.$t.')');
            if(array_column($cols,'name')!==array_keys(self::fields()))self::fail();
            foreach($cols as$c)if(strtolower($c['type'])!==self::fields()[$c['name']]||(int)$c['notnull']!==1||$c['dflt_value']!==null
                ||(int)$c['pk']!==(['issuer_id'=>1,'invoice_id'=>2][$c['name']]??0))self::fail();
            $indexes=$rows('PRAGMA index_list('.$t.')');$unique=[];
            foreach($indexes as$i){if((int)$i['unique']!==1||(int)$i['partial']!==0)self::fail();$unique[]=array_column($rows('PRAGMA index_info('.$i['name'].')'),'name');}
            if(count($unique)!==2||!in_array(['issuer_id','invoice_id'],$unique,true)||!in_array(['invoice_id'],$unique,true))self::fail();
            $fks=$rows('PRAGMA foreign_key_list('.$t.')');if(count($fks)!==3)self::fail();
            foreach($fks as$f){if(!isset($targets[$f['from']])||[$f['table'],$f['to']]!==$targets[$f['from']]
                ||$f['on_delete']!=='RESTRICT'||$f['on_update']!=='RESTRICT')self::fail();unset($targets[$f['from']]);}
            if($targets)self::fail();return;
        }
        $meta=$rows('SELECT ENGINE,TABLE_COLLATION FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?',[$t])[0]??[];
        if(strtoupper($meta['ENGINE']??'')!=='INNODB'||($meta['TABLE_COLLATION']??'')!=='utf8mb4_bin')self::fail();
        $cols=$rows('SELECT COLUMN_NAME,COLUMN_TYPE,IS_NULLABLE,COLUMN_DEFAULT,COLLATION_NAME,EXTRA FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? ORDER BY ORDINAL_POSITION',[$t]);
        if(array_column($cols,'COLUMN_NAME')!==array_keys(self::fields()))self::fail();
        foreach($cols as$c)if(preg_replace('/^int\([0-9]+\)/','int',strtolower($c['COLUMN_TYPE']))!==self::fields()[$c['COLUMN_NAME']]
            ||$c['IS_NULLABLE']!=='NO'||!IssuerStorage::sqlNullDefault($c)||$c['EXTRA']!==''
            ||$c['COLLATION_NAME']!==($c['COLUMN_NAME']==='source_kind'?'utf8mb4_bin':null))self::fail();
        $indexes=$rows('SELECT INDEX_NAME,COLUMN_NAME,NON_UNIQUE,SUB_PART FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? ORDER BY INDEX_NAME,SEQ_IN_INDEX',[$t]);
        $unique=[];
        foreach($indexes as$i){if($i['SUB_PART']!==null)self::fail();if((int)$i['NON_UNIQUE']===0)$unique[$i['INDEX_NAME']][]=$i['COLUMN_NAME'];}
        if(($unique['PRIMARY']??[])!==['issuer_id','invoice_id']||count($unique)!==2||!in_array(['invoice_id'],$unique,true))self::fail();
        $fks=$rows('SELECT k.COLUMN_NAME,k.REFERENCED_TABLE_NAME,k.REFERENCED_COLUMN_NAME,r.DELETE_RULE,r.UPDATE_RULE FROM information_schema.KEY_COLUMN_USAGE k JOIN information_schema.REFERENTIAL_CONSTRAINTS r ON r.CONSTRAINT_SCHEMA=k.CONSTRAINT_SCHEMA AND r.TABLE_NAME=k.TABLE_NAME AND r.CONSTRAINT_NAME=k.CONSTRAINT_NAME WHERE k.TABLE_SCHEMA=DATABASE() AND k.TABLE_NAME=? AND k.REFERENCED_TABLE_SCHEMA=DATABASE()',[$t]);
        if(count($fks)!==3)self::fail();
        foreach($fks as$f){if(!isset($targets[$f['COLUMN_NAME']])||[$f['REFERENCED_TABLE_NAME'],$f['REFERENCED_COLUMN_NAME']]!==$targets[$f['COLUMN_NAME']]
            ||$f['DELETE_RULE']!=='RESTRICT'||$f['UPDATE_RULE']!=='RESTRICT')self::fail();unset($targets[$f['COLUMN_NAME']]);}
        if($targets)self::fail();
    }
}
