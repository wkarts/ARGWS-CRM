<?php
defined('BASEPATH') or exit('No direct script access allowed');
class Migration_Version_194 extends App_module_migration
{
 public function up() { /* NOOP: account-scoped recovery and explicit portal consent/send claims in existing journals; no automatic grants, HTTP or financial backfill. */ }
}
