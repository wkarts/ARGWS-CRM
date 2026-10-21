<?php
defined('BASEPATH') or exit('No direct script access allowed');
class Migration_Version_103 extends App_module_migration
{
    public function up()
    {
        // NOOP: bind the Banco do Brasil administration to the native Companies context.
        // No schema or customer data changes are required.
    }
}
