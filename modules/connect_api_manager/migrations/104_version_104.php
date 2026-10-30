<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Migration_Version_104 extends App_module_migration
{
    public function up()
    {
        add_option('connect_api_manager_scope', 'module');

        $hash = trim((string) get_option('connect_api_manager_namespace_hash'));
        if ($hash === '') {
            try {
                $hash = substr(bin2hex(random_bytes(4)), 0, 6);
            } catch (Throwable $e) {
                $hash = substr(hash('sha256', uniqid((string) mt_rand(), true)), 0, 6);
            }

            add_option('connect_api_manager_namespace_hash', $hash);
            if (trim((string) get_option('connect_api_manager_namespace_hash')) === '') {
                update_option('connect_api_manager_namespace_hash', $hash);
            }
        }
    }
}
