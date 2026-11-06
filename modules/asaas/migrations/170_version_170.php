<?php
defined('BASEPATH') or exit('No direct script access allowed');
class Migration_Version_170 extends App_module_migration
{
    public function up()
    {
        require_once __DIR__.'/../libraries/Asaas/CompanyInvoiceEditSchema.php';
        \Asaas\CompanyInvoiceEditSchema::ensure($this->ci->db,db_prefix());
    }
}
