<?php
defined('BASEPATH') or exit('No direct script access allowed');
class Migration_Version_171 extends App_module_migration
{
    public function up()
    {
        // NOOP: tariff posting includes the existing manual-settlement guard in its owned transaction.
        // No DDL, data backfill, receipt, fee posting or external operation.
    }
}
