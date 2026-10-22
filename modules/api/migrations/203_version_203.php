<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Migration_Version_203 extends App_module_migration
{
    public function up()
    {
        // NOOP: staff identity writes use the core global email constraint and transaction boundary.
        // No staff identities, memberships or permissions are rewritten by this module upgrade.
    }
}
