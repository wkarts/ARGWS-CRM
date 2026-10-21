<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Migration_Version_432 extends CI_Migration
{
    public function up(): void
    {
        require_once APPPATH.'services/CompanyContractDiscussionsSchema.php';
        CompanyContractDiscussionsSchema::ensure($this->db,db_prefix());
    }

    public function down(): void
    {
        // Preserve immutable provenance, receipts, snapshots and audience decisions.
    }
}
