<?php

declare(strict_types=1);

require_once __DIR__.'/issuers/IssuerStorage.php';
require_once __DIR__.'/issuers/IssuerTransaction.php';

/** Append-only sales revisions and reviewed copy receipts; no historical document backfill. */
final class CompanySalesMutationsSchema
{
    public static function definitions():array
    {
        return [
            'company_sales_revisions'=>['request_key'=>'char(64)','issuer_id'=>'int unsigned','document_id'=>'int unsigned','actor_id'=>'int','revision_no'=>'int unsigned','source_revision'=>'char(64)','before_payload_json'=>'longtext','after_payload_json'=>'longtext','before_hash'=>'char(64)','after_hash'=>'char(64)','origin_hash'=>'char(64)','creation_key'=>'char(64)','creation_actor_id'=>'int','reason'=>'varchar(2000)','form_hash'=>'char(64)','record_hash'=>'char(64)','created_at'=>'datetime'],
            'company_sales_copy_requests'=>['request_key'=>'char(64)','issuer_id'=>'int unsigned','actor_id'=>'int','source_document_id'=>'int unsigned','result_document_id'=>'int unsigned','source_revision'=>'char(64)','source_json'=>'longtext','review_json'=>'longtext','input_hash'=>'char(64)','result_creation_key'=>'char(64)','result_hash'=>'char(64)','record_hash'=>'char(64)','created_at'=>'datetime'],
        ];
    }
    public static function indexes(string$table):array{return match($table){'company_sales_revisions'=>['PRIMARY'=>[['request_key'],0],'sales_revision_number'=>[['document_id','revision_no'],0],'sales_revision_owner'=>[['issuer_id','document_id'],1]],'company_sales_copy_requests'=>['PRIMARY'=>[['request_key'],0],'sales_copy_result'=>[['result_document_id'],0],'sales_copy_source'=>[['issuer_id','source_document_id'],1]],default=>throw new InvalidArgumentException('company_sales_mutation_invalid')};}
    private static function foreign(string$table):array{return ['issuer_id'=>['issuers_registry','id']]+($table==='company_sales_revisions'?['document_id'=>['company_sales_documents','id']]:['source_document_id'=>['company_sales_documents','id'],'result_document_id'=>['company_sales_documents','id']]);}

    /** Exact outgoing FK contract: unexpected, duplicate and composite rules can erase evidence. */
    private static function assertForeignKeys($db,string $p,string $table,bool $sqlite):void
    {
        $groups=[];
        if($sqlite){
            foreach(\IssuerStorage::query($db,'PRAGMA foreign_key_list(`'.$p.$table.'`)')->result_array()as$row)$groups[(string)$row['id']][]=$row;
        }else{
            $sql='SELECT k.CONSTRAINT_NAME,k.ORDINAL_POSITION,k.TABLE_SCHEMA,k.COLUMN_NAME,k.REFERENCED_TABLE_SCHEMA,k.REFERENCED_TABLE_NAME,k.REFERENCED_COLUMN_NAME,r.DELETE_RULE,r.UPDATE_RULE FROM information_schema.KEY_COLUMN_USAGE k JOIN information_schema.REFERENTIAL_CONSTRAINTS r ON r.CONSTRAINT_SCHEMA=k.CONSTRAINT_SCHEMA AND r.TABLE_NAME=k.TABLE_NAME AND r.CONSTRAINT_NAME=k.CONSTRAINT_NAME WHERE k.TABLE_SCHEMA=DATABASE() AND k.TABLE_NAME=? AND k.REFERENCED_TABLE_NAME IS NOT NULL ORDER BY k.CONSTRAINT_NAME,k.ORDINAL_POSITION';
            foreach(\IssuerStorage::query($db,$sql,[$p.$table])->result_array()as$row)$groups[$row['CONSTRAINT_NAME']][]=$row;
        }
        $expected=self::foreign($table);$seen=[];
        if(count($groups)!==count($expected))throw new \RuntimeException('issuer_sales_mutation_schema_invalid');
        foreach($groups as$rows){
            if(count($rows)!==1)throw new \RuntimeException('issuer_sales_mutation_schema_invalid');$row=$rows[0];
            $column=$sqlite?$row['from']:$row['COLUMN_NAME'];$target=$sqlite?$row['table']:$row['REFERENCED_TABLE_NAME'];$key=$sqlite?$row['to']:$row['REFERENCED_COLUMN_NAME'];$delete=$sqlite?$row['on_delete']:$row['DELETE_RULE'];$update=$sqlite?$row['on_update']:$row['UPDATE_RULE'];
            if(!isset($expected[$column])||isset($seen[$column])||$target!==$p.$expected[$column][0]||$key!==$expected[$column][1]||!in_array($delete,['RESTRICT','NO ACTION'],true)||!in_array($update,['RESTRICT','NO ACTION'],true)||($sqlite?(int)$row['seq']!==0:($row['REFERENCED_TABLE_SCHEMA']!==$row['TABLE_SCHEMA']||(int)$row['ORDINAL_POSITION']!==1)))throw new \RuntimeException('issuer_sales_mutation_schema_invalid');
            $seen[$column]=true;
        }
        if(count($seen)!==count($expected))throw new \RuntimeException('issuer_sales_mutation_schema_invalid');
    }

