<?php
defined('BASEPATH') or exit('No direct script access allowed');
class Migration_Version_161 extends App_module_migration
{
    public function up()
    {
        // NOOP: explicit account-bound API v3 webhook CRUD, queue resumption and
        // sanitized operation history use existing encrypted tokens/audit tables.
        // No credentials, receiver, payment origin or financial record is rewritten.
    }
}
