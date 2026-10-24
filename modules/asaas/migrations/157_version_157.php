<?php
defined('BASEPATH') or exit('No direct script access allowed');
class Migration_Version_157 extends App_module_migration
{
    public function up(){require_once __DIR__.'/../libraries/Asaas/CompanyOriginPolicy.php';\Asaas\CompanyOriginPolicy::ensure($this->ci->db,db_prefix(),true);}
    public function down(){throw new RuntimeException('asaas_origin_policy_destructive_downgrade_denied');}
}
