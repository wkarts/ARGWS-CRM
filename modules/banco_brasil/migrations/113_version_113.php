<?php
defined('BASEPATH') or exit('No direct script access allowed');
class Migration_Version_113 extends App_module_migration
{
    public function up()
    {
        // NOOP: reviewed component receipts use the additive core427 schema and canonical writer.
        // Existing origins, proof, cash receipts and immutable bank snapshots remain unchanged.
    }
}
