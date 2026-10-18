<?php

defined('BASEPATH') or exit('No direct script access allowed');
require_once dirname(__DIR__) . '/services/IssuerSchema.php';

class Migration_Version_101 extends App_module_migration
{
    public function up()
    {
        foreach (IssuerSchema::statements(db_prefix()) as $statement) {
            if ($this->ci->db->query($statement) === false) {
                throw new RuntimeException('issuer_migration_failed');
            }
        }
    }

    public function down()
    {
        // Ownership, memberships, sharing and audit must never disappear on rollback.
        throw new RuntimeException('issuer_destructive_downgrade_not_supported');
    }
}
