<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Migration_Version_406 extends CI_Migration
{
    public function up(): void
    {
        require_once APPPATH.'services/CompanyClientRequestSchema.php';
        CompanyClientRequestSchema::ensure($this->db, db_prefix());
    }
    public function down(): void
    {
        throw new RuntimeException('issuer_destructive_downgrade_not_supported');
    }
}
