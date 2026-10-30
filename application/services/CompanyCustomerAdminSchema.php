<?php
declare(strict_types=1);
require_once __DIR__.'/issuers/IssuerStorage.php';
/** Explicit per-customer assignment; never grants a company-wide capability. */
final class CompanyCustomerAdminSchema
{
    public static function ensure($db,string$p):void
    {
        if(!preg_match('/^[A-Za-z0-9_]+$/D',$p))throw new InvalidArgumentException('Invalid prefix');$c=$db->pdo??$db->conn_id??null;$sqlite=$c instanceof PDO&&$c->getAttribute(PDO::ATTR_DRIVER_NAME)==='sqlite';
        $sql=$sqlite?"CREATE TABLE IF NOT EXISTS {$p}company_customer_admins (issuer_id INTEGER NOT NULL,client_id INTEGER NOT NULL,staff_id INTEGER NOT NULL,date_assigned TEXT NOT NULL,PRIMARY KEY(issuer_id,client_id,staff_id),FOREIGN KEY(issuer_id) REFERENCES {$p}issuers_registry(id),FOREIGN KEY(client_id) REFERENCES {$p}clients(userid),FOREIGN KEY(staff_id) REFERENCES {$p}staff(staffid))":"CREATE TABLE IF NOT EXISTS {$p}company_customer_admins (issuer_id INT UNSIGNED NOT NULL,client_id INT NOT NULL,staff_id INT NOT NULL,date_assigned VARCHAR(19) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,PRIMARY KEY(issuer_id,client_id,staff_id),FOREIGN KEY(issuer_id) REFERENCES {$p}issuers_registry(id),FOREIGN KEY(client_id) REFERENCES {$p}clients(userid),FOREIGN KEY(staff_id) REFERENCES {$p}staff(staffid)) ENGINE=InnoDB";
        IssuerStorage::query($db,$sql);if(property_exists($db,'data_cache'))unset($db->data_cache['table_names']);self::assertValid($db,$p);
    }
    public static function assertValid($db,string$p):void
    {
        if(!preg_match('/^[A-Za-z0-9_]+$/D',$p))throw new InvalidArgumentException('Invalid prefix');$c=$db->pdo??$db->conn_id??null;
        if($c instanceof PDO&&$c->getAttribute(PDO::ATTR_DRIVER_NAME)==='sqlite'){
            $fields=IssuerStorage::query($db,'PRAGMA table_info('.$p.'company_customer_admins)')->result_array();if(array_column($fields,'name')!==['issuer_id','client_id','staff_id','date_assigned']||array_map('intval',array_column($fields,'notnull'))!==[1,1,1,1]||array_map('intval',array_column($fields,'pk'))!==[1,2,3,0])throw new RuntimeException('issuer_customer_admin_schema_invalid');
            $fk=IssuerStorage::query($db,'PRAGMA foreign_key_list('.$p.'company_customer_admins)')->result_array();$actual=[];foreach($fk as$r){if(!in_array($r['on_delete'],['RESTRICT','NO ACTION'],true)||!in_array($r['on_update'],['RESTRICT','NO ACTION'],true))throw new RuntimeException('issuer_customer_admin_schema_invalid');$actual[$r['from']]=[$r['table'],$r['to']];}
        }else{
            $fields=IssuerStorage::query($db,'SHOW FULL COLUMNS FROM '.$p.'company_customer_admins')->result_array();$expected=['issuer_id'=>'int unsigned','client_id'=>'int','staff_id'=>'int','date_assigned'=>'varchar(19)'];if(array_column($fields,'Field')!==array_keys($expected))throw new RuntimeException('issuer_customer_admin_schema_invalid');foreach($fields as$r)if(preg_replace('/\bint\(\d+\)/','int',strtolower($r['Type']))!==$expected[$r['Field']]||$r['Null']!=='NO'||$r['Default']!==null||$r['Extra']!==''||$r['Collation']!==($r['Field']==='date_assigned'?'ascii_bin':null))throw new RuntimeException('issuer_customer_admin_schema_invalid');
            $engine=IssuerStorage::query($db,'SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?',[$p.'company_customer_admins'])->row_array();$index=IssuerStorage::query($db,'SHOW INDEX FROM '.$p.'company_customer_admins')->result_array();$pk=[];foreach($index as$r)if($r['Key_name']==='PRIMARY'){$pk[(int)$r['Seq_in_index']]=$r['Column_name'];if((int)$r['Non_unique']!==0||$r['Sub_part']!==null)throw new RuntimeException('issuer_customer_admin_schema_invalid');}ksort($pk);if(strtoupper($engine['ENGINE']??'')!=='INNODB'||array_values($pk)!==['issuer_id','client_id','staff_id'])throw new RuntimeException('issuer_customer_admin_schema_invalid');
            $fk=IssuerStorage::query($db,'SELECT k.COLUMN_NAME,k.REFERENCED_TABLE_NAME,k.REFERENCED_COLUMN_NAME,r.DELETE_RULE,r.UPDATE_RULE FROM information_schema.KEY_COLUMN_USAGE k JOIN information_schema.REFERENTIAL_CONSTRAINTS r ON r.CONSTRAINT_SCHEMA=k.CONSTRAINT_SCHEMA AND r.CONSTRAINT_NAME=k.CONSTRAINT_NAME AND r.TABLE_NAME=k.TABLE_NAME WHERE k.TABLE_SCHEMA=DATABASE() AND k.TABLE_NAME=? AND k.REFERENCED_TABLE_NAME IS NOT NULL',[$p.'company_customer_admins'])->result_array();$actual=[];foreach($fk as$r){if(!in_array($r['DELETE_RULE'],['RESTRICT','NO ACTION'],true)||!in_array($r['UPDATE_RULE'],['RESTRICT','NO ACTION'],true))throw new RuntimeException('issuer_customer_admin_schema_invalid');$actual[$r['COLUMN_NAME']]=[$r['REFERENCED_TABLE_NAME'],$r['REFERENCED_COLUMN_NAME']];}
        }
        $expected=['issuer_id'=>[$p.'issuers_registry','id'],'client_id'=>[$p.'clients','userid'],'staff_id'=>[$p.'staff','staffid']];ksort($actual);ksort($expected);if($actual!==$expected)throw new RuntimeException('issuer_customer_admin_schema_invalid');
    }
}
