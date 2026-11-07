<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Migration_Version_492 extends CI_Migration
{
    public function up(): void
    {
        require_once APPPATH.'services/CompanyInvoiceRepricingSchema.php';
        CompanyInvoiceRepricingSchema::ensure($this->db, db_prefix());
    }

    public function down(): void
    {
        // Preserve proposals, reviewed links and historical price snapshots on rollback.
    }
}
