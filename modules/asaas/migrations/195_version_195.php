<?php
defined('BASEPATH') or exit('No direct script access allowed');
class Migration_Version_195 extends App_module_migration
{
 public function up() { /* NOOP: native portal consent and private QR recovery; canonical initial Pix receipt adapter uses existing immutable journals; no backfill or automatic grants. */ }
}
