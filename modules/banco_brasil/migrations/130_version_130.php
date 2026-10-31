<?php
defined('BASEPATH') or exit('No direct script access allowed');
class Migration_Version_130 extends App_module_migration
{
    public function up()
    {
        require_once __DIR__.'/../libraries/BancoBrasil/ReturnArchiveSchema.php';
        require_once __DIR__.'/../libraries/BancoBrasil/LayoutSchema.php';
        \BancoBrasil\SchemaValidator::assertCompatible($this->ci->db,db_prefix(),\BancoBrasil\LayoutSchema::requirements());
        \BancoBrasil\ReturnArchiveSchema::upgrade($this->ci->db,db_prefix());
    }
    public function down(){throw new RuntimeException('bb_destructive_downgrade_not_supported');}
}
