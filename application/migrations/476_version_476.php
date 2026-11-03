<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Migration_Version_476 extends CI_Migration
{
    public function up(): void
    {
        require_once APPPATH.'services/NativeCompanyTaskItemSchema.php';
        NativeCompanyTaskItemSchema::ensure($this->db,db_prefix());
        require_once APPPATH.'services/NativeCompanyTicketReplyFileSchema.php';
        NativeCompanyTicketReplyFileSchema::ensure($this->db,db_prefix());
    }

    public function down(): void
    {
        throw new RuntimeException('native_company_schema_downgrade_requires_review');
    }
}
