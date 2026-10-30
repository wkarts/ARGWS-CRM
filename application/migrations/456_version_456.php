<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Migration_Version_456 extends CI_Migration
{
    public function up(): void
    {
        require_once APPPATH.'services/CompanyHrSchema.php';
        $ci = &get_instance();
        CompanyHrSchema::ensure($ci->db, db_prefix());
    }

    public function down(): void
    {
        // Preserve employment, sensitive records, contracts, training and access history.
    }
}
