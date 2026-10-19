<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Migration_Version_434 extends CI_Migration
{
    public function up(): void
    {
        require_once APPPATH.'services/CompanyPortalTaskWriteSchema.php';
        CompanyPortalTaskWriteSchema::ensure($this->db,db_prefix());
        require_once APPPATH.'services/CompanyOperationalAuthorCharset.php';
        CompanyOperationalAuthorCharset::ensure($this->db,db_prefix());
    }

    public function down(): void
    {
        // Preserve immutable provenance, receipts, snapshots and audience decisions.
    }
}
