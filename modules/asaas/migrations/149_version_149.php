<?php
defined('BASEPATH') or exit('No direct script access allowed');
class Migration_Version_149 extends App_module_migration
{
    public function up()
    {
        require_once __DIR__.'/../libraries/Asaas/PaymentOriginSchema.php';
        require_once __DIR__.'/../libraries/Asaas/CompanySchema.php';
        \Asaas\PaymentOriginSchema::install($this->ci->db);
        \Asaas\CompanySchema::ensure($this->ci->db,db_prefix());
    }
    public function down() { /* Preserve origin, encrypted configuration, proofs and financial receipts. */ }
}
