<?php
defined('BASEPATH') or exit('No direct script access allowed');
/** Immutable external intentions, never backfilled from old invoices or legacy receipts. */
class Migration_Version_105 extends App_module_migration
{
    public function up()
    {
        $table=db_prefix().'pige_bridge_charge_origins';
        if(!$this->ci->db->table_exists($table)){
            if($this->ci->db->query("CREATE TABLE `{$table}` (
                request_key CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
                request_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
                connection_key VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
                event_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
                charge_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
                payload_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
                origin_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
                origin_json TEXT NOT NULL,
                created_at DATETIME NOT NULL,
                PRIMARY KEY(request_key), UNIQUE KEY request_id(request_id),
                UNIQUE KEY connection_charge(connection_key,charge_id), UNIQUE KEY connection_event(connection_key,event_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin")===false)throw new RuntimeException('pige_charge_schema_create_failed');
            if(isset($this->ci->db->data_cache['table_names']))unset($this->ci->db->data_cache['table_names']);
        }
        $result=$this->ci->db->query('SHOW FULL COLUMNS FROM `'.$table.'`');if($result===false)throw new RuntimeException('pige_charge_schema_unavailable');
        $columns=[];foreach($result->result_array() as $row)$columns[$row['Field']]=$row;
        $types=['request_key'=>'char(64)','request_id'=>'char(36)','connection_key'=>'varchar(64)','event_id'=>'char(36)','charge_id'=>'char(36)','payload_hash'=>'char(64)','origin_hash'=>'char(64)','origin_json'=>'text','created_at'=>'datetime'];
        foreach($types as $field=>$type){$actual=$columns[$field]??[];if(strtolower($actual['Type']??'')!==$type||($actual['Null']??null)!=='NO')throw new RuntimeException('pige_charge_schema_incompatible');}
        foreach(['request_key','request_id','connection_key','event_id','charge_id','payload_hash','origin_hash'] as $field)if($columns[$field]['Collation']!=='ascii_bin')throw new RuntimeException('pige_charge_schema_incompatible');
        $result=$this->ci->db->query('SHOW INDEX FROM `'.$table.'`');if($result===false)throw new RuntimeException('pige_charge_schema_unavailable');$keys=[];$bad=[];
        foreach($result->result_array() as $row){if((int)$row['Non_unique']!==0||!empty($row['Sub_part'])){$bad[$row['Key_name']]=true;continue;}$keys[$row['Key_name']][(int)$row['Seq_in_index']]=$row['Column_name'];}
        $unique=[];foreach($keys as $name=>$fields){if(isset($bad[$name]))continue;ksort($fields);$unique[]=implode(',',$fields);}
        foreach(['request_key','request_id','connection_key,charge_id','connection_key,event_id'] as $key)if(!in_array($key,$unique,true))throw new RuntimeException('pige_charge_schema_incompatible');
        $engine=$this->ci->db->query('SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?',[$table]);if($engine===false||strtoupper($engine->row()->ENGINE??'')!=='INNODB')throw new RuntimeException('pige_charge_schema_incompatible');
    }
    public function down(){throw new RuntimeException('pige_charge_destructive_downgrade_not_supported');}
}
