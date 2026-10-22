<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Migration_Version_112 extends App_module_migration
{
    public function up()
    {
        require_once __DIR__.'/../services/MaEmailTemplateAssets.php';
        $CI = &get_instance();
        // Local assets only for exact, original PT-BR library pairs from migration111.
        MaEmailTemplateAssets::upgrade($CI->db, db_prefix(), base_url());
    }
}
