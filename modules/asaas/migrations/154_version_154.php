<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Migration_Version_154 extends App_module_migration
{
    public function up()
    {
        require_once __DIR__ . '/../libraries/Asaas/WebhookTokenSchema.php';
        require_once __DIR__ . '/../libraries/Asaas/EnvironmentSettingsSchema.php';
        // Finish both additive ciphertext columns before exposing complete profile initialization.
        \Asaas\WebhookTokenSchema::ensure($this->ci->db, db_prefix(), true);
        \Asaas\EnvironmentSettingsSchema::ensure($this->ci->db, db_prefix());
    }

    public function down()
    {
        // Preserve ciphertext, hashes, both environment profiles and all financial evidence.
        // A code rollback must review legacy configuration; never copy one profile over another.
    }
}
