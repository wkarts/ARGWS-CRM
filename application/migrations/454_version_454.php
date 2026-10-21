<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Migration_Version_454 extends CI_Migration
{
    public function up(): void
    {
        require_once APPPATH.'services/CompanyLeadsSchema.php';
        $ci = &get_instance();
        CompanyLeadsSchema::ensure($ci->db, db_prefix());
    }

    public function down(): void
    {
        // Preserve private leads, conversion history, receipts and worker origin.
        // A downgrade must never erase business records or silently adopt legacy leads.
    }
}
