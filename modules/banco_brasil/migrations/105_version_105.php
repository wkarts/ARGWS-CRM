<?php

defined('BASEPATH') or exit('No direct script access allowed');
class Migration_Version_105 extends App_module_migration
{
    public function up()
    {
        require_once __DIR__.'/../libraries/BancoBrasil/Transaction.php';
        require_once __DIR__.'/../libraries/BancoBrasil/OriginationSchema.php';
        \BancoBrasil\OriginationSchema::ensure($this->ci->db,db_prefix());
    }
}
