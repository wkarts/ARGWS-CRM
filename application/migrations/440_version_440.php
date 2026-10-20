<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Migration_Version_440 extends CI_Migration
{
    public function up(): void
    {
        require_once APPPATH.'services/CompanyInvoiceDraftsSchema.php';
        CompanyInvoiceDraftsSchema::ensure($this->db, db_prefix());
        require_once APPPATH.'services/CompanyInvoiceRecurringModesSchema.php';
        CompanyInvoiceRecurringModesSchema::ensure($this->db, db_prefix());
        require_once APPPATH.'services/CompanyInvoiceRecurringDraftsSchema.php';
        CompanyInvoiceRecurringDraftsSchema::ensure($this->db, db_prefix());
        require_once APPPATH.'services/CompanyInvoiceRecurringDiscoverySchema.php';
        CompanyInvoiceRecurringDiscoverySchema::ensure($this->db, db_prefix());
        require_once APPPATH.'services/CompanyProjectCopiesSchema.php';
        CompanyProjectCopiesSchema::ensure($this->db, db_prefix());
        require_once APPPATH.'services/CompanyTaskCopiesSchema.php';
        CompanyTaskCopiesSchema::ensure($this->db, db_prefix());
        require_once APPPATH.'services/CompanyPostRefundManualReversalSchema.php';
        CompanyPostRefundManualReversalSchema::ensure($this->db, db_prefix());
        require_once APPPATH.'services/CompanyTicketConnectSchema.php';
        CompanyTicketConnectSchema::ensure($this->db, db_prefix());
    }

    public function down(): void
    {
        // Preserve drafts, cursor, source snapshots, refund cycles and private connector evidence.
    }
}
