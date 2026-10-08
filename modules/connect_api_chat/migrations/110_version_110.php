<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Migration_Version_110 extends App_module_migration
{
    public function up()
    {
        $CI=&get_instance();$p=db_prefix();
        $tickets=$p.'connect_api_chat_tickets';$messages=$p.'connect_api_chat_messages';$events=$p.'connect_api_chat_webhook_events';
        if($CI->db->table_exists($tickets)){
            if(!$CI->db->field_exists('source_api_message_id',$tickets))$CI->db->query("ALTER TABLE `{$tickets}` ADD `source_api_message_id` VARCHAR(191) NULL AFTER `opened_via`");
            $idx=$CI->db->query("SHOW INDEX FROM `{$tickets}` WHERE Key_name='idx_connect_chat_ticket_source'")->row_array();
            if(!$idx)$CI->db->query("ALTER TABLE `{$tickets}` ADD KEY `idx_connect_chat_ticket_source` (`instance_name`,`conversation_id`,`source_api_message_id`)");
        }
        if($CI->db->table_exists($messages)){
            if(!$CI->db->field_exists('thumbnail_base64',$messages))$CI->db->query("ALTER TABLE `{$messages}` ADD `thumbnail_base64` MEDIUMTEXT NULL AFTER `mimetype`");
            if(!$CI->db->field_exists('media_duration',$messages))$CI->db->query("ALTER TABLE `{$messages}` ADD `media_duration` INT UNSIGNED NULL AFTER `thumbnail_base64`");
            if(!$CI->db->field_exists('media_waveform',$messages))$CI->db->query("ALTER TABLE `{$messages}` ADD `media_waveform` TEXT NULL AFTER `media_duration`");
            if(!$CI->db->field_exists('media_width',$messages))$CI->db->query("ALTER TABLE `{$messages}` ADD `media_width` INT UNSIGNED NULL AFTER `media_waveform`");
            if(!$CI->db->field_exists('media_height',$messages))$CI->db->query("ALTER TABLE `{$messages}` ADD `media_height` INT UNSIGNED NULL AFTER `media_width`");
        }
        if($CI->db->table_exists($events)){
            if(!$CI->db->field_exists('attempts',$events))$CI->db->query("ALTER TABLE `{$events}` ADD `attempts` INT UNSIGNED NOT NULL DEFAULT 1 AFTER `event_name`");
            if(!$CI->db->field_exists('processed_at',$events))$CI->db->query("ALTER TABLE `{$events}` ADD `processed_at` DATETIME NULL AFTER `created_at`");
            if(!$CI->db->field_exists('last_error',$events))$CI->db->query("ALTER TABLE `{$events}` ADD `last_error` VARCHAR(1000) NULL AFTER `processed_at`");
        }
        add_option('connect_api_chat_realtime_poll_ms','1000');
    }
}
