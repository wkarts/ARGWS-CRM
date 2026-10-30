<?php
defined('BASEPATH') or exit('No direct script access allowed');
class Migration_Version_107 extends App_module_migration
{
    public function up()
    {
        require_once __DIR__.'/../libraries/BancoBrasil/ReconciliationSchema.php';
        \BancoBrasil\ReconciliationSchema::ensure($this->ci->db,db_prefix());
    }
}
