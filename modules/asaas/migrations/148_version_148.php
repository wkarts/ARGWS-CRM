<?php
defined('BASEPATH') or exit('No direct script access allowed');
class Migration_Version_148 extends App_module_migration
{
    public function up() { require_once __DIR__.'/../libraries/Asaas/WebhookIntegrationSchema.php'; \Asaas\WebhookIntegrationSchema::install($this->ci->db); }
    public function down() { /* Preserve authentication scope and durable financial audit. */ }
}
