<?php
defined('BASEPATH') or exit('No direct script access allowed');
/** Customer-identity read boundary; no schema or existing records are changed. */
class Migration_Version_113 extends App_module_migration
{
    public function up() { /* NOOP: code-only customer privacy correction. */ }
}
