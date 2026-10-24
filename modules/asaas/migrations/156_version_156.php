<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Migration_Version_156 extends App_module_migration
{
    public function up()
    {
        // NOOP: native environment subtabs only; preserve credentials, profiles and origins.
    }
}
