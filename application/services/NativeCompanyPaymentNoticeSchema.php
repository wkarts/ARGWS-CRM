<?php
declare(strict_types=1);
require_once __DIR__.'/issuers/IssuerStorage.php';
require_once __DIR__.'/issuers/IssuerTransaction.php';
/** Durable intent only. It does not mean that an email or SMS was queued or delivered. */
final class NativeCompanyPaymentNoticeSchema
{
    public const TABLE='company_payment_notice_intents';
    public static function ensure($db,string $p):void
    {
        if(!preg_match('/^[A-Za-z0-9_]+$/D',$p))throw new InvalidArgumentException('Invalid prefix');
        IssuerTransaction::assertIndependent($db);
        IssuerStorage::query($db,"CREATE TABLE IF NOT EXISTS `{$p}".self::TABLE."` (request_key CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL, issuer_id INT UNSIGNED NOT NULL, actor_id INT NOT NULL, invoice_id INT NOT NULL, payment_id INT NOT NULL, email_requested TINYINT NOT NULL, sms_requested TINYINT NOT NULL, email_status VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL, sms_status VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL, created_at DATETIME NOT NULL, PRIMARY KEY(request_key), UNIQUE KEY payment_notice(payment_id), KEY issuer_invoice(issuer_id,invoice_id), FOREIGN KEY(issuer_id) REFERENCES `{$p}issuers_registry`(id), FOREIGN KEY(invoice_id) REFERENCES `{$p}invoices`(id), FOREIGN KEY(payment_id) REFERENCES `{$p}invoicepaymentrecords`(id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin");
        // Delivery proof survives removal of a queued message and prevents re-queueing it.
        IssuerStorage::query($db,"CREATE TABLE IF NOT EXISTS `{$p}company_payment_notice_deliveries` (queue_id INT NOT NULL, issuer_id INT UNSIGNED NOT NULL, notice_key CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL, delivery_key CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL, contact_id INT NOT NULL, PRIMARY KEY(queue_id), UNIQUE KEY delivery_key(delivery_key), UNIQUE KEY notice_contact(notice_key,contact_id), FOREIGN KEY(issuer_id) REFERENCES `{$p}issuers_registry`(id), FOREIGN KEY(notice_key) REFERENCES `{$p}company_payment_notice_intents`(request_key)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin");
        if(property_exists($db,'data_cache'))unset($db->data_cache['table_names']);self::assertValid($db,$p);
    }
    public static function assertValid($db,string $p):void
    {
        if(!preg_match('/^[A-Za-z0-9_]+$/D',$p)||!$db->table_exists($p.self::TABLE))throw new RuntimeException('native_payment_notice_schema_invalid');
        $pdo=$db->pdo??$db->conn_id??null;$sqlite=$pdo instanceof PDO&&$pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='sqlite';
        $columns=['request_key','issuer_id','actor_id','invoice_id','payment_id','email_requested','sms_requested','email_status','sms_status','created_at'];
        if($sqlite){$rows=IssuerStorage::query($db,'PRAGMA table_info(`'.$p.self::TABLE.'`)')->result_array();$delivery=IssuerStorage::query($db,'PRAGMA table_info(`'.$p.'company_payment_notice_deliveries`)')->result_array();if(array_column($rows,'name')!==$columns||array_column($delivery,'name')!==['queue_id','issuer_id','notice_key','delivery_key','contact_id'])throw new RuntimeException('native_payment_notice_schema_invalid');return;}
        $rows=IssuerStorage::query($db,'SHOW FULL COLUMNS FROM `'.$p.self::TABLE.'`')->result_array();
        $types=['char(64)','int unsigned','int','int','int','tinyint','tinyint','varchar(16)','varchar(16)','datetime'];
        if(array_column($rows,'Field')!==$columns)throw new RuntimeException('native_payment_notice_schema_invalid');
        foreach($rows as$n=>$row){$type=preg_replace('/\b(tinyint|int)\([0-9]+\)/','$1',strtolower($row['Type']));if($type!==$types[$n]||$row['Null']!=='NO'||$row['Default']!==null||$row['Extra']!==''||$row['Collation']!==(in_array($n,[0,7,8],true)?'ascii_bin':null))throw new RuntimeException('native_payment_notice_schema_invalid');}
        $engine=IssuerStorage::query($db,'SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?',[$p.self::TABLE])->row_array();if(strtoupper($engine['ENGINE']??'')!=='INNODB')throw new RuntimeException('native_payment_notice_schema_invalid');
        $keys=[];foreach(IssuerStorage::query($db,'SHOW INDEX FROM `'.$p.self::TABLE.'`')->result_array() as $row){if($row['Sub_part']!==null)throw new RuntimeException('native_payment_notice_schema_invalid');$keys[$row['Key_name']]['unique']=(int)$row['Non_unique']===0;$keys[$row['Key_name']]['columns'][(int)$row['Seq_in_index']]=$row['Column_name'];}
        foreach(['PRIMARY'=>[true,['request_key']],'payment_notice'=>[true,['payment_id']],'issuer_invoice'=>[false,['issuer_id','invoice_id']]] as $name=>[$unique,$want]){$actual=$keys[$name]??[];$fields=$actual['columns']??[];ksort($fields);if(($actual['unique']??null)!==$unique||array_values($fields)!==$want)throw new RuntimeException('native_payment_notice_schema_invalid');}
        $references=IssuerStorage::query($db,'SELECT COLUMN_NAME,REFERENCED_TABLE_NAME,REFERENCED_COLUMN_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND REFERENCED_TABLE_NAME IS NOT NULL',[$p.self::TABLE])->result_array();$actual=[];foreach($references as $row)$actual[$row['COLUMN_NAME']]=[$row['REFERENCED_TABLE_NAME'],$row['REFERENCED_COLUMN_NAME']];
        $want=['issuer_id'=>[$p.'issuers_registry','id'],'invoice_id'=>[$p.'invoices','id'],'payment_id'=>[$p.'invoicepaymentrecords','id']];ksort($actual);ksort($want);if($actual!==$want)throw new RuntimeException('native_payment_notice_schema_invalid');
        $delivery=$p.'company_payment_notice_deliveries';if(!$db->table_exists($delivery))throw new RuntimeException('native_payment_notice_schema_invalid');
        $rows=IssuerStorage::query($db,'SHOW FULL COLUMNS FROM `'.$delivery.'`')->result_array();$fields=['queue_id','issuer_id','notice_key','delivery_key','contact_id'];$types=['int','int unsigned','char(64)','char(64)','int'];if(array_column($rows,'Field')!==$fields)throw new RuntimeException('native_payment_notice_schema_invalid');
        foreach($rows as$n=>$row){$type=preg_replace('/\bint\([0-9]+\)/','int',strtolower($row['Type']));if($type!==$types[$n]||$row['Null']!=='NO'||$row['Default']!==null||$row['Extra']!==''||$row['Collation']!==(in_array($n,[2,3],true)?'ascii_bin':null))throw new RuntimeException('native_payment_notice_schema_invalid');}
        $engine=IssuerStorage::query($db,'SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?',[$delivery])->row_array();if(strtoupper($engine['ENGINE']??'')!=='INNODB')throw new RuntimeException('native_payment_notice_schema_invalid');
        $keys=[];foreach(IssuerStorage::query($db,'SHOW INDEX FROM `'.$delivery.'`')->result_array()as$row){if($row['Sub_part']!==null)throw new RuntimeException('native_payment_notice_schema_invalid');$keys[$row['Key_name']]['unique']=(int)$row['Non_unique']===0;$keys[$row['Key_name']]['columns'][(int)$row['Seq_in_index']]=$row['Column_name'];}
        foreach(['PRIMARY'=>['queue_id'],'delivery_key'=>['delivery_key'],'notice_contact'=>['notice_key','contact_id']]as$name=>$want){$index=$keys[$name]??[];$cols=$index['columns']??[];ksort($cols);if(empty($index['unique'])||array_values($cols)!==$want)throw new RuntimeException('native_payment_notice_schema_invalid');}
        $references=IssuerStorage::query($db,'SELECT COLUMN_NAME,REFERENCED_TABLE_NAME,REFERENCED_COLUMN_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND REFERENCED_TABLE_NAME IS NOT NULL',[$delivery])->result_array();$actual=[];foreach($references as$row)$actual[$row['COLUMN_NAME']]=[$row['REFERENCED_TABLE_NAME'],$row['REFERENCED_COLUMN_NAME']];$want=['issuer_id'=>[$p.'issuers_registry','id'],'notice_key'=>[$p.self::TABLE,'request_key']];ksort($actual);ksort($want);if($actual!==$want)throw new RuntimeException('native_payment_notice_schema_invalid');
    }
}
