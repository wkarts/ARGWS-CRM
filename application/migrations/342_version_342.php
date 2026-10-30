<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Migration_Version_342 extends CI_Migration
{
    private $backupTable;

    public function __construct()
    {
        parent::__construct();
        $this->backupTable = db_prefix() . 'argws_migration_342_backup';
    }

    public function up(): void
    {
        if (!$this->db->table_exists($this->backupTable)) {
            $this->db->query('CREATE TABLE `' . $this->backupTable . '` ('
                . '`scope` varchar(64) NOT NULL,'
                . '`record_id` bigint NOT NULL DEFAULT 0,'
                . '`old_value` longtext NULL,'
                . '`was_present` tinyint(1) NOT NULL DEFAULT 1,'
                . 'PRIMARY KEY (`scope`, `record_id`)'
                . ') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        }

        $options = [
            'active_language' => 'portuguese_br',
            'enabled_languages' => json_encode(['portuguese_br']),
            'argws_support_enabled' => '0',
            'argws_support_base_url' => '',
            'argws_support_public_token' => '',
            'argws_support_position' => 'left',
            'argws_support_type' => 'expanded_bubble',
            'argws_support_launcher_title' => 'Suporte',
            'argws_terminology_policy' => json_encode([
                'translate' => ['Customer' => 'Cliente', 'Invoice' => 'Fatura', 'E-mail' => 'e-mail'],
                'keep_original' => ['API', 'Docker', 'FrankenPHP', 'GHCR'],
                'approved' => ['Email' => 'e-mail', 'Client' => 'Cliente', 'Invoice' => 'Fatura'],
                'contexts' => [
                    'e-mail' => 'Canal e endereço eletrônico; preservar a grafia consolidada.',
                    'Cliente' => 'Pessoa ou organização cadastrada como cliente.',
                    'Fatura' => 'Documento de cobrança da aplicação.',
                    'API' => 'Interface de integração; manter a sigla.',
                ],
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ];

        foreach ($options as $name => $value) {
            $row = $this->db->get_where(db_prefix() . 'options', ['name' => $name])->row();
            $this->save_backup('option:' . $name, 0, $row ? $row->value : null, (bool) $row);
            if ($row) {
                $this->db->where('name', $name)->update(db_prefix() . 'options', ['value' => $value]);
            } else {
                $this->db->insert(db_prefix() . 'options', ['name' => $name, 'value' => $value, 'autoload' => 1]);
            }
        }

        // Remove obsolete store/licensing identifiers after preserving their values for rollback.
        foreach ([
            'purchase_key',
            'identification_key',
            'translations_purchase_code',
            'si_custom_theme_activated',
            'si_custom_theme_activation_code',
        ] as $name) {
            $row = $this->db->get_where(db_prefix() . 'options', ['name' => $name])->row();
            $this->save_backup('option:' . $name, 0, $row ? $row->value : null, (bool) $row);
            if ($row) {
                $this->db->where('name', $name)->delete(db_prefix() . 'options');
            }
        }

        foreach ([
            'staff' => ['staffid', 'default_language'],
            'clients' => ['userid', 'default_language'],
            'leads' => ['id', 'default_language'],
        ] as $table => [$idColumn, $languageColumn]) {
            $tableName = db_prefix() . $table;
            if (!$this->db->table_exists($tableName) || !$this->db->field_exists($languageColumn, $tableName)) {
                continue;
            }
            $rows = $this->db->query('SELECT `' . $idColumn . '` AS `record_id`, `' . $languageColumn . '` AS `old_value` FROM `' . $tableName . '` WHERE `' . $languageColumn . '` IS NULL OR `' . $languageColumn . '` <> ?', ['portuguese_br'])->result();
            foreach ($rows as $row) {
                $this->save_backup($table, (int) $row->record_id, $row->old_value, true);
            }
            $this->db->query('UPDATE `' . $tableName . '` SET `' . $languageColumn . '` = ? WHERE `' . $languageColumn . '` IS NULL OR `' . $languageColumn . '` <> ?', ['portuguese_br', 'portuguese_br']);
        }
    }

    public function down(): void
    {
        if (!$this->db->table_exists($this->backupTable)) {
            return;
        }

        $backup = $this->db->get($this->backupTable)->result();
        foreach ($backup as $row) {
            if (strpos($row->scope, 'option:') === 0) {
                $name = substr($row->scope, 7);
                $current = $this->db->get_where(db_prefix() . 'options', ['name' => $name])->row();
                $expected = $this->expected_option_value($name);
                $expectedAbsent = in_array($name, [
                    'purchase_key',
                    'identification_key',
                    'translations_purchase_code',
                    'si_custom_theme_activated',
                    'si_custom_theme_activation_code',
                ], true);
                if ($expectedAbsent ? (bool) $current : (($current && $current->value !== $expected) || (!$current && $row->was_present))) {
                    continue;
                }
                if ($row->was_present) {
                    $this->db->where('name', $name)->update(db_prefix() . 'options', ['value' => $row->old_value]);
                } else {
                    $this->db->where('name', $name)->delete(db_prefix() . 'options');
                }
                continue;
            }

            $columns = [
                'staff' => ['staffid', 'default_language'],
                'clients' => ['userid', 'default_language'],
                'leads' => ['id', 'default_language'],
            ];
            if (!isset($columns[$row->scope])) {
                continue;
            }
            [$idColumn, $languageColumn] = $columns[$row->scope];
            $tableName = db_prefix() . $row->scope;
            $current = $this->db->select($languageColumn)->get_where($tableName, [$idColumn => $row->record_id])->row();
            if ($current && $current->{$languageColumn} === 'portuguese_br') {
                $this->db->where($idColumn, $row->record_id)->update($tableName, [$languageColumn => $row->old_value]);
            }
        }

        $this->db->drop_table($this->backupTable, true);
    }

    private function save_backup(string $scope, int $recordId, ?string $value, bool $wasPresent): void
    {
        // Keep the first pre-migration value if an earlier attempt stopped partway.
        if ($this->db->get_where($this->backupTable, ['scope' => $scope, 'record_id' => $recordId])->row()) {
            return;
        }

        $this->db->insert($this->backupTable, [
            'scope' => $scope,
            'record_id' => $recordId,
            'old_value' => $value,
            'was_present' => $wasPresent ? 1 : 0,
        ]);
    }

    private function expected_option_value(string $name): string
    {
        if ($name === 'active_language') {
            return 'portuguese_br';
        }
        if ($name === 'enabled_languages') {
            return json_encode(['portuguese_br']);
        }
        if (in_array($name, [
            'purchase_key',
            'identification_key',
            'translations_purchase_code',
            'si_custom_theme_activated',
            'si_custom_theme_activation_code',
        ], true)) {
            return '';
        }
        if ($name === 'argws_terminology_policy') {
            return json_encode([
                'translate' => ['Customer' => 'Cliente', 'Invoice' => 'Fatura', 'E-mail' => 'e-mail'],
                'keep_original' => ['API', 'Docker', 'FrankenPHP', 'GHCR'],
                'approved' => ['Email' => 'e-mail', 'Client' => 'Cliente', 'Invoice' => 'Fatura'],
                'contexts' => [
                    'e-mail' => 'Canal e endereço eletrônico; preservar a grafia consolidada.',
                    'Cliente' => 'Pessoa ou organização cadastrada como cliente.',
                    'Fatura' => 'Documento de cobrança da aplicação.',
                    'API' => 'Interface de integração; manter a sigla.',
                ],
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }
        $defaults = [
            'argws_support_enabled' => '0',
            'argws_support_base_url' => '',
            'argws_support_public_token' => '',
            'argws_support_position' => 'left',
            'argws_support_type' => 'expanded_bubble',
            'argws_support_launcher_title' => 'Suporte',
        ];
        return $defaults[$name] ?? '';
    }
}
