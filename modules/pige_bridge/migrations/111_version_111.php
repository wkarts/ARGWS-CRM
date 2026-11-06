<?php
defined('BASEPATH') or exit('No direct script access allowed');
class Migration_Version_111 extends App_module_migration
{
    public function up()
    {
        // NOOP: explicit HMAC diagnostic for an already enabled connection; no configuration or consent change.
    }
}
