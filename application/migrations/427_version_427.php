<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Migration_Version_427 extends CI_Migration
{
    public function up(): void
    {
        require_once APPPATH.'services/CompanyOperationFilesSchema.php';
        require_once APPPATH.'services/CompanyBankComponentsSchema.php';
        CompanyOperationFilesSchema::ensure($this->db,db_prefix());
        CompanyBankComponentsSchema::ensure($this->db,db_prefix());
    }

    public function down(): void
    {
        // Retain operational files and bank-component evidence; no destructive downgrade.
    }
}
