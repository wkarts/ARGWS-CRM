<?php
defined('BASEPATH') or exit('No direct script access allowed');
class Migration_Version_203 extends App_module_migration
{
    public function up()
    {
        // NOOP: validate installment succession before refund authority; preserve all historical proofs.
    }
}
