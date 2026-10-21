<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Migration_Version_455 extends CI_Migration
{
    public function up(): void
    {
        require_once APPPATH.'services/CompanyPurchasesSchema.php';
        $ci = &get_instance();
        CompanyPurchasesSchema::ensure($ci->db, db_prefix());
    }

    public function down(): void
    {
        // Preserve supplier, purchasing, payable and immutable settlement history.
        // No legacy adoption, automatic cancellation or financial backfill.
    }
}
