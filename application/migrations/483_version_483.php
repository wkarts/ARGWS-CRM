<?php

defined('BASEPATH') or exit('No direct script access allowed');

/** Add issuer-owned, idempotent client statement delivery receipts. */
class Migration_Version_483 extends CI_Migration
{
    public function up(): void
    {
        require_once APPPATH.'services/CompanyClientStatementDeliverySchema.php';
        CompanyClientStatementDeliverySchema::ensure($this->db, db_prefix());
    }

    public function down(): void
    {
        throw new RuntimeException('native_client_statement_delivery_downgrade_requires_review');
    }
}
