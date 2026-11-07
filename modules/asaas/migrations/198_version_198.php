<?php
defined('BASEPATH') or exit('No direct script access allowed');
class Migration_Version_198 extends App_module_migration
{
 public function up() { /* NOOP: reviewed native-series bindings, future-cycle claims and authorization identity use existing append-only journals; no automatic activation or backfill. */ }
}
