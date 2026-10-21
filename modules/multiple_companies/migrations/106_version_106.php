<?php
defined('BASEPATH') or exit('No direct script access allowed');
/** Preserve schema and existing contacts; isolate legacy identity synchronization. */
class Migration_Version_106 extends App_module_migration
{
    public function up() { /* NOOP: customer ownership checks only. */ }
}
