<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Migration_Version_204 extends App_module_migration
{
    public function up()
    {
        // NOOP: the existing API administration list now checks the same
        // administrator authorization as create/edit/delete and the API guide.
        // Tokens, permissions, data and public API contracts remain unchanged.
    }
}
