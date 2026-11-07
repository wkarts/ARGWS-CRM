<?php
defined('BASEPATH') or exit('No direct script access allowed');
class Migration_Version_187 extends App_module_migration
{
 public function up() { /* NOOP: explicit per-company report capabilities; never grants permissions or changes financial data. */ }
}
