<?php
defined('BASEPATH') or exit('No direct script access allowed');
class Migration_Version_141 extends App_module_migration
{
    public function up()
    {
        require_once __DIR__.'/../libraries/BancoBrasil/Cnab400GroupSchema.php';
        \BancoBrasil\Cnab400GroupSchema::upgrade($this->ci->db,db_prefix());
    }
}
