<?php

defined('BASEPATH') or exit('No direct script access allowed');
require_once dirname(__DIR__, 3) . '/application/services/EssentialIssuerModule.php';

class Migration_Version_102 extends App_module_migration
{
    public function up()
    {
        // The module became essential in 1.0.2. No company, credentials, settings
        // or financial data is copied. Confirm the foundation before tracking it.
        EssentialIssuerModule::invalidateSchemaCache($this->ci->db);
        EssentialIssuerModule::assertSchema($this->ci->db, db_prefix());
    }

    public function down()
    {
        throw new RuntimeException('issuer_destructive_downgrade_not_supported');
    }
}
