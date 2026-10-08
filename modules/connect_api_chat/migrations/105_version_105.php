<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Migration_Version_105 extends App_module_migration
{
    public function up()
    {
        $CI = &get_instance();
        $table = db_prefix() . 'connect_api_chat_conversations';
        if ($CI->db->table_exists($table)) {
            if (!$CI->db->field_exists('closed_at', $table)) {
                $CI->db->query("ALTER TABLE `{$table}` ADD `closed_at` DATETIME NULL AFTER `status`");
            }
            if (!$CI->db->field_exists('closed_by_staff_id', $table)) {
                $CI->db->query("ALTER TABLE `{$table}` ADD `closed_by_staff_id` INT NULL AFTER `closed_at`");
            }
        }
    }
}
