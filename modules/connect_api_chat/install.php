<?php

defined('BASEPATH') or exit('No direct script access allowed');

if (!function_exists('connect_api_chat_install_schema')) {
    function connect_api_chat_install_schema()
    {
        $CI = &get_instance();
        $p = db_prefix();

        add_option('connect_api_chat_all_staff', '1');
        add_option('connect_api_chat_auto_claim_on_send', '1');
        add_option('connect_api_chat_notify_unassigned', '1');
        add_option('connect_api_chat_poll_interval', '3');
        add_option('connect_api_chat_realtime_poll_ms', '1000');
        add_option('connect_api_chat_last_inbound_at', '');
        add_option('connect_api_chat_last_inbound_message_id', '');
        add_option('connect_api_chat_last_watchdog_at', '0');
        add_option('connect_api_chat_media_storage_mode', 'local_on_demand');
        add_option('connect_api_chat_inbound_queue_enabled', '1');
        add_option('connect_api_chat_watchdog_seconds', '10');
        add_option('connect_api_chat_page_size', '30');
        add_option('connect_api_chat_upload_limit_mb', '16');
        add_option('connect_api_chat_api_sync_interval', '60');
        add_option('connect_api_chat_webhook_secret', bin2hex(random_bytes(24)));
        add_option('connect_api_chat_webhook_configured_instance', '');
        add_option('connect_api_chat_forward_webhook_url', '');
        add_option('connect_api_chat_debug_enabled', '0');
        add_option('connect_api_chat_debug_retention', '300');
        add_option('connect_api_chat_pwa_enabled', '1');
        add_option('connect_api_chat_multi_instance_enabled', '1');

        $contacts = $p . 'connect_api_chat_contacts';
        if (!$CI->db->table_exists($contacts)) {
            $CI->db->query("CREATE TABLE `{$contacts}` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `instance_name` VARCHAR(191) NOT NULL,
                `remote_jid` VARCHAR(191) NOT NULL,
                `remote_jid_alt` VARCHAR(191) NULL,
                `identity_key` VARCHAR(191) NULL,
                `phone_number` VARCHAR(32) NULL,
                `display_name` VARCHAR(191) NULL,
                `profile_pic_url` TEXT NULL,
                `is_group` TINYINT(1) NOT NULL DEFAULT 0,
                `created_at` DATETIME NOT NULL,
                `updated_at` DATETIME NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uq_connect_chat_contact` (`instance_name`,`remote_jid`),
                KEY `idx_connect_chat_contact_phone` (`phone_number`),
                KEY `idx_connect_chat_contact_identity` (`instance_name`,`identity_key`),
                KEY `idx_connect_chat_contact_alt` (`instance_name`,`remote_jid_alt`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
        }

        $conversations = $p . 'connect_api_chat_conversations';
        if (!$CI->db->table_exists($conversations)) {
            $CI->db->query("CREATE TABLE `{$conversations}` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `instance_name` VARCHAR(191) NOT NULL,
                `remote_jid` VARCHAR(191) NOT NULL,
                `remote_jid_alt` VARCHAR(191) NULL,
                `identity_key` VARCHAR(191) NULL,
                `contact_id` BIGINT UNSIGNED NULL,
                `title` VARCHAR(191) NULL,
                `assigned_staff_id` INT NULL,
                `status` VARCHAR(32) NOT NULL DEFAULT 'open',
                `current_ticket_id` BIGINT UNSIGNED NULL,
                `ticket_counter` INT UNSIGNED NOT NULL DEFAULT 0,
                `new_ticket_flag` TINYINT(1) NOT NULL DEFAULT 0,
                `closed_at` DATETIME NULL,
                `closed_by_staff_id` INT NULL,
                `last_message_at` DATETIME NULL,
                `last_message_preview` TEXT NULL,
                `last_message_from_me` TINYINT(1) NOT NULL DEFAULT 0,
                `created_by_staff_id` INT NULL,
                `created_at` DATETIME NOT NULL,
                `updated_at` DATETIME NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uq_connect_chat_conversation` (`instance_name`,`remote_jid`),
                KEY `idx_connect_chat_assignment` (`instance_name`,`assigned_staff_id`,`status`),
                KEY `idx_connect_chat_fast_list` (`instance_name`,`status`,`assigned_staff_id`,`last_message_at`),
                KEY `idx_connect_chat_current_ticket` (`current_ticket_id`),
                KEY `idx_connect_chat_last_message` (`instance_name`,`last_message_at`),
                KEY `idx_connect_chat_contact_id` (`contact_id`),
                KEY `idx_connect_chat_conversation_identity` (`instance_name`,`identity_key`),
                KEY `idx_connect_chat_conversation_alt` (`instance_name`,`remote_jid_alt`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
        }

        $messages = $p . 'connect_api_chat_messages';
        if (!$CI->db->table_exists($messages)) {
            $CI->db->query("CREATE TABLE `{$messages}` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `instance_name` VARCHAR(191) NOT NULL,
                `conversation_id` BIGINT UNSIGNED NOT NULL,
                `ticket_id` BIGINT UNSIGNED NULL,
                `api_message_id` VARCHAR(191) NOT NULL,
                `remote_jid` VARCHAR(191) NOT NULL,
                `remote_jid_alt` VARCHAR(191) NULL,
                `participant_jid` VARCHAR(191) NULL,
                `from_me` TINYINT(1) NOT NULL DEFAULT 0,
                `sender_staff_id` INT NULL,
                `sender_name` VARCHAR(191) NULL,
                `message_type` VARCHAR(64) NOT NULL DEFAULT 'text',
                `text_content` LONGTEXT NULL,
                `media_url` LONGTEXT NULL,
                `local_media_path` VARCHAR(512) NULL,
                `caption` LONGTEXT NULL,
                `file_name` VARCHAR(255) NULL,
                `mimetype` VARCHAR(191) NULL,
                `thumbnail_base64` MEDIUMTEXT NULL,
                `media_duration` INT UNSIGNED NULL,
                `media_waveform` TEXT NULL,
                `media_width` INT UNSIGNED NULL,
                `media_height` INT UNSIGNED NULL,
                `message_timestamp` BIGINT UNSIGNED NOT NULL,
                `live_received_at` DATETIME NULL,
                `status` VARCHAR(64) NULL,
                `quoted_api_message_id` VARCHAR(191) NULL,
                `is_deleted` TINYINT(1) NOT NULL DEFAULT 0,
                `raw_payload` LONGTEXT NULL,
                `created_at` DATETIME NOT NULL,
                `updated_at` DATETIME NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uq_connect_chat_api_message` (`instance_name`,`api_message_id`),
                KEY `idx_connect_chat_conversation_msg` (`conversation_id`,`id`),
                KEY `idx_connect_chat_poll` (`instance_name`,`conversation_id`,`id`),
                KEY `idx_connect_chat_live_inbound` (`instance_name`,`from_me`,`live_received_at`,`id`),
                KEY `idx_connect_chat_message_ticket` (`ticket_id`,`id`),
                KEY `idx_connect_chat_remote_msg` (`instance_name`,`remote_jid`,`message_timestamp`),
                KEY `idx_connect_chat_timeline` (`instance_name`,`conversation_id`,`message_timestamp`,`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
        }

        $tickets = $p . 'connect_api_chat_tickets';
        if (!$CI->db->table_exists($tickets)) {
            $CI->db->query("CREATE TABLE `{$tickets}` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `instance_name` VARCHAR(191) NOT NULL,
                `conversation_id` BIGINT UNSIGNED NOT NULL,
                `ticket_number` INT UNSIGNED NOT NULL,
                `status` VARCHAR(32) NOT NULL DEFAULT 'open',
                `opened_via` VARCHAR(32) NOT NULL DEFAULT 'manual',
                `source_api_message_id` VARCHAR(191) NULL,
                `opened_by_staff_id` INT NULL,
                `opened_at` DATETIME NOT NULL,
                `closed_at` DATETIME NULL,
                `closed_by_staff_id` INT NULL,
                `is_new` TINYINT(1) NOT NULL DEFAULT 0,
                `created_at` DATETIME NOT NULL,
                `updated_at` DATETIME NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uq_connect_chat_ticket_number` (`conversation_id`,`ticket_number`),
                KEY `idx_connect_chat_ticket_status` (`instance_name`,`status`,`opened_at`),
                KEY `idx_connect_chat_ticket_source` (`instance_name`,`conversation_id`,`source_api_message_id`),
                KEY `idx_connect_chat_ticket_conversation` (`conversation_id`,`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
        }

        $reads = $p . 'connect_api_chat_reads';
        if (!$CI->db->table_exists($reads)) {
            $CI->db->query("CREATE TABLE `{$reads}` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `conversation_id` BIGINT UNSIGNED NOT NULL,
                `staff_id` INT NOT NULL,
                `last_read_message_id` BIGINT UNSIGNED NULL,
                `last_read_at` DATETIME NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uq_connect_chat_read` (`conversation_id`,`staff_id`),
                KEY `idx_connect_chat_read_staff` (`staff_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
        }

        $assignments = $p . 'connect_api_chat_assignments';
        if (!$CI->db->table_exists($assignments)) {
            $CI->db->query("CREATE TABLE `{$assignments}` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `conversation_id` BIGINT UNSIGNED NOT NULL,
                `ticket_id` BIGINT UNSIGNED NULL,
                `from_staff_id` INT NULL,
                `to_staff_id` INT NULL,
                `action` VARCHAR(32) NOT NULL,
                `note` TEXT NULL,
                `created_by_staff_id` INT NULL,
                `created_at` DATETIME NOT NULL,
                PRIMARY KEY (`id`),
                KEY `idx_connect_chat_assignment_history` (`conversation_id`,`created_at`),
                KEY `idx_connect_chat_assignment_ticket` (`ticket_id`,`created_at`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
        }

        $events = $p . 'connect_api_chat_webhook_events';
        if (!$CI->db->table_exists($events)) {
            $CI->db->query("CREATE TABLE `{$events}` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `event_key` CHAR(64) NOT NULL,
                `instance_name` VARCHAR(191) NOT NULL,
                `event_name` VARCHAR(100) NOT NULL,
                `attempts` INT UNSIGNED NOT NULL DEFAULT 1,
                `created_at` DATETIME NOT NULL,
                `processed_at` DATETIME NULL,
                `last_error` VARCHAR(1000) NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uq_connect_chat_event_key` (`event_key`),
                KEY `idx_connect_chat_event_created` (`created_at`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
        }


        $staffInstances = $p . 'connect_api_chat_staff_instances';
        if (!$CI->db->table_exists($staffInstances)) {
            $CI->db->query("CREATE TABLE `{$staffInstances}` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `staff_id` INT NOT NULL,
                `instance_name` VARCHAR(191) NOT NULL,
                `is_default` TINYINT(1) NOT NULL DEFAULT 0,
                `created_at` DATETIME NOT NULL,
                `updated_at` DATETIME NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uq_connect_chat_staff_instance` (`staff_id`,`instance_name`),
                KEY `idx_connect_chat_staff_default` (`staff_id`,`is_default`),
                KEY `idx_connect_chat_instance_staff` (`instance_name`,`staff_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
        }

        $realtime = $p . 'connect_api_chat_realtime_events';
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

        $inboundQueue = $p . 'connect_api_chat_inbound_queue';
        if (!$CI->db->table_exists($inboundQueue)) {
            $CI->db->query("CREATE TABLE `{$inboundQueue}` (
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

        $debug = $p . 'connect_api_chat_debug_logs';
        if (!$CI->db->table_exists($debug)) {
            $CI->db->query("CREATE TABLE `{$debug}` (
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
