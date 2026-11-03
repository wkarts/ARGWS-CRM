<?php

defined('BASEPATH') or exit('No direct script access allowed');

/** hr_payroll 1.1.0: canonical contribution asset/view filename migration; no database change. */
class Migration_Version_110 extends App_module_migration
{
    public function up()
    {
        // Filename-only migration; preserve every table, row and history.
    }
}
