<?php
defined('BASEPATH') or exit('No direct script access allowed');
class Migration_Version_110 extends App_module_migration
{
    public function up()
    {
        // NOOP: explicit preparation of a new disabled remote PIGE connection; no credentials or consent created locally.
    }
}
