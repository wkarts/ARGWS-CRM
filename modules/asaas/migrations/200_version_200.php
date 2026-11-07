<?php
defined('BASEPATH') or exit('No direct script access allowed');
class Migration_Version_200 extends App_module_migration
{
 public function up(){ /* NOOP: atomic mandate replacement uses existing proofs and worker policy; no history rewrite or activation. */ }
}
