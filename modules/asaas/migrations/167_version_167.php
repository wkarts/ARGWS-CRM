<?php
defined('BASEPATH') or exit('No direct script access allowed');
class Migration_Version_167 extends App_module_migration
{
    public function up()
    {
        // NOOP: post-refund local actions validate the complete existing recreation chain.
    }
}
