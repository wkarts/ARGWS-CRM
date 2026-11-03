<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Migration_Version_478 extends CI_Migration
{
    public function up(): void
    {
        require_once APPPATH.'services/CompanyProjectContactFilesSchema.php';
        CompanyProjectContactFilesSchema::ensureParentIndexes($this->db,db_prefix());
    }

    public function down(): void
    {
        throw new RuntimeException('native_company_schema_downgrade_requires_review');
    }
}
