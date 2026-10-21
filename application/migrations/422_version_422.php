<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Migration_Version_422 extends CI_Migration
{
    public function up(): void
    {
        require_once APPPATH.'services/CompanyInvoiceDeliverySchema.php';
        CompanyInvoiceDeliverySchema::ensure($this->db, db_prefix());
    }

    public function down(): void
    {
        // Preserve queued notifications and their audit receipts; rollback is non-destructive.
    }
}
