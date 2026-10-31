<?php
defined('BASEPATH') or exit('No direct script access allowed');
class Migration_Version_128 extends App_module_migration
{
    public function up()
    {
        // NOOP: independent API/CNAB opt-ins live in the issuer's settings document.
        // Absence means both disabled; never infer activation or rewrite origins/history.
    }
}
