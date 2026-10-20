<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Migration_Version_438 extends CI_Migration
{
    public function up(): void
    {
        require_once APPPATH.'services/CompanyProjectDiscussionExtrasSchema.php';
        CompanyProjectDiscussionExtrasSchema::ensure($this->db,db_prefix());
        require_once APPPATH.'services/CompanyTaskTimeBillingSchema.php';
        CompanyTaskTimeBillingSchema::ensure($this->db,db_prefix());
        require_once APPPATH.'services/CompanyInvoiceRecurringSchema.php';
        CompanyInvoiceRecurringSchema::ensure($this->db,db_prefix());
        require_once APPPATH.'services/CompanyConnectApiSchema.php';
        CompanyConnectApiSchema::ensure($this->db,db_prefix());
        require_once APPPATH.'services/CompanyConnectInstanceOperationsSchema.php';
        CompanyConnectInstanceOperationsSchema::ensure($this->db,db_prefix());
    }
    public function down(): void { /* Preserve versioned discussion, time allocations, recurrence and connector evidence. */ }
}
