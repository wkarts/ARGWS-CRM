<?php
declare(strict_types=1);
require_once __DIR__.'/issuers/IssuerStorage.php';
final class CompanyClientProfileSchema
{
    public static function ready($db,string $p):bool{return $db->table_exists($p.'company_client_profiles');}
    public static function ensure($db,string $p):void
    {
        if(!preg_match('/^[A-Za-z0-9_]+$/D',$p))throw new InvalidArgumentException('Invalid prefix');
        IssuerStorage::query($db,"CREATE TABLE IF NOT EXISTS {$p}company_client_profiles (issuer_id int unsigned NOT NULL,client_id int NOT NULL,profile_json longtext NOT NULL,PRIMARY KEY(issuer_id,client_id),FOREIGN KEY(issuer_id) REFERENCES {$p}issuers_registry(id),FOREIGN KEY(client_id) REFERENCES {$p}clients(userid) ON DELETE CASCADE) ENGINE=InnoDB");
        if(property_exists($db,'data_cache'))unset($db->data_cache['table_names']);
        self::assertValid($db,$p);
    }
    public static function assertValid($db,string $p):void
    {
        if(!preg_match('/^[A-Za-z0-9_]+$/D',$p))throw new InvalidArgumentException('Invalid prefix');
        $conn=$db->pdo??$db->conn_id??null;if($conn instanceof PDO&&$conn->getAttribute(PDO::ATTR_DRIVER_NAME)==='sqlite'){
            $fields=IssuerStorage::query($db,'PRAGMA table_info('.$p.'company_client_profiles)')->result_array();
            if(array_column($fields,'name')!==['issuer_id','client_id','profile_json']||array_column($fields,'pk')!==[1,2,0]||array_column($fields,'notnull')!==[1,1,1])throw new RuntimeException('company_client_profile_schema_invalid');return;
        }
        $columns=[];foreach(IssuerStorage::query($db,'SELECT COLUMN_NAME,COLUMN_TYPE,IS_NULLABLE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?',[$p.'company_client_profiles'])->result_array() as $row)$columns[$row['COLUMN_NAME']]=$row;
        foreach(['issuer_id'=>'int unsigned','client_id'=>'int','profile_json'=>'longtext'] as $key=>$type){$r=$columns[$key]??[];if(preg_replace('/^int\(\d+\)/','int',strtolower($r['COLUMN_TYPE']??''))!==$type||($r['IS_NULLABLE']??'')!=='NO')throw new RuntimeException('company_client_profile_schema_invalid');}
        $pk=IssuerStorage::query($db,"SELECT COLUMN_NAME FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND INDEX_NAME='PRIMARY' ORDER BY SEQ_IN_INDEX",[$p.'company_client_profiles'])->result_array();
        $engine=IssuerStorage::query($db,'SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?',[$p.'company_client_profiles'])->result_array();
        if(array_column($pk,'COLUMN_NAME')!==['issuer_id','client_id']||strtoupper($engine[0]['ENGINE']??'')!=='INNODB')throw new RuntimeException('company_client_profile_schema_invalid');
        foreach(['issuer_id'=>[$p.'issuers_registry','id'],'client_id'=>[$p.'clients','userid']] as $column=>$target){$fk=IssuerStorage::query($db,'SELECT CONSTRAINT_NAME,REFERENCED_TABLE_NAME,REFERENCED_COLUMN_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=? AND REFERENCED_TABLE_SCHEMA=DATABASE()',[$p.'company_client_profiles',$column])->result_array();if(count($fk)!==1||$fk[0]['REFERENCED_TABLE_NAME']!==$target[0]||$fk[0]['REFERENCED_COLUMN_NAME']!==$target[1])throw new RuntimeException('company_client_profile_schema_invalid');if($column==='client_id'){$rule=IssuerStorage::query($db,'SELECT DELETE_RULE FROM information_schema.REFERENTIAL_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME=? AND CONSTRAINT_NAME=?',[$p.'company_client_profiles',$fk[0]['CONSTRAINT_NAME']])->result_array()[0]??[];if(($rule['DELETE_RULE']??'')!=='CASCADE')throw new RuntimeException('company_client_profile_schema_invalid');}}
    }
}
