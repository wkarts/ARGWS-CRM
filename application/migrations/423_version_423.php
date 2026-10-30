<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Migration_Version_423 extends CI_Migration
{
    public function up(): void
    {
        require_once APPPATH.'services/CompanyInvoiceAttachmentSchema.php';
        CompanyInvoiceAttachmentSchema::ensure($this->db, db_prefix());
    }

    public function down(): void
    {
        // Private attachments and provenance are retained; no destructive downgrade.
    }
}
