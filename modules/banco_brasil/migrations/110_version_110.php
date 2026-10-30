<?php
defined('BASEPATH') or exit('No direct script access allowed');
class Migration_Version_110 extends App_module_migration
{
    public function up()
    {
        // NOOP: reconcile canonical Nosso Numero across API20 and CNAB17 representations.
        // No schema, observation, historical receipt or financial record is rewritten.
    }
}
