<?php
defined('BASEPATH') or exit('No direct script access allowed');
class Migration_Version_206 extends App_module_migration
{
    public function up()
    {
        // NOOP: freeze canonical paid-retention validation separately from original receipt evidence.
    }
}
