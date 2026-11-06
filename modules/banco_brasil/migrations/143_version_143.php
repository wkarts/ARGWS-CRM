<?php
defined('BASEPATH') or exit('No direct script access allowed');
class Migration_Version_143 extends App_module_migration
{
    public function up()
    {
        // NOOP: explicit CNAB portal publication reuses the124 publication ledger. No automatic publication.
        require_once __DIR__.'/../libraries/BancoBrasil/CheckoutSchema.php';
        \BancoBrasil\CheckoutSchema::ensure($this->ci->db,db_prefix(),false);
    }
}
