<?php

defined('BASEPATH') or exit('No direct script access allowed');
class Migration_Version_424 extends CI_Migration
{
    public function up(): void
    {
        require_once APPPATH.'services/CompanyCreditSchema.php';
        require_once APPPATH.'services/CompanyProjectsSchema.php';
        require_once APPPATH.'services/CompanyExpenseSchema.php';
        CompanyCreditSchema::ensure($this->db,db_prefix());
        CompanyProjectsSchema::ensure($this->db,db_prefix());
        CompanyExpenseSchema::ensure($this->db,db_prefix());
    }
    public function down(): void
    {
        // Documents, ownership, activity and immutable evidence remain available for forward repair.
    }
}
