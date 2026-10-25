<?php
defined('BASEPATH') or exit('No direct script access allowed');
/** Separate explicit provider consent; existing connections stay unconfigured. */
class Migration_Version_104 extends App_module_migration
{
    public function up()
    {
        $table=db_prefix().'pige_bridge_external_config';
        if(!$this->ci->db->table_exists($table)){
            if($this->ci->db->query("CREATE TABLE `{$table}` (
                connection_key VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
                connection_revision BIGINT UNSIGNED NOT NULL,
                revision BIGINT UNSIGNED NOT NULL,
                mapping_json TEXT NOT NULL,
                account_revision CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
                scope_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
                updated_by INT NOT NULL,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                PRIMARY KEY(connection_key)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin")===false)throw new RuntimeException('pige_external_schema_create_failed');
            if(isset($this->ci->db->data_cache['table_names']))unset($this->ci->db->data_cache['table_names']);
        }
        $result=$this->ci->db->query('SHOW FULL COLUMNS FROM `'.$table.'`');if($result===false)throw new RuntimeException('pige_external_schema_unavailable');
        $columns=[];foreach($result->result_array() as $row)$columns[$row['Field']]=$row;
        $types=['connection_key'=>'varchar(64)','connection_revision'=>'bigint unsigned','revision'=>'bigint unsigned','mapping_json'=>'text','account_revision'=>'char(64)','scope_hash'=>'char(64)','updated_by'=>'int','created_at'=>'datetime','updated_at'=>'datetime'];
        foreach($types as $field=>$type){$actual=$columns[$field]??[];$normal=preg_replace('/^(bigint|int)\(\d+\)/','$1',strtolower($actual['Type']??''));if($normal!==$type||($actual['Null']??null)!=='NO')throw new RuntimeException('pige_external_schema_incompatible');}
        foreach(['connection_key','account_revision','scope_hash'] as $field)if($columns[$field]['Collation']!=='ascii_bin')throw new RuntimeException('pige_external_schema_incompatible');
        $result=$this->ci->db->query('SHOW INDEX FROM `'.$table.'`');if($result===false)throw new RuntimeException('pige_external_schema_unavailable');$keys=[];$bad=[];
        foreach($result->result_array() as $row){if((int)$row['Non_unique']!==0||!empty($row['Sub_part'])){$bad[$row['Key_name']]=true;continue;}$keys[$row['Key_name']][(int)$row['Seq_in_index']]=$row['Column_name'];}
        $unique=[];foreach($keys as $name=>$fields){if(isset($bad[$name]))continue;ksort($fields);$unique[]=implode(',',$fields);}
        foreach(['connection_key'] as $key)if(!in_array($key,$unique,true))throw new RuntimeException('pige_external_schema_incompatible');
        $engine=$this->ci->db->query('SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?',[$table]);if($engine===false||strtoupper($engine->row()->ENGINE??'')!=='INNODB')throw new RuntimeException('pige_external_schema_incompatible');
    }
    public function down(){throw new RuntimeException('pige_external_destructive_downgrade_not_supported');}
}
