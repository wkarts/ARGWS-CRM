<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Migration_Version_128 extends App_module_migration
{
    public function up()
    {
        // NOOP: existing Webhooks administration actions now enforce the
        // configured view/create/edit/delete capabilities before reading or
        // writing. Historical grants, jobs, logs and event contracts are kept.
    }
}
