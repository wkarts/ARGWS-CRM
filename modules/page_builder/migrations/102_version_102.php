<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Migration_Version_102 extends App_module_migration
{
    function __construct()
    {
        parent::__construct();
    }

    public function up()
    {
        page_builder_install();
    }

    public function down()
    {
    }
}