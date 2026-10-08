<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Migration_Version_100 extends App_module_migration
{
    public function up()
    {
        $CI = &get_instance();

        add_option('connect_api_manager_api_url', '');
        add_option('connect_api_manager_admin_token', '');
        add_option('connect_api_manager_verify_tls', '1');
        add_option('connect_api_manager_timeout', '30');

        $table = db_prefix() . 'argws_connect_manager_instances';
        if (!$CI->db->table_exists($table)) {
            $CI->db->query('CREATE TABLE `' . $table . '` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `instance_name` VARCHAR(191) NOT NULL,
                `instance_token` MEDIUMTEXT NULL,
                `integration` VARCHAR(64) NULL,
                `last_state` VARCHAR(64) NULL,
                `created_at` DATETIME NOT NULL,
                `updated_at` DATETIME NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uq_argws_connect_manager_instance_name` (`instance_name`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;');
        }
    }
}
