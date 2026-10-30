<?php
defined('BASEPATH') or exit('No direct script access allowed');
class Migration_Version_115 extends App_module_migration
{
    public function up()
    {
        // NOOP: monthly interest and first fixed/percentage discount use distinct immutable JSON profiles.
        // Existing profiles/fingerprints and financial authority are preserved; no old declaration is expanded.
    }
}
