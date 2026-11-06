<?php
defined('BASEPATH') or exit('No direct script access allowed');
class Migration_Version_166 extends App_module_migration
{
    public function up()
    {
        // NOOP: legacy store writes target only the current generation; schema165 remains unchanged.
    }
}
