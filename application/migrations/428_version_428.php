<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Migration_Version_428 extends CI_Migration
{
    public function up(): void
    {
        require_once APPPATH.'services/CompanyBankReversalSchema.php';
        require_once APPPATH.'services/CompanyPortalOperationsSchema.php';
        CompanyBankReversalSchema::ensure($this->db,db_prefix());
        CompanyPortalOperationsSchema::ensure($this->db,db_prefix());
    }

    public function down(): void
    {
        // Preserve publication history and private originals; no destructive downgrade.
    }
}
