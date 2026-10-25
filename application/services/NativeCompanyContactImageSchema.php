<?php
declare(strict_types=1);
require_once __DIR__.'/issuers/IssuerStorage.php';
final class NativeCompanyContactImageSchema
{
    public const FIELDS=['contact_id'=>'int','issuer_id'=>'int unsigned','client_id'=>'int','revision'=>'int unsigned','mime'=>'varchar(32)','byte_size'=>'int unsigned','sha256'=>'char(64)','content'=>'mediumblob','updated_by'=>'int','updated_at'=>'datetime'];
    public static function ensure($db,string $p):void
    {
        self::prefix($p);IssuerStorage::query($db,"CREATE TABLE IF NOT EXISTS {$p}company_native_contact_images(contact_id INT NOT NULL,issuer_id INT UNSIGNED NOT NULL,client_id INT NOT NULL,revision INT UNSIGNED NOT NULL,mime VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,byte_size INT UNSIGNED NOT NULL,sha256 CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,content MEDIUMBLOB NOT NULL,updated_by INT NOT NULL,updated_at DATETIME NOT NULL,PRIMARY KEY(contact_id),KEY owner_client(issuer_id,client_id),FOREIGN KEY(issuer_id) REFERENCES {$p}issuers_registry(id),FOREIGN KEY(contact_id) REFERENCES {$p}contacts(id)) ENGINE=InnoDB");if(property_exists($db,'data_cache'))unset($db->data_cache['table_names']);self::assertValid($db,$p);
    }
    private static function prefix(string $p):void{if(!preg_match('/^[A-Za-z0-9_]+$/D',$p))throw new InvalidArgumentException('Invalid prefix');}
    public static function assertValid($db,string $p):void
    {
        self::prefix($p);$pdo=$db->pdo??$db->conn_id??null;
        if($pdo instanceof PDO&&$pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='sqlite'){
            $rows=IssuerStorage::query($db,'PRAGMA table_info('.$p.'company_native_contact_images)')->result_array();if(array_column($rows,'name')!==array_keys(self::FIELDS)||array_column($rows,'pk')!==[1,0,0,0,0,0,0,0,0,0])throw new RuntimeException('issuer_contact_image_schema_invalid');return;
        }
        $rows=IssuerStorage::query($db,'SELECT COLUMN_NAME,COLUMN_TYPE,IS_NULLABLE,COLUMN_DEFAULT,EXTRA,COLLATION_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? ORDER BY ORDINAL_POSITION',[$p.'company_native_contact_images'])->result_array();if(array_column($rows,'COLUMN_NAME')!==array_keys(self::FIELDS))throw new RuntimeException('issuer_contact_image_schema_invalid');
        foreach($rows as$row)if(preg_replace('/\bint\([0-9]+\)/','int',strtolower($row['COLUMN_TYPE']))!==self::FIELDS[$row['COLUMN_NAME']]||$row['IS_NULLABLE']!=='NO'||!IssuerStorage::sqlNullDefault($row)||!empty($row['EXTRA'])||(in_array($row['COLUMN_NAME'],['mime','sha256'],true)&&$row['COLLATION_NAME']!=='ascii_bin'))throw new RuntimeException('issuer_contact_image_schema_invalid');
        $engine=IssuerStorage::query($db,'SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?',[$p.'company_native_contact_images'])->row_array();if(strtoupper($engine['ENGINE']??'')!=='INNODB')throw new RuntimeException('issuer_contact_image_schema_invalid');
        $indexes=[];foreach(IssuerStorage::query($db,'SHOW INDEX FROM '.$p.'company_native_contact_images')->result_array()as$row){if($row['Sub_part']!==null)throw new RuntimeException('issuer_contact_image_schema_invalid');$indexes[$row['Key_name']][(int)$row['Seq_in_index']]=$row['Column_name'];if($row['Key_name']==='PRIMARY'&&(int)$row['Non_unique']!==0)throw new RuntimeException('issuer_contact_image_schema_invalid');}
        foreach(['PRIMARY'=>['contact_id'],'owner_client'=>['issuer_id','client_id']]as$key=>$cols){$actual=$indexes[$key]??[];ksort($actual);if(array_values($actual)!==$cols)throw new RuntimeException('issuer_contact_image_schema_invalid');}
        foreach(['issuer_id'=>[$p.'issuers_registry','id'],'contact_id'=>[$p.'contacts','id']]as$field=>$target){$fks=IssuerStorage::query($db,'SELECT REFERENCED_TABLE_NAME,REFERENCED_COLUMN_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=? AND REFERENCED_TABLE_SCHEMA=DATABASE()',[$p.'company_native_contact_images',$field])->result_array();if(count($fks)!==1||[$fks[0]['REFERENCED_TABLE_NAME'],$fks[0]['REFERENCED_COLUMN_NAME']]!==$target)throw new RuntimeException('issuer_contact_image_schema_invalid');}
    }
}
