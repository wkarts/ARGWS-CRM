<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Migration_Version_146 extends App_module_migration
{
    public function up()
    {
        require_once __DIR__ . '/../libraries/Asaas/PaymentOriginSchema.php';
        \Asaas\PaymentOriginSchema::install($this->ci->db);
    }

    public function down()
    {
        // Non-destructive rollback: preserve account identity and financial audit.
        // Restoring the previous application must not drop these tables.
    }
}
