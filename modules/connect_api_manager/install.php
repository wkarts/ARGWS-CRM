<?php

defined('BASEPATH') or exit('No direct script access allowed');

function connect_api_manager_install()
{
    $CI = &get_instance();

    add_option('connect_api_manager_api_url', '');
    add_option('connect_api_manager_admin_token', '');
    add_option('connect_api_manager_verify_tls', '1');
    add_option('connect_api_manager_timeout', '30');
    add_option('connect_api_manager_scope', 'module');

    $hash = trim((string) get_option('connect_api_manager_namespace_hash'));
    if ($hash === '') {
        $hash = connect_api_manager_generate_namespace_hash();
        add_option('connect_api_manager_namespace_hash', $hash);
        if (trim((string) get_option('connect_api_manager_namespace_hash')) === '') {
            update_option('connect_api_manager_namespace_hash', $hash);
        }
    }

    $table = db_prefix() . 'argws_connect_manager_instances';
    if (!$CI->db->table_exists($table)) {
        $CI->db->query('CREATE TABLE `' . $table . '` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `instance_name` VARCHAR(191) NOT NULL,
            `instance_token` MEDIUMTEXT NULL,
            `integration` VARCHAR(64) NULL,
            `phone_number` VARCHAR(32) NULL,
            `last_state` VARCHAR(64) NULL,
            `created_at` DATETIME NOT NULL,
            `updated_at` DATETIME NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uq_argws_connect_manager_instance_name` (`instance_name`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;');
    }

    if ($CI->db->table_exists($table) && !$CI->db->field_exists('phone_number', $table)) {
        $CI->db->query('ALTER TABLE `' . $table . '` ADD `phone_number` VARCHAR(32) NULL AFTER `integration`');
    }
}

if (!function_exists('connect_api_manager_generate_namespace_hash')) {
    function connect_api_manager_generate_namespace_hash()
    {
        try {
            return substr(bin2hex(random_bytes(4)), 0, 6);
        } catch (Throwable $e) {
            return substr(hash('sha256', uniqid((string) mt_rand(), true)), 0, 6);
        }
    }
}
