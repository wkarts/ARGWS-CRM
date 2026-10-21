<?php
defined('BASEPATH') or exit('No direct script access allowed');
class Migration_Version_151 extends App_module_migration
{
    public function up()
    {
        require_once __DIR__.'/../libraries/Asaas/CompanyCheckoutSchema.php';
        require_once __DIR__.'/../libraries/Asaas/CompanyRemoteOperationsSchema.php';
        \Asaas\CompanySchema::ensure($this->ci->db,db_prefix(),false);
        \Asaas\CompanyRemoteOperationsSchema::ensure($this->ci->db,db_prefix(),false);
        \Asaas\CompanyCheckoutSchema::ensure($this->ci->db,db_prefix());
    }
    public function down() { /* Preserve payer intentions, references and hosted publication history. */ }
}
