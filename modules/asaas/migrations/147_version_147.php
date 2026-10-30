<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Migration_Version_147 extends App_module_migration
{
    public function up()
    {
        // Versioned callback routing/response correction. No schema or financial data changes.
    }

    public function down()
    {
        // No schema change to revert; preserve payment origins and all historical records.
    }
}
