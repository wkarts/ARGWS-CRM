<?php
defined('BASEPATH') or exit('No direct script access allowed');
class Migration_Version_135 extends App_module_migration
{
    public function up()
    {
        // Additive private preparations; optional number reservations use the existing shared ledger.
        // This migration reserves nothing and never creates a remittance or payment.
        require_once __DIR__.'/../libraries/BancoBrasil/Cnab400PreparationSchema.php';
        \BancoBrasil\Cnab400PreparationSchema::upgrade($this->ci->db,db_prefix());
    }
}
