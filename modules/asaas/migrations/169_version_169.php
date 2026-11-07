<?php
defined('BASEPATH') or exit('No direct script access allowed');
class Migration_Version_169 extends App_module_migration
{
    public function up()
    {
        require_once __DIR__.'/../libraries/Asaas/CompanyManualSettlementSchema.php';
        \Asaas\CompanyManualSettlementSchema::ensure($this->ci->db,db_prefix());
    }
}
