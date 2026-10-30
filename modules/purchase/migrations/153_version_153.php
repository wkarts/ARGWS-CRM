<?php

defined('BASEPATH') or exit('No direct script access allowed');

/** purchase 1.5.3: canonical vendor profile filename migration; no database change. */
class Migration_Version_153 extends App_module_migration
{
    public function up()
    {
        // Filename-only migration; preserve every table, row and history.
    }
}
