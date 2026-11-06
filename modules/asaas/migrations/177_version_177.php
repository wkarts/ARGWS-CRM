<?php
defined('BASEPATH') or exit('No direct script access allowed');
class Migration_Version_177 extends App_module_migration
{
    public function up()
    {
        // NOOP: reviewed legacy associations use existing immutable origin/proof/audit storage.
    }
}
