<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Migration_Version_447 extends CI_Migration
{
    public function up(): void
    {
        require_once APPPATH . 'services/CompanyLocalNoncashSchema.php';
        CompanyLocalNoncashSchema::ensure($this->db, db_prefix());
    }

    public function down(): void
    {
        // Preserve append-only LOCAL declarations, restorations and independent-funds evidence.
        // A rollback closes the new writers; it never drops financial history.
    }
}
