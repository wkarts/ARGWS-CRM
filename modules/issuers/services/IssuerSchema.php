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
                `legal_name` varchar(191) NOT NULL, `tax_id` varchar(14) NOT NULL,
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
}
