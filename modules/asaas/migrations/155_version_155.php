<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Migration_Version_155 extends App_module_migration
{
    public function up()
    {
        require_once __DIR__.'/../libraries/Asaas/CompanyPreferencesSchema.php';
        // Additive only. No account, credential, financial origin or global preference is copied.
        // No environment is selected by migration. Explicit authorized selection is required.
        \Asaas\CompanyPreferencesSchema::ensure($this->ci->db,db_prefix(),true);
    }
    public function down()
    {
        // Preserve preferences and immutable issuance snapshots on code rollback.
    }
}
