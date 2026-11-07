<?php
defined('BASEPATH') or exit('No direct script access allowed');
class Migration_Version_190 extends App_module_migration
{
 public function up() {
  require_once __DIR__.'/../libraries/Asaas/CompanyPixInboxSchema.php';
  \Asaas\CompanyPixInboxSchema::ensure($this->ci->db,db_prefix(),true);
 }
}
