<?php
defined('BASEPATH') or exit('No direct script access allowed');
class Migration_Version_111 extends App_module_migration
{
    public function up()
    {
        require_once __DIR__.'/../libraries/BancoBrasil/ApiWebhookSchema.php';
        \BancoBrasil\ApiWebhookSchema::ensure($this->ci->db,db_prefix());
        // Date instructions use the existing durable API operations ledger.
    }
    public function down() { /* Preserve receivers, immutable observations and instructions. */ }
}
