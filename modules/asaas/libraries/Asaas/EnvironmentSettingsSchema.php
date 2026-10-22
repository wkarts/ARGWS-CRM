<?php

declare(strict_types=1);
namespace Asaas;
require_once __DIR__ . '/EnvironmentSettings.php';

/** Additive native-options migration. Never reads/copies API keys or webhook secrets. */
final class EnvironmentSettingsSchema
{
    public static function ensure($db, string $prefix): void
    {
        if (!preg_match('/^[A-Za-z0-9_]+$/D', $prefix)) throw new \InvalidArgumentException('invalid_asaas_options_prefix');
        $table = $prefix . 'options';
        if (!$db->table_exists($table)) throw new \RuntimeException('asaas_options_table_missing');
        foreach (EnvironmentSettings::DEFAULTS as $field => $default) {
            $legacy = $db->get_where($table, ['name' => 'paymentmethod_asaas_' . $field])->row_array() ?? [];
            // Presence, not truthiness: preserve '', '0', and previous custom settings.
            $value = array_key_exists('value', $legacy) ? $legacy['value'] : $default;
            foreach (EnvironmentSettings::ENVIRONMENTS as $environment) {
                $name = EnvironmentSettings::option($field, $environment);
                $existing = $db->get_where($table, ['name' => $name])->row_array();
                if ($existing) continue;
                $row = ['name' => $name, 'value' => $value];
                if ($db->field_exists('autoload', $table)) {
                    $row['autoload'] = in_array($field, ['active', 'label', 'default_selected'], true) ? 1 : 0;
                }
                $inserted = $db->insert($table, $row);
                $saved = $db->get_where($table, ['name' => $name])->row_array();
                if ($inserted === false || !$saved || !array_key_exists('value', $saved)
                    || (string) $saved['value'] !== (string) $value) {
                    throw new \RuntimeException('asaas_profile_initialization_unconfirmed');
                }
            }
        }
        $marker = 'paymentmethod_asaas_profile_schema';
        $saved = $db->get_where($table, ['name' => $marker])->row_array();
        if (!$saved) {
            $row = ['name' => $marker, 'value' => '1'];
            if ($db->field_exists('autoload', $table)) $row['autoload'] = 1;
            if ($db->insert($table, $row) === false) throw new \RuntimeException('asaas_profile_initialization_unconfirmed');
            $saved = $db->get_where($table, ['name' => $marker])->row_array();
        }
        if (($saved['value'] ?? null) !== '1') throw new \RuntimeException('asaas_profile_initialization_unconfirmed');
    }
}
