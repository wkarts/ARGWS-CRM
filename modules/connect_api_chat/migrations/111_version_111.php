<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Migration_Version_111 extends App_module_migration
{
    public function up()
    {
        $CI = &get_instance();
        $p = db_prefix();
        $messages = $p . 'connect_api_chat_messages';

        if ($CI->db->table_exists($messages)) {
            if (!$CI->db->field_exists('live_received_at', $messages)) {
                $CI->db->query("ALTER TABLE `{$messages}` ADD `live_received_at` DATETIME NULL AFTER `message_timestamp`");
            }

            $idx = $CI->db->query("SHOW INDEX FROM `{$messages}` WHERE Key_name='idx_connect_chat_live_inbound'")->row_array();
            if (!$idx) {
                $CI->db->query("ALTER TABLE `{$messages}` ADD KEY `idx_connect_chat_live_inbound` (`instance_name`,`from_me`,`live_received_at`,`id`)");
            }
        }

        add_option('connect_api_chat_last_inbound_at', '');
        add_option('connect_api_chat_last_inbound_message_id', '');
        add_option('connect_api_chat_last_watchdog_at', '0');
    }
}
