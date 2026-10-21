<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Migration_Version_460 extends CI_Migration
{
    public function up(): void
    {
        require_once APPPATH.'services/EmailTemplateReconciler.php';
        $ci = &get_instance();
        EmailTemplateReconciler::upgrade($ci->db, db_prefix());
    }

    public function down(): void
    {
        // Preserve translated templates, user customizations and language variants.
    }
}
