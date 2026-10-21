<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Migration_Version_435 extends CI_Migration
{
    public function up(): void
    {
        require_once APPPATH.'services/CompanyClientImportSchema.php';
        require_once APPPATH.'services/CompanyExpenseBillingSchema.php';
        CompanyClientImportSchema::ensure($this->db,db_prefix());
        CompanyExpenseBillingSchema::ensure($this->db,db_prefix());
    }

    public function down(): void
    {
        // Preserve private import batches, immutable billing origins and invoice links.
    }
}
