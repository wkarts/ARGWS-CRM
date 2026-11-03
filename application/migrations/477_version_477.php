<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Migration_Version_477 extends CI_Migration
{
    public function up(): void
    {
        require_once APPPATH.'services/NativeCompanyEstimateSignatureSchema.php';
        NativeCompanyEstimateSignatureSchema::ensure($this->db,db_prefix());
    }

    public function down(): void
    {
        throw new RuntimeException('native_company_schema_downgrade_requires_review');
    }
}
