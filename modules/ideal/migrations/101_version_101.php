<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Migration_Version_101 extends App_module_migration
{
    public function up()
    {
        // Deliberate NOOP: version the reversible catalog flag and protected
        // webhook-management forms. Preserve all options, payments and webhook
        // registrations; no schema mutation or provider request is required.
    }
}
