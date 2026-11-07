<?php
defined('BASEPATH') or exit('No direct script access allowed');
class Migration_Version_137 extends App_module_migration
{
    public function up()
    {
        // NOOP: native payment settings presentation and scoped command return.
        // Existing accounts, contracts, credentials and banking histories are unchanged.
    }
}
