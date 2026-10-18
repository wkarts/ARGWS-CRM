<?php
defined('BASEPATH') or exit('No direct script access allowed');
require_once dirname(__DIR__).'/libraries/BancoBrasil/LayoutSchema.php';
class Migration_Version_102 extends App_module_migration
{
    public function up() { \BancoBrasil\LayoutSchema::upgrade($this->ci->db,db_prefix()); }
    public function down() { throw new RuntimeException('bb_destructive_downgrade_not_supported'); }
}
