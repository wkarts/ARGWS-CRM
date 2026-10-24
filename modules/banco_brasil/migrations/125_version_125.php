<?php
defined('BASEPATH') or exit('No direct script access allowed');
class Migration_Version_125 extends App_module_migration
{
    public function up()
    {
        require_once __DIR__.'/../libraries/BancoBrasil/ApiOriginPolicy.php';
        \BancoBrasil\ApiOriginPolicy::ensure($this->ci->db,db_prefix());
    }
    public function down() { /* Preserve immutable bridge ownership; no destructive rollback. */ }
}
