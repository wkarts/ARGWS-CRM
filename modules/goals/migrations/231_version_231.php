<?php
defined('BASEPATH') or exit('No direct script access allowed');
/** Code-only private customer filtering; preserve all existing schema and data. */
class Migration_Version_231 extends App_module_migration
{
    public function up() { /* NOOP: customer privacy predicates. */ }
}