    public static function present($db,string $p):bool
    {
        if(!preg_match('/^[A-Za-z0-9_]+$/D',$p))throw new \InvalidArgumentException('company_sales_mutation_invalid');
        $present=[];foreach(self::definitions() as $t=>$f)$present[]=$db->table_exists($p.$t);
        if(!in_array(true,$present,true))return false;
        self::ensure($db,$p,false);return true;
    }
    public static function ensure($db,string $p,bool $create=true):void
    {
        if(!preg_match('/^[A-Za-z0-9_]+$/D',$p))throw new \InvalidArgumentException('company_sales_mutation_invalid');
        if($create)\IssuerTransaction::assertIndependent($db);
        $conn=$db->pdo??$db->conn_id??null;$sqlite=$conn instanceof PDO&&$conn->getAttribute(PDO::ATTR_DRIVER_NAME)==='sqlite';
        foreach(self::definitions() as $table=>$fields){
            if($create){$parts=[];foreach($fields as $name=>$type){$nullable=str_ends_with($type,'?');$type=rtrim($type,'?');$parts[]='`'.$name.'` '.($name==='id'&&$sqlite?'INTEGER PRIMARY KEY AUTOINCREMENT':$type.(!$sqlite&&preg_match('/char|text/',$type)?' CHARACTER SET utf8mb4 COLLATE utf8mb4_bin':'').($nullable?' NULL':' NOT NULL').($name==='id'?' AUTO_INCREMENT':''));}
                foreach(self::indexes($table) as $name=>[$cols,$non])if(!$sqlite)$parts[]=($name==='PRIMARY'?'PRIMARY KEY':($non?'KEY':'UNIQUE KEY').' `'.$name.'`').' (`'.implode('`,`',$cols).'`)';elseif($name==='PRIMARY'&&!isset($fields['id']))$parts[]='PRIMARY KEY (`'.implode('`,`',$cols).'`)';
                foreach(self::foreign($table) as $col=>[$target,$key])$parts[]='FOREIGN KEY (`'.$col.'`) REFERENCES `'.$p.$target.'` (`'.$key.'`)';
                \IssuerStorage::query($db,'CREATE TABLE IF NOT EXISTS `'.$p.$table.'` ('.implode(',',$parts).')'.($sqlite?'':' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin'));
                if($sqlite)foreach(self::indexes($table) as $name=>[$cols,$non])if($name!=='PRIMARY')\IssuerStorage::query($db,'CREATE '.($non?'':'UNIQUE ').'INDEX IF NOT EXISTS `'.$p.$table.'_'.$name.'` ON `'.$p.$table.'` (`'.implode('`,`',$cols).'`)');
            }
            if($sqlite){$cols=\IssuerStorage::query($db,'PRAGMA table_info(`'.$p.$table.'`)')->result_array();if(array_column($cols,'name')!==array_keys($fields))throw new \RuntimeException('issuer_sales_mutation_schema_invalid');self::assertForeignKeys($db,$p,$table,true);continue;}
            $meta=\IssuerStorage::query($db,'SELECT ENGINE,TABLE_COLLATION FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?',[$p.$table])->row_array();
            if(strtoupper($meta['ENGINE']??'')!=='INNODB'||($meta['TABLE_COLLATION']??'')!=='utf8mb4_bin')throw new \RuntimeException('issuer_sales_mutation_schema_invalid');
            $cols=[];foreach(\IssuerStorage::query($db,'SELECT COLUMN_NAME,COLUMN_TYPE,IS_NULLABLE,COLUMN_DEFAULT,COLLATION_NAME,EXTRA FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?',[$p.$table])->result_array() as $r)$cols[$r['COLUMN_NAME']]=$r;
            if(count($cols)!==count($fields))throw new \RuntimeException('issuer_sales_mutation_schema_invalid');
            foreach($fields as $name=>$type){$nullable=str_ends_with($type,'?');$type=rtrim($type,'?');$r=$cols[$name]??[];if(preg_replace('/^(bigint|int|tinyint)\([0-9]+\)/','$1',strtolower($r['COLUMN_TYPE']??''))!==$type||($r['IS_NULLABLE']??'')!==($nullable?'YES':'NO')||!\IssuerStorage::sqlNullDefault($r)||(preg_match('/char|text/',$type)&&($r['COLLATION_NAME']??'')!=='utf8mb4_bin')||($name==='id'&&($r['EXTRA']??'')!=='auto_increment'))throw new \RuntimeException('issuer_sales_mutation_schema_invalid');}
            $indexes=[];foreach(\IssuerStorage::query($db,'SELECT INDEX_NAME,COLUMN_NAME,NON_UNIQUE,SUB_PART FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? ORDER BY INDEX_NAME,SEQ_IN_INDEX',[$p.$table])->result_array() as $r){if($r['SUB_PART']!==null)throw new \RuntimeException('issuer_sales_mutation_schema_invalid');$indexes[$r['INDEX_NAME']]['columns'][]=$r['COLUMN_NAME'];$indexes[$r['INDEX_NAME']]['non_unique']=(int)$r['NON_UNIQUE'];}
            foreach(self::indexes($table) as $name=>[$cols,$non])if(($indexes[$name]['columns']??[])!==$cols||($indexes[$name]['non_unique']??null)!==$non)throw new \RuntimeException('issuer_sales_mutation_schema_invalid');
            self::assertForeignKeys($db,$p,$table,false);
        }
        if($create&&isset($db->data_cache['table_names']))unset($db->data_cache['table_names']);
    }
}
