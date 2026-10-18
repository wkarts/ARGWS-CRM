<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Migration_Version_101 extends App_module_migration
{
    public function up()
    {
        // NOOP: issuer-scoped access/export enforcement is implemented in code.
        // No schema, business data, credentials or remote state is changed here.
    }
}
