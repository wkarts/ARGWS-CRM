<?php

declare(strict_types=1);

final class IssuerSchema
{
    public static function statements(string $prefix): array
    {
        if (!preg_match('/^[A-Za-z0-9_]+$/D', $prefix)) {
            throw new InvalidArgumentException('Invalid table prefix');
        }
        $p = $prefix . 'issuers_';
        // DDL is additive and idempotent. It never copies global options or customer/financial rows.
        return [
            "CREATE TABLE IF NOT EXISTS `{$p}lock` (`id` int NOT NULL PRIMARY KEY) ENGINE=InnoDB",
            "INSERT IGNORE INTO `{$p}lock` (`id`) VALUES (1)",
            "CREATE TABLE IF NOT EXISTS `{$p}registry` (
                `id` int unsigned NOT NULL AUTO_INCREMENT PRIMARY KEY,
                `legal_name` varchar(191) NOT NULL, `tax_id` varchar(14) DEFAULT NULL,
                `active` tinyint NOT NULL DEFAULT 1, `settings_json` longtext NOT NULL,
                `created_by` int NOT NULL, `created_at` datetime NOT NULL,
                UNIQUE KEY `tax_id` (`tax_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            "CREATE TABLE IF NOT EXISTS `{$p}staff` (
                `issuer_id` int unsigned NOT NULL, `staff_id` int NOT NULL,
                `can_view` tinyint NOT NULL DEFAULT 1, `can_manage_clients` tinyint NOT NULL DEFAULT 0,
                `can_share_clients` tinyint NOT NULL DEFAULT 0,
                PRIMARY KEY (`issuer_id`, `staff_id`), KEY `staff_id` (`staff_id`),
                FOREIGN KEY (`issuer_id`) REFERENCES `{$p}registry` (`id`)
            ) ENGINE=InnoDB",
            "CREATE TABLE IF NOT EXISTS `{$p}staff_defaults` (
                `staff_id` int NOT NULL PRIMARY KEY, `issuer_id` int unsigned NOT NULL,
                FOREIGN KEY (`issuer_id`, `staff_id`) REFERENCES `{$p}staff` (`issuer_id`, `staff_id`)
            ) ENGINE=InnoDB",
            "CREATE TABLE IF NOT EXISTS `{$p}clients` (
                `client_id` int NOT NULL PRIMARY KEY, `owner_issuer_id` int unsigned NOT NULL,
                `tax_id` varchar(14) DEFAULT NULL, `created_by` int NOT NULL, `created_at` datetime NOT NULL,
                UNIQUE KEY `tax_id` (`tax_id`), FOREIGN KEY (`owner_issuer_id`) REFERENCES `{$p}registry` (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            "CREATE TABLE IF NOT EXISTS `{$p}client_links` (
                `client_id` int NOT NULL, `issuer_id` int unsigned NOT NULL,
                `linked_by` int NOT NULL, `created_at` datetime NOT NULL,
                PRIMARY KEY (`client_id`, `issuer_id`), KEY `issuer_id` (`issuer_id`),
                FOREIGN KEY (`client_id`) REFERENCES `{$p}clients` (`client_id`),
                FOREIGN KEY (`issuer_id`) REFERENCES `{$p}registry` (`id`)
            ) ENGINE=InnoDB",
            "CREATE TABLE IF NOT EXISTS `{$p}audit` (
                `id` bigint unsigned NOT NULL AUTO_INCREMENT PRIMARY KEY, `actor_id` int NOT NULL,
                `event` varchar(64) NOT NULL, `issuer_id` int unsigned DEFAULT NULL,
                `subject_id` int DEFAULT NULL, `created_at` datetime NOT NULL,
                KEY `issuer_event` (`issuer_id`, `event`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        ];
    }
    /** Migration 103 only: existing memberships never inherit integration privileges. */
    public static function ensureIntegrationPermissions($db, string $prefix): void
    {
        if (!preg_match('/^[A-Za-z0-9_]+$/D', $prefix)) {
            throw new InvalidArgumentException('Invalid table prefix');
        }
        $table = $prefix . 'issuers_staff';
        // CI3 caches field_names; raw ALTER does not invalidate that metadata.
        if (property_exists($db, 'data_cache')) { unset($db->data_cache['field_names'][$table]); }
        if (!$db->field_exists('can_manage_integrations', $table)) {
            if ($db->query("ALTER TABLE `{$table}` ADD `can_manage_integrations` tinyint NOT NULL DEFAULT 0") === false) {
                throw new RuntimeException('issuer_integration_permission_migration_failed');
            }
            if (property_exists($db, 'data_cache')) { unset($db->data_cache['field_names'][$table]); }
        }
        if (!$db->field_exists('can_manage_integrations', $table)) {
            throw new RuntimeException('issuer_integration_permission_migration_unconfirmed');
        }
        $result = $db->query('SELECT COLUMN_TYPE,IS_NULLABLE,COLUMN_DEFAULT FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=?', [$table, 'can_manage_integrations']);
        $columns = $result === false ? [] : $result->result_array();
        $column = count($columns) === 1 ? $columns[0] : [];
        if (!preg_match('/^tinyint(?:\([0-9]+\))?$/iD', trim((string) ($column['COLUMN_TYPE'] ?? '')))
            || ($column['IS_NULLABLE'] ?? null) !== 'NO'
            || !array_key_exists('COLUMN_DEFAULT', $column)
            || (string) $column['COLUMN_DEFAULT'] !== '0') {
            throw new RuntimeException('issuer_integration_permission_schema_invalid');
        }
    }


    public static function documentStatements(string $prefix): array
    {
        if (!preg_match('/^[A-Za-z0-9_]+$/D', $prefix)) {
            throw new InvalidArgumentException('Invalid table prefix');
        }
        $p = $prefix . 'issuers_';
        return [
            "CREATE TABLE IF NOT EXISTS `{$p}sequences` (
                `issuer_id` int unsigned NOT NULL, `series` varchar(32) NOT NULL,
                `next_number` bigint unsigned NOT NULL,
                PRIMARY KEY (`issuer_id`, `series`),
                FOREIGN KEY (`issuer_id`) REFERENCES `{$p}registry` (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            "CREATE TABLE IF NOT EXISTS `{$p}invoice_documents` (
                `invoice_id` int NOT NULL PRIMARY KEY, `issuer_id` int unsigned NOT NULL,
                `series` varchar(32) NOT NULL, `number` bigint unsigned NOT NULL,
                `prefix` varchar(50) NOT NULL, `formatted_number` varchar(191) NOT NULL,
                `legal_identity_json` longtext NOT NULL, `recipient_identity_json` longtext NOT NULL,
                `created_at` datetime NOT NULL,
                UNIQUE KEY `issuer_series_number` (`issuer_id`, `series`, `number`),
                FOREIGN KEY (`invoice_id`) REFERENCES `{$prefix}invoices` (`id`),
                FOREIGN KEY (`issuer_id`) REFERENCES `{$p}registry` (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        ];
    }
}
