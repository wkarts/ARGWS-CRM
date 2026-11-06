<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Migration_Version_489 extends CI_Migration
{
    public function up(): void
    {
        require_once APPPATH . 'services/NativeCompanySalesProjectionSchema.php';
        require_once APPPATH . 'services/NativeCompanySalesProjection.php';
        require_once APPPATH . 'services/NativeCompanyExpenseCategorySchema.php';
        require_once APPPATH . 'services/CompanyContractMetadataSchema.php';
        require_once APPPATH . 'services/CompanyLeadMetadataSchema.php';
        NativeCompanyExpenseCategorySchema::ensure($this->db, db_prefix());
        CompanyContractMetadataSchema::ensure($this->db, db_prefix());
        CompanyLeadMetadataSchema::ensure($this->db, db_prefix());
        NativeCompanySalesProjectionSchema::ensure($this->db, db_prefix());
        // A failed source verification leaves the native version marker pending.
        // Retrying rebuilds only the projection; document identity and amounts stay intact.
        (new NativeCompanySalesProjection($this->db, db_prefix()))->backfill();
    }

    public function down(): void
    {
        // Keep additive form data, projection and financial history on code rollback.
        // The native updater does not automatically downgrade its schema marker.
    }
}
