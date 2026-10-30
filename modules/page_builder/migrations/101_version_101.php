<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Migration_Version_101 extends App_module_migration
{
    function __construct()
    {
        parent::__construct();
    }

    public function up()
    {
        try {
            // Install first
            page_builder_install();

            // Setup the new themes for old tenants
            page_builder_import_default_templates(['candy', 'blaze', 'deck', 'flat', 'softland']);
        } catch (\Throwable $th) {
            throw new \Exception("Error migrating page builder to v1.1", 1);
        }
    }

    public function down()
    {
    }
}