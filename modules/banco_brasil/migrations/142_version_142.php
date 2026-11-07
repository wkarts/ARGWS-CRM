<?php
defined('BASEPATH') or exit('No direct script access allowed');
class Migration_Version_142 extends App_module_migration
{
    public function up()
    {
        require_once __DIR__.'/../libraries/BancoBrasil/InvoiceIssuanceSchema.php';
        \BancoBrasil\InvoiceIssuanceSchema::upgrade($this->ci->db,db_prefix());
    }
}
