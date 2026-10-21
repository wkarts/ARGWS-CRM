<?php
defined('BASEPATH') or exit('No direct script access allowed');
class Migration_Version_116 extends App_module_migration
{
    public function up()
    {
        require_once __DIR__.'/../libraries/BancoBrasil/OriginationSchema.php';
        require_once __DIR__.'/../libraries/BancoBrasil/ReconciliationSchema.php';
        require_once __DIR__.'/../libraries/BancoBrasil/ApiSchema.php';
        require_once __DIR__.'/../libraries/BancoBrasil/ApiCnabContractSchema.php';
        \BancoBrasil\OriginationSchema::ensure($this->ci->db,db_prefix(),false);
        \BancoBrasil\ReconciliationSchema::ensure($this->ci->db,db_prefix(),false);
        \BancoBrasil\ApiSchema::ensure($this->ci->db,db_prefix(),false);
        \BancoBrasil\ApiCnabContractSchema::ensure($this->ci->db,db_prefix());
    }
    public function down() { /* Preserve immutable per-origin API/return declarations and all receipt evidence. */ }
}
