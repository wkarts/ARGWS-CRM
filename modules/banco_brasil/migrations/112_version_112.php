<?php
defined('BASEPATH') or exit('No direct script access allowed');
class Migration_Version_112 extends App_module_migration
{
    public function up()
    {
        // NOOP: fixed daily interest is an explicit immutable API profile/payload revision.
        // Existing profiles, invoice snapshots, bank numbers and financial receipts are preserved.
    }
}
