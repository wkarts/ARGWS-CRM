<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Migration_Version_105 extends App_module_migration
{
    public function up()
    {
        $CI = &get_instance();
        $table = db_prefix() . 'argws_connect_manager_instances';

        if ($CI->db->table_exists($table) && !$CI->db->field_exists('phone_number', $table)) {
            $CI->db->query('ALTER TABLE `' . $table . '` ADD `phone_number` VARCHAR(32) NULL AFTER `integration`');
        }
    }
}
