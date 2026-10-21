<?php

defined('BASEPATH') or exit('No direct script access allowed');

/** Forward absorption of the former issuers module into the native company core. */
class Migration_Version_404 extends CI_Migration
{
    public function up()
    {
        require_once APPPATH . 'services/issuers/IssuerCore.php';
        IssuerCore::reconcile($this->db, db_prefix());
        require_once APPPATH . 'services/CompanyMailQueueSchema.php';
        CompanyMailQueueSchema::ensure($this->db, db_prefix());
        if (!IssuerCore::ready($this->db, db_prefix())) {
            throw new RuntimeException('company_core_upgrade_unconfirmed');
        }
    }
    public function down() { /* Preserve company ownership, access, documents and audit. */ }
}
