<?php
defined('BASEPATH') or exit('No direct script access allowed');
class Migration_Version_140 extends App_module_migration
{
    public function up()
    {
        // NOOP: printable CNAB400 instrument from an existing validated origin.
        // No number allocation, bank transmission, schema change or financial effect.
    }
}
