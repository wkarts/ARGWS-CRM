<?php
defined('BASEPATH') or exit('No direct script access allowed');
class Migration_Version_205 extends App_module_migration
{
    public function up()
    {
        // NOOP: retain immutable paid allocations while recreating only the eligible group remainder.
    }
}
