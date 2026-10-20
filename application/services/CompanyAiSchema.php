<?php

declare(strict_types=1);

require_once __DIR__.'/issuers/IssuerStorage.php';
require_once __DIR__.'/issuers/IssuerTransaction.php';

/** Durable opt-in AI attempts; no provider secrets, raw prompts or legacy adoption. */
final class CompanyAiSchema
{
    public static function definitions():array
    {
        return ['company_ai_requests'=>['request_key'=>'char(64)','issuer_id'=>'int unsigned','actor_id'=>'int','ticket_id'=>'int unsigned','ticket_revision'=>'int unsigned','config_epoch'=>'char(64)','operation'=>'varchar(24)','input_hash'=>'char(64)','source_hash'=>'char(64)','dedupe_hash'=>'char(64)','status'=>'varchar(16)','response_cipher'=>'longtext','result_hash'=>'char(64)','record_hash'=>'char(64)','created_at'=>'datetime','updated_at'=>'datetime']];
    }
    public static function indexes(string $table):array
    {
        if($table!=='company_ai_requests')throw new InvalidArgumentException('company_ai_invalid');return ['PRIMARY'=>[['request_key'],0],'ai_owned_dedupe'=>[['issuer_id','actor_id','dedupe_hash'],0],'ai_owned_ticket'=>[['issuer_id','ticket_id'],1]];
    }
    private static function foreign(string $table):array{return ['issuer_id'=>['issuers_registry','id'],'ticket_id'=>['company_tickets','id']];}

    public static function present($db,string $p):bool
    {
        if(!preg_match('/^[A-Za-z0-9_]+$/D',$p))throw new \InvalidArgumentException('company_ai_invalid');
        $present=[];foreach(self::definitions() as $t=>$f)$present[]=$db->table_exists($p.$t);
        if(!in_array(true,$present,true))return false;
        self::ensure($db,$p,false);return true;
    }
    public static function ensure($db,string $p,bool $create=true):void
    {
        if(!preg_match('/^[A-Za-z0-9_]+$/D',$p))throw new \InvalidArgumentException('company_ai_invalid');
        if($create)\IssuerTransaction::assertIndependent($db);
        $conn=$db->pdo??$db->conn_id??null;$sqlite=$conn instanceof PDO&&$conn->getAttribute(PDO::ATTR_DRIVER_NAME)==='sqlite';
        foreach(self::definitions() as $table=>$fields){
            if($create){$parts=[];foreach($fields as $name=>$type){$nullable=str_ends_with($type,'?');$type=rtrim($type,'?');$parts[]='`'.$name.'` '.($name==='id'&&$sqlite?'INTEGER PRIMARY KEY AUTOINCREMENT':$type.(!$sqlite&&preg_match('/char|text/',$type)?' CHARACTER SET utf8mb4 COLLATE utf8mb4_bin':'').($nullable?' NULL':' NOT NULL').($name==='id'?' AUTO_INCREMENT':''));}
                foreach(self::indexes($table) as $name=>[$cols,$non])if(!$sqlite)$parts[]=($name==='PRIMARY'?'PRIMARY KEY':($non?'KEY':'UNIQUE KEY').' `'.$name.'`').' (`'.implode('`,`',$cols).'`)';elseif($name==='PRIMARY'&&!isset($fields['id']))$parts[]='PRIMARY KEY (`'.implode('`,`',$cols).'`)';
                foreach(self::foreign($table) as $col=>[$target,$key])$parts[]='CONSTRAINT `pmrv_'.substr(hash('sha256',$p.$table.'.'.$col),0,40).'` FOREIGN KEY (`'.$col.'`) REFERENCES `'.$p.$target.'` (`'.$key.'`)';
                \IssuerStorage::query($db,'CREATE TABLE IF NOT EXISTS `'.$p.$table.'` ('.implode(',',$parts).')'.($sqlite?'':' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin'));
                if($sqlite)foreach(self::indexes($table) as $name=>[$cols,$non])if($name!=='PRIMARY')\IssuerStorage::query($db,'CREATE '.($non?'':'UNIQUE ').'INDEX IF NOT EXISTS `'.$p.$table.'_'.$name.'` ON `'.$p.$table.'` (`'.implode('`,`',$cols).'`)');
            }
            if($sqlite){$cols=\IssuerStorage::query($db,'PRAGMA table_info(`'.$p.$table.'`)')->result_array();if(array_column($cols,'name')!==array_keys($fields))throw new \RuntimeException('issuer_ai_schema_invalid');continue;}
            $meta=\IssuerStorage::query($db,'SELECT ENGINE,TABLE_COLLATION FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?',[$p.$table])->row_array();
            if(strtoupper($meta['ENGINE']??'')!=='INNODB'||($meta['TABLE_COLLATION']??'')!=='utf8mb4_bin')throw new \RuntimeException('issuer_ai_schema_invalid');
            $cols=[];foreach(\IssuerStorage::query($db,'SELECT COLUMN_NAME,COLUMN_TYPE,IS_NULLABLE,COLUMN_DEFAULT,COLLATION_NAME,EXTRA FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?',[$p.$table])->result_array() as $r)$cols[$r['COLUMN_NAME']]=$r;
            if(count($cols)!==count($fields))throw new \RuntimeException('issuer_ai_schema_invalid');
            foreach($fields as $name=>$type){$nullable=str_ends_with($type,'?');$type=rtrim($type,'?');$r=$cols[$name]??[];if(preg_replace('/^(int|tinyint)\([0-9]+\)/','$1',strtolower($r['COLUMN_TYPE']??''))!==$type||($r['IS_NULLABLE']??'')!==($nullable?'YES':'NO')||!\IssuerStorage::sqlNullDefault($r)||(preg_match('/char|text/',$type)&&($r['COLLATION_NAME']??'')!=='utf8mb4_bin')||($name==='id'&&($r['EXTRA']??'')!=='auto_increment'))throw new \RuntimeException('issuer_ai_schema_invalid');}
            $indexes=[];foreach(\IssuerStorage::query($db,'SELECT INDEX_NAME,COLUMN_NAME,NON_UNIQUE,SUB_PART FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? ORDER BY INDEX_NAME,SEQ_IN_INDEX',[$p.$table])->result_array() as $r){if($r['SUB_PART']!==null)throw new \RuntimeException('issuer_ai_schema_invalid');$indexes[$r['INDEX_NAME']]['columns'][]=$r['COLUMN_NAME'];$indexes[$r['INDEX_NAME']]['non_unique']=(int)$r['NON_UNIQUE'];}
            foreach(self::indexes($table) as $name=>[$cols,$non])if(($indexes[$name]['columns']??[])!==$cols||($indexes[$name]['non_unique']??null)!==$non)throw new \RuntimeException('issuer_ai_schema_invalid');
            foreach(self::foreign($table) as $col=>[$target,$key]){$fk=\IssuerStorage::query($db,'SELECT REFERENCED_TABLE_NAME,REFERENCED_COLUMN_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=? AND REFERENCED_TABLE_SCHEMA=DATABASE()',[$p.$table,$col])->result_array();if(count($fk)!==1||$fk[0]['REFERENCED_TABLE_NAME']!==$p.$target||$fk[0]['REFERENCED_COLUMN_NAME']!==$key)throw new \RuntimeException('issuer_ai_schema_invalid');}
        }
        if($create&&isset($db->data_cache['table_names']))unset($db->data_cache['table_names']);
    }
}
