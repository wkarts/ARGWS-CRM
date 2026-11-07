<?php
defined('BASEPATH') or exit('No direct script access allowed');
class Migration_Version_204 extends App_module_migration
{
    public function up()
    {
        // NOOP: preserve full refund postings in installment succession; existing journals only.
    }
}
