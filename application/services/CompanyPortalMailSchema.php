<?php

declare(strict_types=1);
require_once __DIR__.'/issuers/IssuerStorage.php';

/** Explicit portal transport opt-in and immutable recovery queue receipts, without changing existing tokens. */
final class CompanyPortalMailSchema
{
    public static function definitions():array{return[
        'company_portal_mail_preferences'=>['issuer_id'=>'int unsigned','mode'=>'varchar(16)','revision'=>'int unsigned','saved_by'=>'int','created_at'=>'datetime','updated_at'=>'datetime'],
        'company_portal_mail_deliveries'=>['delivery_key'=>'varchar(64)','issuer_id'=>'int unsigned','queue_id'=>'int','contact_id'=>'int','client_id'=>'int','token_hash'=>'varchar(64)','identity_hash'=>'varchar(64)','recipient_hash'=>'varchar(64)','transport_kind'=>'varchar(16)','transport_epoch'=>'int unsigned','configuration_hash'=>'varchar(64)','payload_hash'=>'varchar(64)','expires_at'=>'datetime','created_at'=>'datetime'],
    ];}
    private static function collation(string $key,string $type):?string{return str_starts_with($type,'varchar')?(str_ends_with($key,'_hash')||$key==='delivery_key'?'ascii_bin':'utf8mb4_bin'):null;}
    public static function present($db,string$p):bool{$any=false;foreach(array_keys(self::definitions())as$table)if($db->table_exists($p.$table))$any=true;if($any)self::assertValid($db,$p);return$any;}
    public static function ensure($db,string$p):void
    {
        if(!preg_match('/^[A-Za-z0-9_]+$/D',$p))throw new InvalidArgumentException('Invalid prefix');
        foreach(self::definitions()as$table=>$fields){$parts=[];foreach($fields as$key=>$type){$coll=self::collation($key,$type);$parts[]='`'.$key.'` '.$type.($coll?' CHARACTER SET '.($coll==='ascii_bin'?'ascii':'utf8mb4').' COLLATE '.$coll:'').' NOT NULL';}$parts[]='PRIMARY KEY (`'.array_key_first($fields).'`)';$parts[]='FOREIGN KEY (`issuer_id`) REFERENCES `'.$p.'issuers_registry` (`id`)';if($table==='company_portal_mail_deliveries')$parts[]='UNIQUE KEY `portal_mail_queue` (`queue_id`)';IssuerStorage::query($db,'CREATE TABLE IF NOT EXISTS `'.$p.$table.'` ('.implode(',',$parts).') ENGINE=InnoDB');}self::assertValid($db,$p);
    }
    public static function assertValid($db,string$p):void
    {
        if(!preg_match('/^[A-Za-z0-9_]+$/D',$p))throw new InvalidArgumentException('Invalid prefix');$connection=$db->pdo??$db->conn_id??null;if($connection instanceof PDO&&$connection->getAttribute(PDO::ATTR_DRIVER_NAME)==='sqlite')return;
        foreach(self::definitions()as$table=>$fields){$cols=[];foreach(IssuerStorage::query($db,'SELECT COLUMN_NAME,COLUMN_TYPE,IS_NULLABLE,COLUMN_DEFAULT,COLLATION_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?',[$p.$table])->result_array()as$row)$cols[$row['COLUMN_NAME']]=$row;
            foreach($fields as$key=>$type){$row=$cols[$key]??[];$actual=preg_replace('/^int\([0-9]+\)/','int',strtolower($row['COLUMN_TYPE']??''));$coll=self::collation($key,$type);if($actual!==$type||($row['IS_NULLABLE']??'')!=='NO'||!IssuerStorage::sqlNullDefault($row)||($coll!==null&&($row['COLLATION_NAME']??'')!==$coll))throw new RuntimeException('issuer_portal_mail_schema_invalid');}
            $engine=IssuerStorage::query($db,'SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?',[$p.$table])->row()->ENGINE??'';if(strtoupper($engine)!=='INNODB')throw new RuntimeException('issuer_portal_mail_schema_invalid');
            foreach(['PRIMARY'=>[array_key_first($fields)]]+($table==='company_portal_mail_deliveries'?['portal_mail_queue'=>['queue_id']]:[])as$index=>$keys){$rows=IssuerStorage::query($db,'SELECT COLUMN_NAME,NON_UNIQUE,SUB_PART FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND INDEX_NAME=? ORDER BY SEQ_IN_INDEX',[$p.$table,$index])->result_array();if(array_column($rows,'COLUMN_NAME')!==$keys||array_filter($rows,fn($r)=>(int)$r['NON_UNIQUE']!==0||$r['SUB_PART']!==null))throw new RuntimeException('issuer_portal_mail_schema_invalid');}
            $fks=IssuerStorage::query($db,'SELECT REFERENCED_TABLE_NAME,REFERENCED_COLUMN_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=? AND REFERENCED_TABLE_SCHEMA=DATABASE()',[$p.$table,'issuer_id'])->result_array();if(count($fks)!==1||$fks[0]['REFERENCED_TABLE_NAME']!==$p.'issuers_registry'||$fks[0]['REFERENCED_COLUMN_NAME']!=='id')throw new RuntimeException('issuer_portal_mail_schema_invalid');
        }
    }
}
