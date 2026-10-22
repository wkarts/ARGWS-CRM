<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Migration_Version_151 extends App_module_migration
{
    public function up()
    {
        // NOOP: vendor-only language filter and recipient merge field; no schema or data rewrite.
        // The native runner records 1.5.1. Existing templates and user preferences remain intact.
    }
}
