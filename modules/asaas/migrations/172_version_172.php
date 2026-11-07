<?php
defined('BASEPATH') or exit('No direct script access allowed');
class Migration_Version_172 extends App_module_migration
{
    public function up()
    {
        // NOOP: consume SDK0.2.64 pinned by the confirmed standalone module1.5.6.
        // Preserve all financial history and prior migrations; no provider or credential operation.
    }
}
