<?php
defined('BASEPATH') or exit('No direct script access allowed');
class Migration_Version_193 extends App_module_migration
{
 public function up() { /* NOOP: dedicated automatic Pix invoice reservation and GET recovery using existing origin/proof journals. No consent or financial job backfill. */ }
}
