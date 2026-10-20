<?php
defined('BASEPATH') or exit('No direct script access allowed');
class Migration_Version_120 extends App_module_migration
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
        \BancoBrasil\ApiCnabContractSchema::ensure($this->ci->db,db_prefix(),false);
    }
    public function down() { /* NOOP schema: validate existing participants; preserve all original receipts and operational evidence. */ }
}
