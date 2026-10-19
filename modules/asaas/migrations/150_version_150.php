<?php
defined('BASEPATH') or exit('No direct script access allowed');
class Migration_Version_150 extends App_module_migration
{
    public function up()
    {
        require_once __DIR__.'/../libraries/Asaas/CompanyRemoteOperationsSchema.php';
        \Asaas\CompanySchema::ensure($this->ci->db,db_prefix(),false);
        \Asaas\CompanyRemoteOperationsSchema::ensure($this->ci->db,db_prefix());
    }
    public function down() { /* Preserve remote intentions, observations and immutable history. */ }
}
