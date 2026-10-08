<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Migration_Version_109 extends App_module_migration
{
    public function up()
    {
        $CI = &get_instance();
        $p = db_prefix();
        $realtime = $p . 'connect_api_chat_realtime_events';
        $conversations = $p . 'connect_api_chat_conversations';
        $messages = $p . 'connect_api_chat_messages';

        add_option('connect_api_chat_realtime_poll_ms', '1000');

        if (!$CI->db->table_exists($realtime)) {
            $CI->db->query("CREATE TABLE `{$realtime}` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `instance_name` VARCHAR(191) NOT NULL,
                `conversation_id` BIGINT UNSIGNED NULL,
                `ticket_id` BIGINT UNSIGNED NULL,
                `message_id` BIGINT UNSIGNED NULL,
                `event_type` VARCHAR(64) NOT NULL,
                `created_at` DATETIME NOT NULL,
                PRIMARY KEY (`id`),
                KEY `idx_connect_chat_rt_instance` (`instance_name`,`id`),
                KEY `idx_connect_chat_rt_conversation` (`conversation_id`,`id`),
                KEY `idx_connect_chat_rt_created` (`created_at`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
        }

        if ($CI->db->table_exists($conversations)) {
            $idx = $CI->db->query("SHOW INDEX FROM `{$conversations}` WHERE Key_name = 'idx_connect_chat_fast_list'")->row_array();
            if (!$idx) {
                $CI->db->query("ALTER TABLE `{$conversations}` ADD KEY `idx_connect_chat_fast_list` (`instance_name`,`status`,`assigned_staff_id`,`last_message_at`)");
            }
        }

        if ($CI->db->table_exists($messages)) {
            $idx = $CI->db->query("SHOW INDEX FROM `{$messages}` WHERE Key_name = 'idx_connect_chat_poll'")->row_array();
            if (!$idx) {
                $CI->db->query("ALTER TABLE `{$messages}` ADD KEY `idx_connect_chat_poll` (`instance_name`,`conversation_id`,`id`)");
            }
        }
    }
}
