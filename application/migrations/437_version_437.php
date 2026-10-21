<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Migration_Version_437 extends CI_Migration
{
    public function up(): void
    {
        require_once APPPATH.'services/CompanyProjectDiscussionsSchema.php';
        CompanyProjectDiscussionsSchema::ensure($this->db,db_prefix());
        require_once APPPATH.'services/CompanyMicrosoftMailOAuthSchema.php';
        CompanyMicrosoftMailOAuthSchema::ensure($this->db,db_prefix());
        require_once APPPATH.'services/CompanyAsaasRefundSchema.php';
        CompanyAsaasRefundSchema::ensure($this->db,db_prefix());
        require_once APPPATH.'services/CompanyStandaloneBillingSchema.php';
        CompanyStandaloneBillingSchema::ensure($this->db,db_prefix());
    }
    public function down(): void { /* Preserve discussion, mail, billing and financial declarations. */ }
}
