<?php

defined('BASEPATH') or exit('No direct script access allowed');

/** Preserve legacy module version labels without interpreting them as native versions. */
class Migration_Version_472 extends CI_Migration
{
    public function up(): void
    {
        $table = db_prefix() . 'modules';
        if (!preg_match('/^[a-zA-Z0-9_]+$/D', $table)) {
            throw new RuntimeException('module_version_table_invalid');
        }
        $result = $this->db->query("SHOW FULL COLUMNS FROM `{$table}` LIKE 'installed_version'");
        $column = $result ? $result->row() : null;
        if (!$column || !preg_match('/^varchar\(([0-9]+)\)$/iD', (string) $column->Type, $matches)) {
            throw new RuntimeException('module_version_column_unrecognized');
        }
        if ((int) $matches[1] >= 64) { return; } // Never narrow an existing deployment.
        if (!empty($column->Extra) || !preg_match('/^[a-zA-Z0-9_]+$/D', (string) $column->Collation)) {
            throw new RuntimeException('module_version_column_metadata_unrecognized');
        }
        $definition = 'VARCHAR(64) COLLATE ' . $column->Collation;
        $definition .= $column->Null === 'YES' ? ' NULL' : ' NOT NULL';
        if ($column->Default !== null) {
            $definition .= ' DEFAULT ' . $this->db->escape($column->Default);
        } elseif ($column->Null === 'YES') {
            $definition .= ' DEFAULT NULL';
        }
        $definition .= ' COMMENT ' . $this->db->escape($column->Comment ?? '');
        if (!$this->db->query("ALTER TABLE `{$table}` MODIFY `installed_version` {$definition}")) {
            throw new RuntimeException('module_version_column_widen_failed');
        }
        // No UPDATE, activation hook or module installer: existing strings stay byte-for-byte unchanged.
    }

    public function down(): void
    {
        // Intentionally retain capacity: narrowing can truncate legacy labels.
    }
}
