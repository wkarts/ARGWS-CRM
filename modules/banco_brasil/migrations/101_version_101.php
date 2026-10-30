<?php
defined('BASEPATH') or exit('No direct script access allowed');
require_once dirname(__DIR__) . '/libraries/BancoBrasil/ModuleSchema.php';
require_once dirname(__DIR__) . '/libraries/BancoBrasil/SchemaValidator.php';
class Migration_Version_101 extends App_module_migration
{
    public function up()
    {
        foreach (\BancoBrasil\ModuleSchema::statements(db_prefix()) as $sql) {
            if ($this->ci->db->query($sql) === false) throw new RuntimeException('bb_migration_failed');
        }
        \BancoBrasil\SchemaValidator::assertCompatible($this->ci->db,db_prefix());
    }
    public function down() { throw new RuntimeException('bb_destructive_downgrade_not_supported'); }
}
