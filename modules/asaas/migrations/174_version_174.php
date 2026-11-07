<?php
defined('BASEPATH') or exit('No direct script access allowed');
class Migration_Version_174 extends App_module_migration
{
    public function up()
    {
        // NOOP: scoped local monitoring reads existing ledgers; no backfill or provider calls.
    }
}
