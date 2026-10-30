<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Migration_Version_453 extends CI_Migration
{
    public function up(): void
    {
        require_once APPPATH.'services/CompanyStaffSchema.php';
        require_once APPPATH.'services/CompanyTaskRecurringSchema.php';
        $ci = &get_instance();
        CompanyStaffSchema::ensure($ci->db, db_prefix());
        CompanyTaskRecurringSchema::ensure($ci->db, db_prefix());
    }

    public function down(): void
    {
        // Durable staff commands and task recurrence history are intentionally
        // retained. A downgrade must never delete operational history.
    }
}
