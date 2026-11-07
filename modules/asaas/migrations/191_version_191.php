<?php
defined('BASEPATH') or exit('No direct script access allowed');
class Migration_Version_191 extends App_module_migration
{
 public function up() { /* NOOP: immutable installment identity in explicit PIX refunds; no grants, adoption or financial writes. */ }
}
