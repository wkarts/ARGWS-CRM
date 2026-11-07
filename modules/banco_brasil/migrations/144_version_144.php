<?php
defined('BASEPATH') or exit('No direct script access allowed');
class Migration_Version_144 extends App_module_migration
{
    public function up()
    {
        // NOOP: classify rejected automatic CNAB title parameters before reporting HTTP success.
        // No schema, bank range, batch, invoice, payment or historical data is changed.
    }
}
