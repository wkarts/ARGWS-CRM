<?php
defined('BASEPATH') or exit('No direct script access allowed');
class Migration_Version_136 extends App_module_migration
{
    public function up()
    {
        // NOOP: repair native tab containment and return isolated denials as403
        // without the legacy alert/log/redirect helper. No schema/data change.
    }
}
