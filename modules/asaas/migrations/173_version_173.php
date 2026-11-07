<?php
defined('BASEPATH') or exit('No direct script access allowed');
class Migration_Version_173 extends App_module_migration
{
    public function up()
    {
        require_once __DIR__.'/../libraries/Asaas/CompanyCardPricingSchema.php';
        $CI=&get_instance();
        \Asaas\CompanyCardPricingSchema::ensure($CI->db,db_prefix());
    }
}
