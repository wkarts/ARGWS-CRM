<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Migration_Version_103 extends App_module_migration
{
    public function up()
    {
        $CI = &get_instance();
        $table = db_prefix() . 'connect_api_chat_staff_instances';
        if (!$CI->db->table_exists($table)) {
            $CI->db->query("CREATE TABLE `{$table}` (
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
        add_option('connect_api_chat_multi_instance_enabled', '1');
    }
}
