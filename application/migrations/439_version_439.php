<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Migration_Version_439 extends CI_Migration
{
    public function up(): void
    {
        require_once APPPATH.'services/CompanyFinancialApiSchema.php';
        CompanyFinancialApiSchema::ensure($this->db,db_prefix());
        require_once APPPATH.'services/CompanyInvoiceCopiesSchema.php';
        CompanyInvoiceCopiesSchema::ensure($this->db,db_prefix());
        require_once APPPATH.'services/CompanyPostRefundSchema.php';
        CompanyPostRefundSchema::ensure($this->db,db_prefix());
        require_once APPPATH.'services/CompanyPostRefundManualSchema.php';
        CompanyPostRefundManualSchema::ensure($this->db,db_prefix());
        require_once APPPATH.'services/CompanyTaskTimeBatchSchema.php';
        CompanyTaskTimeBatchSchema::ensure($this->db,db_prefix());
        require_once APPPATH.'services/CompanyConnectPairingSchema.php';
        CompanyConnectPairingSchema::ensure($this->db,db_prefix());
        require_once APPPATH.'services/CompanyPusherSchema.php';
        CompanyPusherSchema::ensure($this->db,db_prefix());
    }
    public function down(): void { /* Preserve financial, source, timer and connector evidence. */ }
}
