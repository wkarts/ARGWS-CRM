<?php
defined('BASEPATH') or exit('No direct script access allowed');
class Migration_Version_126 extends App_module_migration
{
    public function up()
    {
        // NOOP: include company Efí reservations in cross-provider origin fences.
        // No existing origin, account or financial record is rewritten.
    }
}
