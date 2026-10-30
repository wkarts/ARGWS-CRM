<?php
declare(strict_types=1);
require_once __DIR__.'/issuers/IssuerStorage.php';
final class CompanyCustomerGroupsSchema
{
    public static function ensure($db,string $p):void
    {
        if(!preg_match('/^[A-Za-z0-9_]+$/D',$p))throw new InvalidArgumentException('Invalid prefix');
        IssuerStorage::query($db,"CREATE TABLE IF NOT EXISTS {$p}company_customer_group_owners (group_id int NOT NULL,issuer_id int unsigned NOT NULL,PRIMARY KEY(group_id),KEY issuer_id(issuer_id),FOREIGN KEY(group_id) REFERENCES {$p}customers_groups(id),FOREIGN KEY(issuer_id) REFERENCES {$p}issuers_registry(id)) ENGINE=InnoDB");
        if(property_exists($db,'data_cache'))unset($db->data_cache['table_names']);self::assertValid($db,$p);
    }
    public static function assertValid($db,string $p):void
    {
        if(!preg_match('/^[A-Za-z0-9_]+$/D',$p))throw new InvalidArgumentException('Invalid prefix');
        $conn=$db->pdo??$db->conn_id??null;
        if($conn instanceof PDO&&$conn->getAttribute(PDO::ATTR_DRIVER_NAME)==='sqlite'){
            $rows=IssuerStorage::query($db,'PRAGMA table_info('.$p.'company_customer_group_owners)')->result_array();
            if(array_column($rows,'name')!==['group_id','issuer_id']||array_column($rows,'pk')!==[1,0]||array_column($rows,'notnull')!==[1,1])throw new RuntimeException('company_customer_groups_schema_invalid');return;
        }
        $fields=IssuerStorage::query($db,'SELECT COLUMN_NAME,COLUMN_TYPE,IS_NULLABLE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? ORDER BY ORDINAL_POSITION',[$p.'company_customer_group_owners'])->result_array();
        if(array_column($fields,'COLUMN_NAME')!==['group_id','issuer_id'])throw new RuntimeException('company_customer_groups_schema_invalid');
        foreach(['int','int unsigned'] as $i=>$type)if(preg_replace('/^int\(\d+\)/','int',strtolower($fields[$i]['COLUMN_TYPE']))!==$type||$fields[$i]['IS_NULLABLE']!=='NO')throw new RuntimeException('company_customer_groups_schema_invalid');
        $engine=IssuerStorage::query($db,'SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?',[$p.'company_customer_group_owners'])->row_array();
        $pk=IssuerStorage::query($db,"SELECT COLUMN_NAME FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND INDEX_NAME='PRIMARY' ORDER BY SEQ_IN_INDEX",[$p.'company_customer_group_owners'])->result_array();
        if(strtoupper($engine['ENGINE']??'')!=='INNODB'||array_column($pk,'COLUMN_NAME')!==['group_id'])throw new RuntimeException('company_customer_groups_schema_invalid');
        foreach(['group_id'=>[$p.'customers_groups','id'],'issuer_id'=>[$p.'issuers_registry','id']] as $column=>$target){
            $fk=IssuerStorage::query($db,'SELECT REFERENCED_TABLE_NAME,REFERENCED_COLUMN_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=? AND REFERENCED_TABLE_SCHEMA=DATABASE()',[$p.'company_customer_group_owners',$column])->result_array();
            if(count($fk)!==1||$fk[0]['REFERENCED_TABLE_NAME']!==$target[0]||$fk[0]['REFERENCED_COLUMN_NAME']!==$target[1])throw new RuntimeException('company_customer_groups_schema_invalid');
        }
    }
}
