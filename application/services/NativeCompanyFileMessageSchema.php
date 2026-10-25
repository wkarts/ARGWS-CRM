<?php
declare(strict_types=1);
require_once __DIR__.'/issuers/IssuerStorage.php';
require_once __DIR__.'/issuers/IssuerTransaction.php';
final class NativeCompanyFileMessageSchema
{
    public static function tables():array{return['company_native_file_mail_requests'];}
    public static function definitions(string$p):array
    {
        if(!preg_match('/^[A-Za-z0-9_]+$/D',$p))throw new InvalidArgumentException('Invalid prefix');
        $fk_issuer_id="nfm_".substr(hash("sha256",$p."NativeCompanyFileMessageSchema:issuer_id"),0,40);
        $fk_queue_id="nfm_".substr(hash("sha256",$p."NativeCompanyFileMessageSchema:queue_id"),0,40);
        return['company_native_file_mail_requests'=>"CREATE TABLE {$p}company_native_file_mail_requests(queue_id INT NOT NULL,issuer_id INT UNSIGNED NOT NULL,request_key CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,request_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,binding_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,write_nonce CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,PRIMARY KEY(queue_id),UNIQUE KEY issuer_request(issuer_id,request_key),CONSTRAINT {$fk_issuer_id} FOREIGN KEY(issuer_id) REFERENCES {$p}issuers_registry(id),CONSTRAINT {$fk_queue_id} FOREIGN KEY(queue_id) REFERENCES {$p}mail_queue(id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin"];
    }
    public static function ensure($db,string$p):void
    {
        IssuerTransaction::assertIndependent($db);
        foreach(self::definitions($p)as$table=>$sql){$pdo=$db->pdo??$db->conn_id??null;if($pdo instanceof PDO&&$pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='sqlite')$sql="CREATE TABLE IF NOT EXISTS {$p}{$table}(queue_id INTEGER PRIMARY KEY,issuer_id INTEGER NOT NULL,request_key TEXT NOT NULL,request_hash TEXT NOT NULL,binding_hash TEXT NOT NULL,write_nonce TEXT NOT NULL,UNIQUE(issuer_id,request_key),FOREIGN KEY(issuer_id) REFERENCES {$p}issuers_registry(id),FOREIGN KEY(queue_id) REFERENCES {$p}mail_queue(id))";else$sql=str_replace('CREATE TABLE ','CREATE TABLE IF NOT EXISTS ',$sql);IssuerStorage::query($db,$sql);}if(property_exists($db,'data_cache'))unset($db->data_cache['table_names']);self::assertValid($db,$p);
    }
    public static function assertValid($db,string$p):void
    {
        self::definitions($p);$pdo=$db->pdo??$db->conn_id??null;if($pdo instanceof PDO&&$pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='sqlite'){
            $table=$p.'company_native_file_mail_requests';$fail=static function(){throw new RuntimeException('native_file_mail_schema_invalid');};
            $columns=IssuerStorage::query($db,'PRAGMA table_info('.$table.')')->result_array();$expected=['queue_id','issuer_id','request_key','request_hash','binding_hash','write_nonce'];
            if(array_column($columns,'name')!==$expected)$fail();foreach($columns as$i=>$r){if(strtoupper($r['type'])!==($i<2?'INTEGER':'TEXT')||(int)$r['notnull']!==($i===0?0:1)||(int)$r['pk']!==($i===0?1:0)||$r['dflt_value']!==null)$fail();}
            $indexes=[];foreach(IssuerStorage::query($db,'PRAGMA index_list('.$table.')')->result_array()as$r){if((int)$r['partial']!==0)$fail();$cols=IssuerStorage::query($db,'PRAGMA index_info('.$r['name'].')')->result_array();$indexes[]=[array_column($cols,'name'),(int)$r['unique']];}if($indexes!==[[['issuer_id','request_key'],1]])$fail();
            $refs=[];foreach(IssuerStorage::query($db,'PRAGMA foreign_key_list('.$table.')')->result_array()as$r){if((int)$r['seq']!==0||!in_array($r['on_delete'],['RESTRICT','NO ACTION'],true)||!in_array($r['on_update'],['RESTRICT','NO ACTION'],true))$fail();$refs[$r['from']]=[$r['table'],$r['to']];}ksort($refs);if($refs!==['issuer_id'=>[$p.'issuers_registry','id'],'queue_id'=>[$p.'mail_queue','id']])$fail();return;
        }
        $table=$p.'company_native_file_mail_requests';$status=IssuerStorage::query($db,'SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?',[$table])->row_array();if(strtoupper($status['ENGINE']??'')!=='INNODB')throw new RuntimeException('native_file_mail_schema_invalid');
        $expected=['queue_id'=>'int','issuer_id'=>'int unsigned','request_key'=>'char(64)','request_hash'=>'char(64)','binding_hash'=>'char(64)','write_nonce'=>'char(64)'];$rows=IssuerStorage::query($db,'SHOW FULL COLUMNS FROM '.$table)->result_array();if(array_column($rows,'Field')!==array_keys($expected))throw new RuntimeException('native_file_mail_schema_invalid');foreach($rows as$r){$type=preg_replace('/\bint\([0-9]+\)/','int',strtolower($r['Type']));if(($expected[$r['Field']]??null)!==$type||$r['Null']!=='NO'||$r['Extra']!==''||$r['Default']!==null||$r['Collation']!==($type==='char(64)'?'ascii_bin':null))throw new RuntimeException('native_file_mail_schema_invalid');}
        $indexes=[];foreach(IssuerStorage::query($db,'SHOW INDEX FROM '.$table)->result_array()as$r){if($r['Sub_part']!==null||strtoupper($r['Index_type'])!=='BTREE'||(array_key_exists('Visible',$r)&&$r['Visible']!=='YES')||(array_key_exists('Expression',$r)&&$r['Expression']!==null))throw new RuntimeException('native_file_mail_schema_invalid');$indexes[$r['Key_name']]['unique']=(int)$r['Non_unique'];$indexes[$r['Key_name']]['cols'][(int)$r['Seq_in_index']]=$r['Column_name'];}ksort($indexes);$want=['PRIMARY'=>['unique'=>0,'cols'=>[1=>'queue_id']],'issuer_request'=>['unique'=>0,'cols'=>[1=>'issuer_id',2=>'request_key']]];if($indexes!==$want)throw new RuntimeException('native_file_mail_schema_invalid');
        $rules=IssuerStorage::query($db,'SELECT DELETE_RULE,UPDATE_RULE FROM information_schema.REFERENTIAL_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME=?',[$table])->result_array();if(count($rules)!==2)throw new RuntimeException('native_file_mail_schema_invalid');foreach($rules as$rule)if(!in_array($rule['DELETE_RULE'],['RESTRICT','NO ACTION'],true)||!in_array($rule['UPDATE_RULE'],['RESTRICT','NO ACTION'],true))throw new RuntimeException('native_file_mail_schema_invalid');
        $refs=IssuerStorage::query($db,'SELECT COLUMN_NAME,REFERENCED_TABLE_NAME,REFERENCED_COLUMN_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND REFERENCED_TABLE_NAME IS NOT NULL ORDER BY COLUMN_NAME',[$table])->result_array();if($refs!==[['COLUMN_NAME'=>'issuer_id','REFERENCED_TABLE_NAME'=>$p.'issuers_registry','REFERENCED_COLUMN_NAME'=>'id'],['COLUMN_NAME'=>'queue_id','REFERENCED_TABLE_NAME'=>$p.'mail_queue','REFERENCED_COLUMN_NAME'=>'id']])throw new RuntimeException('native_file_mail_schema_invalid');
    }
}
