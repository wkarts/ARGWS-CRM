<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Migration_Version_436 extends CI_Migration
{
    public function up(): void
    {
        require_once APPPATH.'services/CompanyTaskBillingSchema.php';
        CompanyTaskBillingSchema::ensure($this->db,db_prefix());
        require_once APPPATH.'services/CompanyProjectBillingRateSchema.php';
        CompanyProjectBillingRateSchema::ensure($this->db,db_prefix());
        require_once APPPATH.'services/CompanyProjectFixedBillingSchema.php';
        CompanyProjectFixedBillingSchema::ensure($this->db,db_prefix());
        require_once APPPATH.'services/CompanyProjectMilestonesSchema.php';
        CompanyProjectMilestonesSchema::ensure($this->db,db_prefix());
        require_once APPPATH.'services/CompanyProjectTimesheetsSchema.php';
        CompanyProjectTimesheetsSchema::ensure($this->db,db_prefix());
        require_once APPPATH.'services/CompanyProjectGanttSchema.php';
        CompanyProjectGanttSchema::ensure($this->db,db_prefix());
        require_once APPPATH.'services/CompanyProjectContactFilesSchema.php';
        CompanyProjectContactFilesSchema::ensure($this->db,db_prefix());
        require_once APPPATH.'services/CompanyGoogleMailOAuthSchema.php';
        CompanyGoogleMailOAuthSchema::ensure($this->db,db_prefix());
        require_once APPPATH.'services/CompanyPortalMailSchema.php';
        CompanyPortalMailSchema::ensure($this->db,db_prefix());
        require_once APPPATH.'services/CompanyExpenseRecurringSchema.php';
        CompanyExpenseRecurringSchema::ensure($this->db,db_prefix());
    }

    public function down(): void
    {
        // Preserve immutable billing, publication, mail and recurrence evidence.
    }
}
