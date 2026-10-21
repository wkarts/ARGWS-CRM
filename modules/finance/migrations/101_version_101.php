<?php
defined('BASEPATH') or exit('No direct script access allowed');

/** Code-only legacy financial input predicates. No data reclassification or ledger rewrite. */
class Migration_Version_101 extends App_module_migration
{
    public function up() { /* NOOP: explicit origin filters; preserve every existing row. */ }
}
