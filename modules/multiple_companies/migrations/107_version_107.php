<?php

defined('BASEPATH') or exit('No direct script access allowed');

/** multiple_companies 1.0.7: canonical modal filename migration; no database change. */
class Migration_Version_107 extends App_module_migration
{
    public function up()
    {
        // Filename-only migration; preserve every table, row and history.
    }
}
