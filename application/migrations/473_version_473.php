<?php
defined('BASEPATH') or exit('No direct script access allowed');

/** Audit receipts for explicit, reviewed legacy module adoption; never rewrite labels here. */
class Migration_Version_473 extends CI_Migration
{
    public function up(): void
    {
        $table = db_prefix() . 'module_version_adoptions';
        if (!preg_match('/^[A-Za-z0-9_]+$/D', $table)) { throw new RuntimeException('module_adoption_table_invalid'); }
        if (!$this->db->query("CREATE TABLE IF NOT EXISTS `{$table}` (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            module_name VARCHAR(64) COLLATE utf8mb4_bin NOT NULL,
            plan_id VARCHAR(64) COLLATE utf8mb4_bin NOT NULL,
            source_version VARCHAR(64) COLLATE utf8mb4_bin NOT NULL,
            target_version VARCHAR(64) COLLATE utf8mb4_bin NOT NULL,
            source_header_sha256 CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
            actor_id INT UNSIGNED NOT NULL,
            created_at DATETIME NOT NULL,
            PRIMARY KEY (id), UNIQUE KEY module_plan (module_name,plan_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin")) {
            throw new RuntimeException('module_adoption_create_failed');
        }
        $result = $this->db->query('SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?', [$table]);
        if (!$result || strtoupper((string) $result->row()->ENGINE) !== 'INNODB') { throw new RuntimeException('module_adoption_engine_invalid'); }
        $result = $this->db->query("SHOW FULL COLUMNS FROM `{$table}`");
        $columns = $result ? $result->result_array() : [];
        $expected = ['id'=>'bigint unsigned','module_name'=>'varchar(64)','plan_id'=>'varchar(64)','source_version'=>'varchar(64)','target_version'=>'varchar(64)','source_header_sha256'=>'char(64)','actor_id'=>'int unsigned','created_at'=>'datetime'];
        if (count($columns) !== count($expected)) { throw new RuntimeException('module_adoption_columns_invalid'); }
        foreach ($columns as $column) {
            $field = $column['Field'];
            $type = preg_replace('/\b(bigint|int)\(\d+\)/', '$1', strtolower($column['Type']));
            $collation = in_array($field, ['module_name','plan_id','source_version','target_version'], true) ? 'utf8mb4_bin' : ($field === 'source_header_sha256' ? 'ascii_bin' : null);
            if (!isset($expected[$field]) || $type !== $expected[$field] || $column['Null'] !== 'NO'
                || $column['Collation'] !== $collation || $column['Extra'] !== ($field === 'id' ? 'auto_increment' : '')) {
                throw new RuntimeException('module_adoption_column_invalid');
            }
        }
        $result = $this->db->query("SHOW INDEX FROM `{$table}`");
        $keys = [];
        foreach ($result ? $result->result_array() : [] as $key) {
            if ((int) $key['Non_unique'] !== 0 || $key['Sub_part'] !== null || $key['Index_type'] !== 'BTREE') { throw new RuntimeException('module_adoption_index_invalid'); }
            $keys[$key['Key_name']][(int) $key['Seq_in_index']] = $key['Column_name'];
        }
        if (($keys['PRIMARY'] ?? []) !== [1=>'id'] || ($keys['module_plan'] ?? []) !== [1=>'module_name',2=>'plan_id']) {
            throw new RuntimeException('module_adoption_keys_invalid');
        }
        $this->chargeBindings();
        $this->chargeIntents();
        require_once APPPATH . 'services/EfiCompanySchema.php';
        \Efi\CompanySchema::ensure($this->db, db_prefix(), true);
    }
    private function chargeBindings(): void
    {
        $table = db_prefix() . 'efi_charge_bindings';
        if (!preg_match('/^[A-Za-z0-9_]+$/D', $table)) { throw new RuntimeException('efi_binding_table_invalid'); }
        if (!$this->db->query("CREATE TABLE IF NOT EXISTS `{$table}` (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            provider_id VARCHAR(128) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
            kind VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
            sandbox TINYINT UNSIGNED NOT NULL,
            account_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
            invoice_id INT UNSIGNED NOT NULL,
            issuer_id INT UNSIGNED NULL,
            amount_minor BIGINT UNSIGNED NOT NULL,
            currency CHAR(3) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
            payment_id INT UNSIGNED NULL,
            created_at DATETIME NOT NULL,
            PRIMARY KEY (id), UNIQUE KEY provider_identity (kind,sandbox,account_hash,provider_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin")) {
            throw new RuntimeException('efi_binding_create_failed');
        }
        $result = $this->db->query('SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?', [$table]);
        if (!$result || strtoupper((string) $result->row()->ENGINE) !== 'INNODB') { throw new RuntimeException('efi_binding_engine_invalid'); }
        $result = $this->db->query("SHOW FULL COLUMNS FROM `{$table}`");
        $columns = $result ? $result->result_array() : [];
        $expected = ['id'=>'bigint unsigned','provider_id'=>'varchar(128)','kind'=>'varchar(16)',
            'sandbox'=>'tinyint unsigned','account_hash'=>'char(64)','invoice_id'=>'int unsigned','issuer_id'=>'int unsigned',
            'amount_minor'=>'bigint unsigned','currency'=>'char(3)','payment_id'=>'int unsigned','created_at'=>'datetime'];
        if (count($columns) !== count($expected)) { throw new RuntimeException('efi_binding_columns_invalid'); }
        foreach ($columns as $column) {
            $field = $column['Field'];
            $type = preg_replace('/\b(bigint|tinyint|int)\(\d+\)/', '$1', strtolower($column['Type']));
            $collation = in_array($field, ['provider_id','kind','currency','account_hash'], true) ? 'ascii_bin' : null;
            $nullable = in_array($field, ['issuer_id','payment_id'], true) ? 'YES' : 'NO';
            if (!isset($expected[$field]) || $type !== $expected[$field] || $column['Null'] !== $nullable
                || $column['Collation'] !== $collation || $column['Extra'] !== ($field === 'id' ? 'auto_increment' : '')) {
                throw new RuntimeException('efi_binding_column_invalid');
            }
        }
        $result = $this->db->query("SHOW INDEX FROM `{$table}`"); $keys = [];
        foreach ($result ? $result->result_array() : [] as $key) {
            if ((int) $key['Non_unique'] !== 0 || $key['Sub_part'] !== null || $key['Index_type'] !== 'BTREE') { throw new RuntimeException('efi_binding_index_invalid'); }
            $keys[$key['Key_name']][(int) $key['Seq_in_index']] = $key['Column_name'];
        }
        if (($keys['PRIMARY'] ?? []) !== [1=>'id'] || ($keys['provider_identity'] ?? []) !== [1=>'kind',2=>'sandbox',3=>'account_hash',4=>'provider_id']) {
            throw new RuntimeException('efi_binding_keys_invalid');
        }
    }
    private function chargeIntents(): void
    {
        $table = db_prefix() . 'efi_charge_intents';
        if (!preg_match('/^[A-Za-z0-9_]+$/D', $table)) { throw new RuntimeException('efi_intent_table_invalid'); }
        if (!$this->db->query("CREATE TABLE IF NOT EXISTS `{$table}` (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            invoice_id INT UNSIGNED NOT NULL,
            issuer_id INT UNSIGNED NULL,
            kind VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
            sandbox TINYINT UNSIGNED NOT NULL,
            account_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
            amount_minor BIGINT UNSIGNED NOT NULL,
            currency CHAR(3) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
            state VARCHAR(24) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
            provider_id VARCHAR(128) CHARACTER SET ascii COLLATE ascii_bin NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY (id), UNIQUE KEY invoice_intent (invoice_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin")) {
            throw new RuntimeException('efi_intent_create_failed');
        }
        $result = $this->db->query('SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?', [$table]);
        if (!$result || strtoupper((string) $result->row()->ENGINE) !== 'INNODB') { throw new RuntimeException('efi_intent_engine_invalid'); }
        $result = $this->db->query("SHOW FULL COLUMNS FROM `{$table}`");
        $columns = $result ? $result->result_array() : [];
        $expected = ['id'=>'bigint unsigned','invoice_id'=>'int unsigned','issuer_id'=>'int unsigned','kind'=>'varchar(16)',
            'sandbox'=>'tinyint unsigned','account_hash'=>'char(64)','amount_minor'=>'bigint unsigned','currency'=>'char(3)',
            'state'=>'varchar(24)','provider_id'=>'varchar(128)','created_at'=>'datetime','updated_at'=>'datetime'];
        if (count($columns) !== count($expected)) { throw new RuntimeException('efi_intent_columns_invalid'); }
        foreach ($columns as $column) {
            $field = $column['Field'];
            $type = preg_replace('/\b(bigint|tinyint|int)\(\d+\)/', '$1', strtolower($column['Type']));
            $collation = in_array($field, ['provider_id','kind','currency','account_hash','state'], true) ? 'ascii_bin' : null;
            $nullable = in_array($field, ['issuer_id','provider_id'], true) ? 'YES' : 'NO';
            if (!isset($expected[$field]) || $type !== $expected[$field] || $column['Null'] !== $nullable
                || $column['Collation'] !== $collation || $column['Extra'] !== ($field === 'id' ? 'auto_increment' : '')) {
                throw new RuntimeException('efi_intent_column_invalid');
            }
        }
        $result = $this->db->query("SHOW INDEX FROM `{$table}`"); $keys = [];
        foreach ($result ? $result->result_array() : [] as $key) {
            if ((int) $key['Non_unique'] !== 0 || $key['Sub_part'] !== null || $key['Index_type'] !== 'BTREE') { throw new RuntimeException('efi_intent_index_invalid'); }
            $keys[$key['Key_name']][(int) $key['Seq_in_index']] = $key['Column_name'];
        }
        if (($keys['PRIMARY'] ?? []) !== [1=>'id'] || ($keys['invoice_intent'] ?? []) !== [1=>'invoice_id']) {
            throw new RuntimeException('efi_intent_keys_invalid');
        }
    }
    public function down(): void { /* Retain immutable audit receipts; no destructive rollback. */ }
}
