<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Migration_Version_431 extends CI_Migration
{
    public function up(): void
    {
        require_once APPPATH.'services/CompanyPortalSupportFilesSchema.php';
        CompanyPortalSupportFilesSchema::ensure($this->db,db_prefix());
    }

    public function down(): void
    {
        // Preserve immutable records, contact authorship and audience decisions.
    }
}
