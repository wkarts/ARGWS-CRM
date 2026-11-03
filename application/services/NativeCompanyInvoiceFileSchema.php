<?php
declare(strict_types=1);
require_once __DIR__.'/issuers/IssuerStorage.php';
/** Real files.id projection. Private content remains in CompanyInvoiceAttachments. */
final class NativeCompanyInvoiceFileSchema
{
    public static function definitions(string $p):array
    {
        if(!preg_match('/^[A-Za-z0-9_]+$/D',$p))throw new InvalidArgumentException('Invalid prefix');
        return ['company_invoice_file_projection'=>"CREATE TABLE IF NOT EXISTS `{$p}company_invoice_file_projection` (
          file_id INT NOT NULL, issuer_id INT UNSIGNED NOT NULL, invoice_id INT NOT NULL,
          source_kind VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
          file_key VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NULL,
          request_key VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
          request_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
          revision INT UNSIGNED NOT NULL DEFAULT 1, active TINYINT UNSIGNED NOT NULL DEFAULT 1,
          write_nonce CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
          PRIMARY KEY (file_id), UNIQUE KEY `source_key` (file_key), UNIQUE KEY `request_key` (issuer_id,request_key), KEY `invoice_owner` (issuer_id,invoice_id),
          FOREIGN KEY(file_id) REFERENCES `{$p}files`(id), FOREIGN KEY(issuer_id) REFERENCES `{$p}issuers_registry`(id),
          FOREIGN KEY(invoice_id) REFERENCES `{$p}invoices`(id), FOREIGN KEY(file_key) REFERENCES `{$p}company_invoice_attachments`(file_key)
        ) ENGINE=InnoDB"];
    }
    public static function ensure($db,string $p):void{foreach(self::definitions($p)as$sql)IssuerStorage::query($db,$sql);self::assertValid($db,$p);}
    public static function assertValid($db,string $p):void
    {
        self::definitions($p);$pdo=$db->pdo??$db->conn_id??null;if($pdo instanceof PDO&&$pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='sqlite')return;
        $expected=['file_id'=>'int','issuer_id'=>'int unsigned','invoice_id'=>'int','source_kind'=>'varchar(16)','file_key'=>'varchar(64)','request_key'=>'varchar(64)','request_hash'=>'char(64)','revision'=>'int unsigned','active'=>'tinyint unsigned','write_nonce'=>'char(64)'];
        $rows=IssuerStorage::query($db,'SELECT COLUMN_NAME,COLUMN_TYPE,IS_NULLABLE,EXTRA,COLLATION_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? ORDER BY ORDINAL_POSITION',[$p.'company_invoice_file_projection'])->result_array();
        if(array_column($rows,'COLUMN_NAME')!==array_keys($expected))throw new RuntimeException('native_invoice_file_schema_invalid');
        foreach($rows as$r){if(preg_replace('/\b(tinyint|int)\([0-9]+\)/','$1',strtolower($r['COLUMN_TYPE']))!==$expected[$r['COLUMN_NAME']]||$r['IS_NULLABLE']!==($r['COLUMN_NAME']==='file_key'?'YES':'NO')||!empty($r['EXTRA']))throw new RuntimeException('native_invoice_file_schema_invalid');if(in_array($r['COLUMN_NAME'],['source_kind','file_key','request_key','request_hash','write_nonce'],true)&&$r['COLLATION_NAME']!=='ascii_bin')throw new RuntimeException('native_invoice_file_schema_invalid');}
        $row=IssuerStorage::query($db,'SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?',[$p.'company_invoice_file_projection'])->row_array();if(strtoupper($row['ENGINE']??'')!=='INNODB')throw new RuntimeException('native_invoice_file_schema_invalid');
        $indexes=[];foreach(IssuerStorage::query($db,'SHOW INDEX FROM '.$p.'company_invoice_file_projection')->result_array()as$r){if($r['Sub_part']!==null)throw new RuntimeException('native_invoice_file_schema_invalid');$indexes[$r['Key_name']]['unique']=(int)$r['Non_unique']===0;$indexes[$r['Key_name']]['columns'][(int)$r['Seq_in_index']]=$r['Column_name'];}
        foreach(['PRIMARY'=>[true,['file_id']],'source_key'=>[true,['file_key']],'request_key'=>[true,['issuer_id','request_key']],'invoice_owner'=>[false,['issuer_id','invoice_id']]]as$key=>[$unique,$cols]){$actual=$indexes[$key]??[];$c=$actual['columns']??[];ksort($c);if(($actual['unique']??null)!==$unique||array_values($c)!==$cols)throw new RuntimeException('native_invoice_file_schema_invalid');}
        $fks=IssuerStorage::query($db,'SELECT COLUMN_NAME,REFERENCED_TABLE_NAME,REFERENCED_COLUMN_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND REFERENCED_TABLE_NAME IS NOT NULL',[$p.'company_invoice_file_projection'])->result_array();$actual=[];foreach($fks as$r)$actual[$r['COLUMN_NAME']]=[$r['REFERENCED_TABLE_NAME'],$r['REFERENCED_COLUMN_NAME']];$want=['file_id'=>[$p.'files','id'],'issuer_id'=>[$p.'issuers_registry','id'],'invoice_id'=>[$p.'invoices','id'],'file_key'=>[$p.'company_invoice_attachments','file_key']];ksort($actual);ksort($want);if($actual!==$want)throw new RuntimeException('native_invoice_file_schema_invalid');
    }
}
