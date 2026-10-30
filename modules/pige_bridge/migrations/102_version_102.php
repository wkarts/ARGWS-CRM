<?php
defined('BASEPATH') or exit('No direct script access allowed');
/** Additive protected configuration; existing receipts remain untouched. */
class Migration_Version_102 extends App_module_migration
{
    public function up()
    {
        $table=db_prefix().'pige_bridge_connections';
        if(!$this->ci->db->table_exists($table)){
            if($this->ci->db->query("CREATE TABLE `{$table}` (
                connection_key VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
                scope_json TEXT NOT NULL,
                scope_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
                secret_cipher TEXT NOT NULL,
                secret_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
                enabled TINYINT NOT NULL DEFAULT 0,
                revision BIGINT UNSIGNED NOT NULL,
                mapping_json TEXT NULL,
                updated_by INT NOT NULL,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                PRIMARY KEY(connection_key), UNIQUE KEY scope_hash(scope_hash), UNIQUE KEY secret_hash(secret_hash)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin")===false)throw new RuntimeException('pige_connection_schema_create_failed');
            if(isset($this->ci->db->data_cache['table_names']))unset($this->ci->db->data_cache['table_names']);
        }
        $result=$this->ci->db->query('SHOW FULL COLUMNS FROM `'.$table.'`');if($result===false)throw new RuntimeException('pige_connection_schema_unavailable');
        $columns=[];foreach($result->result_array() as $row)$columns[$row['Field']]=$row;
        $types=['connection_key'=>'varchar(64)','scope_json'=>'text','scope_hash'=>'char(64)','secret_cipher'=>'text','secret_hash'=>'char(64)','enabled'=>'tinyint','revision'=>'bigint unsigned','mapping_json'=>'text','updated_by'=>'int','created_at'=>'datetime','updated_at'=>'datetime'];
        foreach($types as $field=>$type){$actual=$columns[$field]??[];$normal=preg_replace('/^(bigint|tinyint|int)\(\d+\)/','$1',strtolower($actual['Type']??''));if($normal!==$type||($actual['Null']??null)!==($field==='mapping_json'?'YES':'NO'))throw new RuntimeException('pige_connection_schema_incompatible');}
        foreach(['connection_key','scope_hash','secret_hash'] as $field)if($columns[$field]['Collation']!=='ascii_bin')throw new RuntimeException('pige_connection_schema_incompatible');
        if((string)($columns['enabled']['Default']??'')!=='0')throw new RuntimeException('pige_connection_schema_incompatible');
        $result=$this->ci->db->query('SHOW INDEX FROM `'.$table.'`');if($result===false)throw new RuntimeException('pige_connection_schema_unavailable');$keys=[];$bad=[];
        foreach($result->result_array() as $row){if((int)$row['Non_unique']!==0||!empty($row['Sub_part'])){$bad[$row['Key_name']]=true;continue;}$keys[$row['Key_name']][(int)$row['Seq_in_index']]=$row['Column_name'];}
        $unique=[];foreach($keys as $name=>$fields){if(isset($bad[$name]))continue;ksort($fields);$unique[]=implode(',',$fields);}
        foreach(['connection_key','scope_hash','secret_hash'] as $key)if(!in_array($key,$unique,true))throw new RuntimeException('pige_connection_schema_incompatible');
        $engine=$this->ci->db->query('SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?',[$table]);if($engine===false||strtoupper($engine->row()->ENGINE??'')!=='INNODB')throw new RuntimeException('pige_connection_schema_incompatible');
    }
    public function down(){throw new RuntimeException('pige_connection_destructive_downgrade_not_supported');}
}
