<?php
defined('BASEPATH') or exit('No direct script access allowed');
class Migration_Version_153 extends App_module_migration
{
    public function up()
    {
        require_once __DIR__.'/../libraries/Asaas/CompanyCheckoutSchema.php';
        require_once __DIR__.'/../libraries/Asaas/CompanyRemoteOperationsSchema.php';
        require_once __DIR__.'/../libraries/Asaas/CompanyRefundSchema.php';
        \Asaas\CompanySchema::ensure($this->ci->db,db_prefix(),false);
        \Asaas\CompanyRemoteOperationsSchema::ensure($this->ci->db,db_prefix(),false);
        \Asaas\CompanyCheckoutSchema::ensure($this->ci->db,db_prefix(),false);
        \Asaas\CompanyRefundSchema::ensure($this->ci->db,db_prefix(),false);
        require_once APPPATH.'services/CompanyAsaasRefundSchema.php';
        \CompanyAsaasRefundSchema::ensure($this->ci->db,db_prefix(),false);
    }
    public function down() { /* NOOP schema: validate core437 posting table and module participants; preserve all evidence. */ }
}
