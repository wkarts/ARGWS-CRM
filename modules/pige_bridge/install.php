<?php

defined('BASEPATH') or exit('No direct script access allowed');
$CI = &get_instance();
$table = db_prefix() . 'pige_bridge_requests';
if (!$CI->db->table_exists($table)) {
    $CI->db->query("CREATE TABLE `{$table}` (
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
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
}
// No secrets, inferred mappings, invoice creation or global option changes.
