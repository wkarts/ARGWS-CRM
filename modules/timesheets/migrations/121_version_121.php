<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Migration_Version_121 extends App_module_migration
{
    public function up()
    {
        // NOOP: staff authentication now requires one global email identity.
        // Core owns the identity constraint; no staff or company-link data is rewritten here.
    }
}
