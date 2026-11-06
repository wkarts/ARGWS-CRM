<?php
defined('BASEPATH') or exit('No direct script access allowed');
class Migration_Version_163 extends App_module_migration
{
    public function up()
    {
        // NOOP: native cancellation after canonical unpaid remote deletion; existing audit stores the receipt.
    }
}
