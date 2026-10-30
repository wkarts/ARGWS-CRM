<?php
defined('BASEPATH') or exit('No direct script access allowed');
/** Runtime contract106 adds BB adapter; origins/config use additive104/105 schema unchanged. */
class Migration_Version_106 extends App_module_migration
{
    public function up()
    {
        foreach(['pige_bridge_connections','pige_bridge_invoice_origins','pige_bridge_external_config','pige_bridge_charge_origins'] as $name)if(!$this->ci->db->table_exists(db_prefix().$name))throw new RuntimeException('pige_bridge_106_prior_schema_required');
    }
    public function down(){throw new RuntimeException('pige_bridge_106_destructive_downgrade_not_supported');}
}
