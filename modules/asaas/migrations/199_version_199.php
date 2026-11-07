<?php
defined('BASEPATH') or exit('No direct script access allowed');
class Migration_Version_199 extends App_module_migration
{
 public function up(){require_once __DIR__.'/../libraries/Asaas/CompanyPixFutureWorkerSchema.php';\Asaas\CompanyPixFutureWorkerSchema::ensure($this->ci->db,db_prefix(),true);}
}
