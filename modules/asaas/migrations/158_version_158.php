<?php
defined('BASEPATH') or exit('No direct script access allowed');
class Migration_Version_158 extends App_module_migration
{
    public function up()
    {
        // Capability-only native checkpoint; existing policy/proof/receipt ledgers are authoritative.
        require_once __DIR__.'/../libraries/Asaas/CompanyOriginPolicy.php';
        \Asaas\CompanyOriginPolicy::ensure($this->ci->db,db_prefix(),false);
    }
}
