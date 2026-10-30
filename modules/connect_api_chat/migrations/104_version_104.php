<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Migration_Version_104 extends App_module_migration
{
    public function up()
    {
        $CI = &get_instance();
        $messages = db_prefix() . 'connect_api_chat_messages';
        if ($CI->db->table_exists($messages) && !$CI->db->field_exists('local_media_path', $messages)) {
            $CI->db->query('ALTER TABLE `' . $messages . '` ADD `local_media_path` VARCHAR(512) NULL AFTER `media_url`');
        }
        add_option('connect_api_chat_api_sync_interval', '60');
    }
}
