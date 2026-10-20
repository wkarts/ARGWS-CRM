<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Migration_Version_443 extends CI_Migration
{
    public function up(): void
    {
        require_once APPPATH.'services/CompanySalesMutationsSchema.php';
        CompanySalesMutationsSchema::ensure($this->db, db_prefix());
    }

    public function down(): void
    {
        // Preserve audited sales revisions and reviewed copy receipts.
    }
}
