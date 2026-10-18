<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Migration_Version_101 extends App_module_migration
{
    public function up()
    {
        $table = db_prefix() . 'pige_bridge_requests';
        if (!$this->ci->db->table_exists($table)) {
            if ($this->ci->db->query("CREATE TABLE `{$table}` (
        `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        `connection_key` VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
        `event_id` CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
        `request_id` CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
        `payload_sha256` CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
        `scope_sha256` CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
        `school_id` CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
        `issuer_id` VARCHAR(19) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
        `account_id` VARCHAR(128) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
        `environment` VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
        `charge_id` CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
        `amount_cents` BIGINT UNSIGNED NOT NULL,
        `due_date` DATE NOT NULL,
        `created_at` DATETIME NOT NULL,
        PRIMARY KEY (`id`),
        UNIQUE KEY `connection_event` (`connection_key`, `event_id`),
        UNIQUE KEY `request_id` (`request_id`),
        UNIQUE KEY `scope_charge` (`scope_sha256`, `charge_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;") === false) { throw new RuntimeException("pige_bridge_schema_create_failed"); }
        }
        // Existing receipts are never dropped, rewritten or silently accepted with a broken schema.
        $columns = $this->ci->db->query('SHOW FULL COLUMNS FROM `' . $table . '`');
        if ($columns === false) { throw new RuntimeException('pige_bridge_schema_inspection_failed'); }
        $found = [];
        foreach ($columns->result_array() as $column) { $found[$column['Field']] = $column; }
        foreach (['id','connection_key','event_id','request_id','payload_sha256','scope_sha256','school_id','issuer_id','account_id','environment','charge_id','amount_cents','due_date','created_at'] as $field) {
            if (!isset($found[$field]) || $found[$field]['Null'] !== 'NO') { throw new RuntimeException('pige_bridge_schema_incompatible'); }
        }
        $types = ['id'=>'bigint unsigned','connection_key'=>'varchar(64)','event_id'=>'char(36)','request_id'=>'char(36)','payload_sha256'=>'char(64)','scope_sha256'=>'char(64)','school_id'=>'char(36)','issuer_id'=>'varchar(19)','account_id'=>'varchar(128)','environment'=>'varchar(16)','charge_id'=>'char(36)','amount_cents'=>'bigint unsigned','due_date'=>'date','created_at'=>'datetime'];
        foreach ($types as $field => $type) {
            $actual = preg_replace('/^bigint\(\d+\)/', 'bigint', strtolower((string) $found[$field]['Type']));
            if ($actual !== $type) { throw new RuntimeException('pige_bridge_schema_incompatible'); }
        }
        if (stripos((string) $found['id']['Extra'], 'auto_increment') === false) { throw new RuntimeException('pige_bridge_schema_incompatible'); }
        foreach (['connection_key','event_id','request_id','payload_sha256','scope_sha256','school_id','issuer_id','account_id','environment','charge_id'] as $field) {
            if ($found[$field]['Collation'] !== 'ascii_bin') { throw new RuntimeException('pige_bridge_schema_incompatible'); }
        }
        $result = $this->ci->db->query('SHOW INDEX FROM `' . $table . '`');
        if ($result === false) { throw new RuntimeException('pige_bridge_schema_inspection_failed'); }
        $unique = [];
        $invalid = [];
        foreach ($result->result_array() as $index) {
            if (!empty($index['Sub_part'])) { $invalid[$index['Key_name']] = true; }
            if ((int) $index['Non_unique'] === 0 && empty($index['Sub_part'])) { $unique[$index['Key_name']][(int) $index['Seq_in_index']] = $index['Column_name']; }
        }
        $keys = [];
        foreach ($unique as $name => $columns) { if (isset($invalid[$name])) { continue; } ksort($columns); $keys[] = implode(',', $columns); }
        foreach (['id','connection_key,event_id','request_id','scope_sha256,charge_id'] as $required) {
            if (!in_array($required, $keys, true)) { throw new RuntimeException('pige_bridge_schema_incompatible'); }
        }
        $engine = $this->ci->db->query('SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?', [$table]);
        if ($engine === false || strtoupper((string) ($engine->row()->ENGINE ?? '')) !== 'INNODB') { throw new RuntimeException('pige_bridge_schema_incompatible'); }
    }

    public function down()
    {
        // Reverting code must not erase durable idempotency receipts.
        throw new RuntimeException('pige_bridge_destructive_downgrade_not_supported');
    }
}
