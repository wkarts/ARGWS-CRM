<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Migration_Version_458 extends CI_Migration
{
    public function up(): void
    {
        require_once APPPATH.'services/CompanyClientProfileSchema.php';
        require_once APPPATH.'services/issuers/IssuerCertificateSchema.php';
        $ci = &get_instance();
        CompanyClientProfileSchema::ensure($ci->db, db_prefix());
        IssuerCertificateSchema::ensure($ci->db, db_prefix());
    }

    public function down(): void
    {
        // Preserve private customer profiles, encrypted certificates and audit history.
    }
}
