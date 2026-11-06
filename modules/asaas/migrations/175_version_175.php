<?php
defined('BASEPATH') or exit('No direct script access allowed');
class Migration_Version_175 extends App_module_migration
{
    public function up()
    {
        // NOOP: explicit remote monitor uses current scoped accounts and immutable origins.
    }
}
