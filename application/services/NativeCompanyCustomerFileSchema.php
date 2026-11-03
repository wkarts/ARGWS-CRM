<?php
declare(strict_types=1);
require_once __DIR__.'/issuers/IssuerStorage.php';
final class NativeCompanyCustomerFileSchema
{
    public static function definitions(string$p):array
    {
        if(!preg_match('/^[A-Za-z0-9_]+$/D',$p))throw new InvalidArgumentException('Invalid prefix');
        $fk_file_id="ncf_".substr(hash("sha256",$p."NativeCompanyCustomerFileSchema:file_id"),0,40);
        $fk_issuer_id="ncf_".substr(hash("sha256",$p."NativeCompanyCustomerFileSchema:issuer_id"),0,40);
        $fk_client_id="ncf_".substr(hash("sha256",$p."NativeCompanyCustomerFileSchema:client_id"),0,40);
        return ['company_customer_file_contents'=>"CREATE TABLE IF NOT EXISTS `{$p}company_customer_file_contents` (
            file_id INT NOT NULL, issuer_id INT UNSIGNED NOT NULL, client_id INT NOT NULL,
            request_key CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
            request_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
            content_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
            byte_size INT UNSIGNED NOT NULL, mime VARCHAR(191) NOT NULL, content LONGBLOB NULL,
            revision INT UNSIGNED NOT NULL DEFAULT 1, active TINYINT UNSIGNED NOT NULL DEFAULT 1,
            write_nonce CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
            PRIMARY KEY(file_id), UNIQUE KEY issuer_request(issuer_id,request_key), KEY client_id(client_id),
            CONSTRAINT {$fk_file_id} FOREIGN KEY(file_id) REFERENCES `{$p}files`(id), CONSTRAINT {$fk_issuer_id} FOREIGN KEY(issuer_id) REFERENCES `{$p}issuers_registry`(id), CONSTRAINT {$fk_client_id} FOREIGN KEY(client_id) REFERENCES `{$p}clients`(userid)
        ) ENGINE=InnoDB"];
    }
    public static function ensure($db,string$p):void{foreach(self::definitions($p)as$sql)IssuerStorage::query($db,$sql);self::assertValid($db,$p);}
    public static function assertValid($db,string$p):void
    {
        self::definitions($p);$pdo=$db->pdo??$db->conn_id??null;if($pdo instanceof PDO&&$pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='sqlite')return;
        $expected=['file_id'=>'int','issuer_id'=>'int unsigned','client_id'=>'int','request_key'=>'char(64)','request_hash'=>'char(64)','content_hash'=>'char(64)','byte_size'=>'int unsigned','mime'=>'varchar(191)','content'=>'longblob','revision'=>'int unsigned','active'=>'tinyint unsigned','write_nonce'=>'char(64)'];
        $rows=IssuerStorage::query($db,'SELECT COLUMN_NAME,COLUMN_TYPE,IS_NULLABLE,COLUMN_DEFAULT,COLLATION_NAME,EXTRA FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? ORDER BY ORDINAL_POSITION',[$p.'company_customer_file_contents'])->result_array();
        if(array_column($rows,'COLUMN_NAME')!==array_keys($expected))throw new RuntimeException('native_customer_file_schema_invalid');
        foreach($rows as$r)if(preg_replace('/\b(tinyint|int)\([0-9]+\)/','$1',strtolower($r['COLUMN_TYPE']))!==$expected[$r['COLUMN_NAME']]||$r['IS_NULLABLE']!==($r['COLUMN_NAME']==='content'?'YES':'NO')||!empty($r['EXTRA']))throw new RuntimeException('native_customer_file_schema_invalid');
        foreach($rows as$r){if(in_array($r['COLUMN_NAME'],['revision','active'],true)){if((string)$r['COLUMN_DEFAULT']!=='1')throw new RuntimeException('native_customer_file_schema_invalid');}elseif(!IssuerStorage::sqlNullDefault($r))throw new RuntimeException('native_customer_file_schema_invalid');if(in_array($r['COLUMN_NAME'],['request_key','request_hash','content_hash','write_nonce'],true)&&$r['COLLATION_NAME']!=='ascii_bin')throw new RuntimeException('native_customer_file_schema_invalid');}
        $row=IssuerStorage::query($db,'SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?',[$p.'company_customer_file_contents'])->row_array();if(strtoupper($row['ENGINE']??'')!=='INNODB')throw new RuntimeException('native_customer_file_schema_invalid');
        $indexes=[];foreach(IssuerStorage::query($db,'SHOW INDEX FROM '.$p.'company_customer_file_contents')->result_array()as$r){if($r['Sub_part']!==null)throw new RuntimeException('native_customer_file_schema_invalid');$indexes[$r['Key_name']]['unique']=(int)$r['Non_unique']===0;$indexes[$r['Key_name']]['columns'][(int)$r['Seq_in_index']]=$r['Column_name'];}
        if(count($indexes)!==3)throw new RuntimeException('native_customer_file_schema_invalid');
        foreach(['PRIMARY'=>[true,['file_id']],'issuer_request'=>[true,['issuer_id','request_key']],'client_id'=>[false,['client_id']]]as$key=>[$unique,$columns]){$actual=$indexes[$key]??[];$cols=$actual['columns']??[];ksort($cols);if(($actual['unique']??null)!==$unique||array_values($cols)!==$columns)throw new RuntimeException('native_customer_file_schema_invalid');}
        $fks=IssuerStorage::query($db,'SELECT COLUMN_NAME,REFERENCED_TABLE_NAME,REFERENCED_COLUMN_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND REFERENCED_TABLE_NAME IS NOT NULL',[$p.'company_customer_file_contents'])->result_array();$actual=[];if(count($fks)!==3)throw new RuntimeException('native_customer_file_schema_invalid');foreach($fks as$r)$actual[$r['COLUMN_NAME']]=[$r['REFERENCED_TABLE_NAME'],$r['REFERENCED_COLUMN_NAME']];$wanted=['file_id'=>[$p.'files','id'],'issuer_id'=>[$p.'issuers_registry','id'],'client_id'=>[$p.'clients','userid']];ksort($actual);ksort($wanted);if($actual!==$wanted)throw new RuntimeException('native_customer_file_schema_invalid');
        $rules=IssuerStorage::query($db,'SELECT UPDATE_RULE,DELETE_RULE FROM information_schema.REFERENTIAL_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME=?',[$p.'company_customer_file_contents'])->result_array();if(count($rules)!==3)throw new RuntimeException('native_customer_file_schema_invalid');foreach($rules as$r)if(!in_array($r['UPDATE_RULE'],['RESTRICT','NO ACTION'],true)||!in_array($r['DELETE_RULE'],['RESTRICT','NO ACTION'],true))throw new RuntimeException('native_customer_file_schema_invalid');
    }
}
