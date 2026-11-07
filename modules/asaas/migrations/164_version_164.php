<?php
defined('BASEPATH') or exit('No direct script access allowed');
class Migration_Version_164 extends App_module_migration
{
    public function up()
    {
        require_once __DIR__.'/../libraries/Asaas/CompanyRecreationSchema.php';
        \Asaas\CompanyRecreationSchema::ensure($this->ci->db,db_prefix());
    }
}
