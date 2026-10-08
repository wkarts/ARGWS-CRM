<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Migration_Version_101 extends App_module_migration
{
    public function up()
    {
        $CI = &get_instance();
        add_option('connect_api_chat_debug_enabled', '0');
        add_option('connect_api_chat_debug_retention', '300');
        add_option('connect_api_chat_pwa_enabled', '1');

        $table = db_prefix() . 'connect_api_chat_debug_logs';
        if (!$CI->db->table_exists($table)) {
            $CI->db->query("CREATE TABLE `{$table}` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `level` VARCHAR(16) NOT NULL,
                `source` VARCHAR(120) NOT NULL,
                `message` VARCHAR(1000) NOT NULL,
                `http_status` INT NULL,
                `duration_ms` INT NULL,
                `context_json` LONGTEXT NULL,
                `created_at` DATETIME NOT NULL,
                PRIMARY KEY (`id`),
                KEY `idx_connect_chat_debug_created` (`created_at`),
                KEY `idx_connect_chat_debug_level` (`level`,`created_at`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
        }
    }
}
