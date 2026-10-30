<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Migration_Version_145 extends App_module_migration
{
    private $rollbackOption = 'argws_asaas_145_schema_state';

    public function up()
    {
        $table = db_prefix() . 'asaas_customers_map';
        if (!$this->ci->db->table_exists($table)) {
            return;
        }

        $state = $this->load_or_create_rollback_state($table);

        if (!$this->ci->db->field_exists('client_id', $table)) {
            $this->ci->db->query('ALTER TABLE `' . $table . '` ADD `client_id` INT NULL DEFAULT NULL');
        }

        if ($this->ci->db->field_exists('perfex_client_id', $table)) {
            $this->ci->db->query('UPDATE `' . $table . '` SET `client_id` = `perfex_client_id` WHERE `client_id` IS NULL');
        }

        if (!$this->has_unique_index_for_column($table, 'client_id') && !$this->has_duplicate_values($table, 'client_id')) {
            $this->ci->db->query('ALTER TABLE `' . $table . '` ADD UNIQUE KEY `argws_asaas_client_id_uq` (`client_id`)');
            $state['client_id_index_added'] = true;
            $this->store_rollback_state($state);
        }
    }

    public function down()
    {
        $table = db_prefix() . 'asaas_customers_map';
        if (!$this->ci->db->table_exists($table)) {
            $this->delete_rollback_state();
            return;
        }

        $state = $this->get_rollback_state();
        $hasClientId = $this->ci->db->field_exists('client_id', $table);
        $hasLegacyId = $this->ci->db->field_exists('perfex_client_id', $table);

        if ($hasClientId && !$hasLegacyId) {
            $this->ci->db->query('ALTER TABLE `' . $table . '` ADD `perfex_client_id` INT NULL DEFAULT NULL');
            $hasLegacyId = true;
        }
        if ($hasClientId && $hasLegacyId) {
            $copyAllMappedIds = !empty($state['client_id_added']);
            $where = $copyAllMappedIds
                ? '`client_id` IS NOT NULL'
                : '`client_id` IS NOT NULL AND `perfex_client_id` IS NULL';
            $this->ci->db->query('UPDATE `' . $table . '` SET `perfex_client_id` = `client_id` WHERE ' . $where);
        }

        if (!empty($state['client_id_index_added'])) {
            $indexes = $this->ci->db->query('SHOW INDEX FROM `' . $table . '`')->result_array();
            foreach ($indexes as $index) {
                if ($index['Key_name'] === 'argws_asaas_client_id_uq') {
                    $this->ci->db->query('ALTER TABLE `' . $table . '` DROP INDEX `argws_asaas_client_id_uq`');
                    break;
                }
            }
        }

        if (!empty($state['client_id_added']) && $this->ci->db->field_exists('client_id', $table)) {
            $this->ci->db->query('ALTER TABLE `' . $table . '` DROP COLUMN `client_id`');
        }

        if ($hasLegacyId && !$this->has_unique_index_for_column($table, 'perfex_client_id')
            && !$this->has_duplicate_values($table, 'perfex_client_id')) {
            $this->ci->db->query('ALTER TABLE `' . $table . '` ADD UNIQUE KEY `perfex_client_id` (`perfex_client_id`)');
        }

        $this->delete_rollback_state();
    }

    private function load_or_create_rollback_state(string $table): array
    {
        $state = $this->get_rollback_state();
        if ($state !== null) {
            return $state;
        }

        $state = [
            'client_id_added' => !$this->ci->db->field_exists('client_id', $table),
            'client_id_index_added' => false,
        ];
        $this->store_rollback_state($state);

        return $state;
    }

    private function get_rollback_state(): ?array
    {
        $row = $this->ci->db->get_where(db_prefix() . 'options', ['name' => $this->rollbackOption])->row();
        if (!$row) {
            return null;
        }

        $state = json_decode((string) $row->value, true);
        return is_array($state) ? $state : [];
    }

    private function store_rollback_state(array $state): void
    {
        $table = db_prefix() . 'options';
        $value = json_encode($state, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $row = $this->ci->db->get_where($table, ['name' => $this->rollbackOption])->row();
        if ($row) {
            $this->ci->db->where('name', $this->rollbackOption)->update($table, ['value' => $value]);
        } else {
            $this->ci->db->insert($table, ['name' => $this->rollbackOption, 'value' => $value, 'autoload' => 0]);
        }
    }

    private function delete_rollback_state(): void
    {
        $this->ci->db->where('name', $this->rollbackOption)->delete(db_prefix() . 'options');
    }

    private function has_unique_index_for_column(string $table, string $column): bool
    {
        $indexes = $this->ci->db->query('SHOW INDEX FROM `' . $table . '`')->result_array();
        foreach ($indexes as $index) {
            if ($index['Column_name'] === $column && (int) $index['Non_unique'] === 0) {
                return true;
            }
        }

        return false;
    }

    private function has_duplicate_values(string $table, string $column): bool
    {
        $query = 'SELECT `' . $column . '` FROM `' . $table . '` WHERE `' . $column . '` IS NOT NULL '
            . 'GROUP BY `' . $column . '` HAVING COUNT(*) > 1 LIMIT 1';

        return (bool) $this->ci->db->query($query)->row();
    }
}
