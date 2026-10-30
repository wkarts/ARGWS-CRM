<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Migration_Version_430 extends CI_Migration
{
    public function up(): void
    {
        require_once APPPATH.'services/CompanyPortalSupportWriteSchema.php';
        CompanyPortalSupportWriteSchema::ensure($this->db,db_prefix());
    }

    public function down(): void
    {
        // Preserve immutable records, audience decisions and provenance.
    }
}
