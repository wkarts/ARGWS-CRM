<?php
declare(strict_types=1);
require_once __DIR__.'/issuers/IssuerStorage.php';
require_once __DIR__.'/issuers/IssuerTransaction.php';

/** Native append-only LOCAL evidence. No bank mutation, cash or credit is created by these tables. */
final class CompanyLocalNoncashSchema
{
    public static function definitions():array
    {
        return [
            'company_local_noncash_declarations'=>['request_key'=>'char(64)','issuer_id'=>'int unsigned','invoice_id'=>'int','origin_id'=>'bigint unsigned','operation_id'=>'bigint unsigned','nominal'=>'decimal(15,2)','discount'=>'decimal(15,2)','abatement'=>'decimal(15,2)','book_date'=>'date','payload_json'=>'longtext','payload_hash'=>'char(64)','record_hash'=>'char(64)','created_by'=>'int','created_at'=>'datetime'],
            'company_local_noncash_restorations'=>['request_key'=>'char(64)','issuer_id'=>'int unsigned','invoice_id'=>'int','declaration_key'=>'char(64)','book_date'=>'date','payload_json'=>'longtext','payload_hash'=>'char(64)','record_hash'=>'char(64)','created_by'=>'int','created_at'=>'datetime'],
            'company_local_noncash_settlements'=>['request_key'=>'char(64)','issuer_id'=>'int unsigned','invoice_id'=>'int','declaration_key'=>'char(64)','restoration_key'=>'char(64)','kind'=>'varchar(24)','payment_id'=>'int?','credit_entry_key'=>'char(64)?','reference_key'=>'char(64)','amount'=>'decimal(15,2)','book_date'=>'date','payload_json'=>'longtext','payload_hash'=>'char(64)','record_hash'=>'char(64)','created_by'=>'int','created_at'=>'datetime'],
        ];
    }
    public static function indexes(string$table):array
    {
        $base=['PRIMARY'=>[['request_key'],0],'local_issuer'=>[['issuer_id'],1]];
        return $base+match($table){
            'company_local_noncash_declarations'=>['local_invoice'=>[['invoice_id'],0],'local_origin'=>[['origin_id'],0]],
            'company_local_noncash_restorations'=>['local_invoice'=>[['invoice_id'],0],'local_declaration'=>[['declaration_key'],0]],
            'company_local_noncash_settlements'=>['local_invoice'=>[['invoice_id'],1],'local_declaration'=>[['declaration_key'],1],'local_restoration'=>[['restoration_key'],1],'local_payment'=>[['payment_id'],0],'local_credit'=>[['credit_entry_key'],0],'local_reference'=>[['reference_key'],0]],
            default=>throw new InvalidArgumentException('company_local_noncash_invalid'),
        };
    }
    public static function foreign(string$table):array
    {
        return ['issuer_id'=>['issuers_registry','id'],'invoice_id'=>['invoices','id']]+match($table){
            'company_local_noncash_declarations'=>[],
            'company_local_noncash_restorations'=>['declaration_key'=>['company_local_noncash_declarations','request_key']],
            'company_local_noncash_settlements'=>['declaration_key'=>['company_local_noncash_declarations','request_key'],'restoration_key'=>['company_local_noncash_restorations','request_key'],'payment_id'=>['invoicepaymentrecords','id']],
            default=>throw new InvalidArgumentException('company_local_noncash_invalid'),
        };
    }
    public static function tables():array{return array_keys(self::definitions());}
    private static function fail():never{throw new RuntimeException('issuer_local_noncash_schema_invalid');}
    public static function ensure($db,string$p,bool$create=true):void
    {
        if(!preg_match('/^[A-Za-z0-9_]+$/D',$p))throw new InvalidArgumentException('company_local_noncash_invalid');
        if($create)IssuerTransaction::assertIndependent($db);
        $connection=$db->pdo??$db->conn_id??null;$sqlite=$connection instanceof PDO&&$connection->getAttribute(PDO::ATTR_DRIVER_NAME)==='sqlite';
        foreach(self::definitions()as$table=>$fields){
            if($create){
                $columns=[];foreach($fields as$name=>$type){$nullable=str_ends_with($type,'?');$type=rtrim($type,'?');$columns[]='`'.$name.'` '.$type.(!$sqlite&&preg_match('/char|text/',$type)?' CHARACTER SET utf8mb4 COLLATE utf8mb4_bin':'').($nullable?' NULL':' NOT NULL');}
                foreach(self::indexes($table)as$name=>[$cols,$non])if(!$sqlite||$name==='PRIMARY')$columns[]=($name==='PRIMARY'?'PRIMARY KEY':($non?'KEY':'UNIQUE KEY').' `'.$name.'`').' (`'.implode('`,`',$cols).'`)';
                foreach(self::foreign($table)as$column=>[$parent,$key])$columns[]='FOREIGN KEY (`'.$column.'`) REFERENCES `'.$p.$parent.'` (`'.$key.'`) ON DELETE RESTRICT ON UPDATE RESTRICT';
                IssuerStorage::query($db,'CREATE TABLE IF NOT EXISTS `'.$p.$table.'` ('.implode(',',$columns).')'.($sqlite?'':' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin'));
                if($sqlite)foreach(self::indexes($table)as$name=>[$cols,$non])if($name!=='PRIMARY')IssuerStorage::query($db,'CREATE '.($non?'':'UNIQUE ').'INDEX IF NOT EXISTS `'.$p.$table.'_'.$name.'` ON `'.$p.$table.'` (`'.implode('`,`',$cols).'`)');
            }
            if($create&&isset($db->data_cache['table_names']))unset($db->data_cache['table_names']);
            if(!$db->table_exists($p.$table))self::fail();
            $rows=static fn(string$sql,array$args=[]):array=>IssuerStorage::query($db,$sql,$args)->result_array();
            if($sqlite){
                $columns=$rows('PRAGMA table_info(`'.$p.$table.'`)');
                if(array_column($columns,'name')!==array_keys($fields))self::fail();
                foreach($columns as$row){$type=$fields[$row['name']];$nullable=str_ends_with($type,'?');if(strtolower($row['type'])!==rtrim($type,'?')||(int)$row['notnull']!==($nullable?0:1)||$row['dflt_value']!==null||(int)$row['pk']!==($row['name']==='request_key'?1:0))self::fail();}
                $seen=[];foreach($rows('PRAGMA index_list(`'.$p.$table.'`)')as$index){if((int)($index['partial']??0)!==0)self::fail();$columns=$rows('PRAGMA index_info(`'.$index['name'].'`)');$names=array_column($columns,'name');if(in_array(null,$names,true))self::fail();$name=$index['origin']==='pk'?'PRIMARY':substr($index['name'],strlen($p.$table.'_'));if(isset($seen[$name]))self::fail();$seen[$name]=[$names,(int)$index['unique']===1?0:1];}
                $expected=self::indexes($table);ksort($seen);ksort($expected);if($seen!==$expected)self::fail();
                $seen=[];foreach($rows('PRAGMA foreign_key_list(`'.$p.$table.'`)')as$row){if((int)$row['seq']!==0||!in_array(strtoupper($row['on_delete']),['NO ACTION','RESTRICT'],true)||!in_array(strtoupper($row['on_update']),['NO ACTION','RESTRICT'],true)||isset($seen[$row['from']]))self::fail();$seen[$row['from']]=[$row['table'],$row['to']];}
                $expected=[];foreach(self::foreign($table)as$column=>[$parent,$key])$expected[$column]=[$p.$parent,$key];ksort($seen);ksort($expected);if($seen!==$expected)self::fail();
                continue;
            }
            $meta=$rows('SELECT ENGINE,TABLE_COLLATION FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?',[$p.$table])[0]??[];
            if(strtoupper($meta['ENGINE']??'')!=='INNODB'||($meta['TABLE_COLLATION']??'')!=='utf8mb4_bin')self::fail();
            $columns=$rows('SELECT COLUMN_NAME,COLUMN_TYPE,IS_NULLABLE,COLUMN_DEFAULT,COLLATION_NAME,EXTRA FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? ORDER BY ORDINAL_POSITION',[$p.$table]);
            if(array_column($columns,'COLUMN_NAME')!==array_keys($fields))self::fail();
            foreach($columns as$row){$type=$fields[$row['COLUMN_NAME']];$nullable=str_ends_with($type,'?');$type=rtrim($type,'?');$actual=preg_replace('/^(bigint|int|tinyint)\([0-9]+\)/','$1',strtolower($row['COLUMN_TYPE']));if($actual!==$type||$row['IS_NULLABLE']!==($nullable?'YES':'NO')||!IssuerStorage::sqlNullDefault($row)||($row['EXTRA']??'')!==''||(preg_match('/char|text/',$type)&&$row['COLLATION_NAME']!=='utf8mb4_bin'))self::fail();}
            $seen=[];foreach($rows('SELECT INDEX_NAME,COLUMN_NAME,NON_UNIQUE,SUB_PART FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? ORDER BY INDEX_NAME,SEQ_IN_INDEX',[$p.$table])as$row){if($row['SUB_PART']!==null)self::fail();$seen[$row['INDEX_NAME']][0][]=$row['COLUMN_NAME'];$seen[$row['INDEX_NAME']][1]=(int)$row['NON_UNIQUE'];}$expected=self::indexes($table);ksort($seen);ksort($expected);if($seen!==$expected)self::fail();
            $rules=[];foreach($rows('SELECT CONSTRAINT_NAME,DELETE_RULE,UPDATE_RULE FROM information_schema.REFERENTIAL_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME=?',[$p.$table])as$row){if(isset($rules[$row['CONSTRAINT_NAME']]))self::fail();$rules[$row['CONSTRAINT_NAME']]=$row;}
            $seen=[];$constraints=[];foreach($rows('SELECT CONSTRAINT_NAME,COLUMN_NAME,ORDINAL_POSITION,REFERENCED_TABLE_SCHEMA,REFERENCED_TABLE_NAME,REFERENCED_COLUMN_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND REFERENCED_TABLE_NAME IS NOT NULL',[$p.$table])as$row){$rule=$rules[$row['CONSTRAINT_NAME']]??null;if(!$rule||(int)$row['ORDINAL_POSITION']!==1||!in_array($rule['DELETE_RULE'],['NO ACTION','RESTRICT'],true)||!in_array($rule['UPDATE_RULE'],['NO ACTION','RESTRICT'],true)||isset($seen[$row['COLUMN_NAME']])||isset($constraints[$row['CONSTRAINT_NAME']]))self::fail();$constraints[$row['CONSTRAINT_NAME']]=true;$seen[$row['COLUMN_NAME']]=[$row['REFERENCED_TABLE_SCHEMA'],$row['REFERENCED_TABLE_NAME'],$row['REFERENCED_COLUMN_NAME']];}if(count($constraints)!==count($rules))self::fail();
            $schema=$rows('SELECT DATABASE() current_schema')[0]['current_schema'];$expected=[];foreach(self::foreign($table)as$column=>[$parent,$key])$expected[$column]=[$schema,$p.$parent,$key];ksort($seen);ksort($expected);if($seen!==$expected)self::fail();
        }
        if($create&&isset($db->data_cache['table_names']))unset($db->data_cache['table_names']);
    }
}
