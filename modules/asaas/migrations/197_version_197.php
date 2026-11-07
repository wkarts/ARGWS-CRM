<?php
defined('BASEPATH') or exit('No direct script access allowed');
class Migration_Version_197 extends App_module_migration
{
 public function up() { /* NOOP: durable consent withdrawal and one-shot cancellation use existing append-only journals; no backfill, grants or remote calls. */ }
}
