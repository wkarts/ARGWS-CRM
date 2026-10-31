<?php
defined('BASEPATH') or exit('No direct script access allowed');
class Migration_Version_129 extends App_module_migration
{
    public function up()
    {
        // NOOP: the module now inherits native CRM presentation and request CSRF.
        // No historical origin, channel decision or bank credential is rewritten.
    }
}
