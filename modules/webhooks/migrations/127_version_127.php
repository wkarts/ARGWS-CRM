<?php

defined('BASEPATH') || exit('No direct script access allowed');

class Migration_Version_127 extends App_module_migration
{
    public function up()
    {
        $CI = &get_instance();
        require_once APPPATH.'services/EmailTemplateReconciler.php';
        EmailTemplateReconciler::repairWebhookDefaults($CI->db, db_prefix());
    }
}
