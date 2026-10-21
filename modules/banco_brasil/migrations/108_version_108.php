<?php
defined('BASEPATH') or exit('No direct script access allowed');
class Migration_Version_108 extends App_module_migration
{
    public function up()
    {
        require_once __DIR__.'/../libraries/BancoBrasil/ApiSchema.php';
        \BancoBrasil\ApiSchema::ensure($this->ci->db,db_prefix());
    }
}
