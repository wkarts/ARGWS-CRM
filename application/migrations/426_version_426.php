<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Migration_Version_426 extends CI_Migration
{
    public function up(): void
    {
        require_once APPPATH.'services/CompanySalesExtrasSchema.php';
        require_once APPPATH.'services/CompanyImportSchema.php';
        require_once APPPATH.'services/CompanyCurrencyUnits.php';
        require_once APPPATH.'services/CompanyFinancialJournal.php';
        CompanySalesExtrasSchema::ensure($this->db,db_prefix());
        CompanyImportSchema::ensure($this->db,db_prefix());
        CompanyCurrencyUnitSchema::ensure($this->db,db_prefix());
        CompanyFinancialJournalSchema::ensure($this->db,db_prefix());
        // These are explicitly observations at upgrade, never reconstructed originals.
        (new CompanyCurrencyUnits($this->db,db_prefix()))->captureObservedUsed();
        (new CompanyFinancialJournal($this->db,db_prefix()))->captureObservedBaselines();
    }

    public function down(): void
    {
        // Preserve private files, import receipts, immutable currency meanings and evidence.
    }
}
