<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Migration_Version_111 extends App_module_migration
{
    public function up()
    {
        require_once __DIR__.'/../services/MaEmailTemplateDefaults.php';
        $CI = &get_instance();
        MaEmailTemplateDefaults::upgrade($CI->db, db_prefix());
    }
}
