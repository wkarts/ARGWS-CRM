<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Migration_Version_112 extends App_module_migration
{
    public function up()
    {
        $CI = &get_instance();
        $p = db_prefix();
        $queue = $p . 'connect_api_chat_inbound_queue';
        $messages = $p . 'connect_api_chat_messages';

        if (!$CI->db->table_exists($queue)) {
            $CI->db->query("CREATE TABLE `{$queue}` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `event_key` CHAR(64) NOT NULL,
                `instance_name` VARCHAR(191) NOT NULL,
                `event_name` VARCHAR(100) NOT NULL,
                `payload` LONGTEXT NOT NULL,
                `status` VARCHAR(20) NOT NULL DEFAULT 'pending',
                `attempts` INT UNSIGNED NOT NULL DEFAULT 0,
                `last_error` VARCHAR(1000) NULL,
                `created_at` DATETIME NOT NULL,
                `updated_at` DATETIME NULL,
                `processed_at` DATETIME NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uq_connect_chat_inbound_event` (`event_key`),
                KEY `idx_connect_chat_inbound_pending` (`instance_name`,`status`,`id`),
                KEY `idx_connect_chat_inbound_created` (`created_at`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
        }

        if ($CI->db->table_exists($messages)) {
            // Remove referências antigas que apontam para hosts privados do Connect|API.
            // O raw_payload preserva metadados necessários; o browser usa apenas o proxy/local storage do Chat.
            $CI->db->query("UPDATE `{$messages}` SET `media_url`=NULL WHERE `media_url` LIKE 'http://minio-%' OR `media_url` LIKE 'https://minio-%' OR `media_url` LIKE 'https://mmg.whatsapp.net/%' OR `media_url` LIKE 'http://mmg.whatsapp.net/%'");
            $indexes = $CI->db->query("SHOW INDEX FROM `{$messages}` WHERE Key_name='idx_connect_chat_timeline'")->result_array();
            if (empty($indexes)) {
                $CI->db->query("ALTER TABLE `{$messages}` ADD KEY `idx_connect_chat_timeline` (`instance_name`,`conversation_id`,`message_timestamp`,`id`)");
            }
        }

        add_option('connect_api_chat_media_storage_mode', 'local_on_demand');
        add_option('connect_api_chat_inbound_queue_enabled', '1');
        add_option('connect_api_chat_watchdog_seconds', '10');
    }
}
