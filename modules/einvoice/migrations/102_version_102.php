<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Migration_Version_102 extends App_module_migration
{
    public function up()
    {
        // NOOP: keep the build-version parser correction on a tracked native step.
        // No schema, business data, credentials or remote state is changed here.
    }
}
