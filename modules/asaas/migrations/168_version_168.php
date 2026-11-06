<?php
defined('BASEPATH') or exit('No direct script access allowed');
class Migration_Version_168 extends App_module_migration
{
    public function up()
    {
        // NOOP: partial manual receipts use the existing atomic receipt and audit ledgers.
    }
}
