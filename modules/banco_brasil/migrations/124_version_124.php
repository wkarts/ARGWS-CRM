<?php
defined('BASEPATH') or exit('No direct script access allowed');
class Migration_Version_124 extends App_module_migration
{
    public function up()
    {
        require_once __DIR__.'/../libraries/BancoBrasil/CheckoutSchema.php';
        \BancoBrasil\CheckoutSchema::ensure($this->ci->db,db_prefix());
    }
    public function down() { /* Preserve publication history; no destructive rollback. */ }
}
