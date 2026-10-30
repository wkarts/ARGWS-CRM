<?php

defined('BASEPATH') or exit('No direct script access allowed');
class Migration_Version_425 extends CI_Migration
{
    public function up(): void
    {
        require_once APPPATH.'services/CompanyContractsSchema.php';
        require_once APPPATH.'services/CompanySalesSchema.php';
        require_once APPPATH.'services/CompanyApiSchema.php';
        require_once APPPATH.'services/CompanyProjectExtrasSchema.php';
        require_once APPPATH.'services/CompanyStandaloneTasksSchema.php';
        CompanyContractsSchema::ensure($this->db,db_prefix());
        CompanySalesSchema::ensure($this->db,db_prefix());
        CompanyApiSchema::ensure($this->db,db_prefix());
        CompanyProjectExtrasSchema::ensure($this->db,db_prefix());
        CompanyStandaloneTasksSchema::ensure($this->db,db_prefix());
    }
    public function down(): void { /* Origin, documents, conversations and recurring evidence retained. */ }
}
