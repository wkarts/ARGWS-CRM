<?php
defined('BASEPATH') or exit('No direct script access allowed');
class Migration_Version_134 extends App_module_migration
{
    public function up()
    {
        // NOOP: diagnostic-list availability does not own API/configuration readiness.
        // Reconfirm the separate ledger without rewriting migration133 or history.
        require_once __DIR__.'/../libraries/BancoBrasil/ReturnRejectionSchema.php';
        \BancoBrasil\SchemaValidator::assertCompatible($this->ci->db,db_prefix(),\BancoBrasil\ReturnRejectionSchema::requirements());
    }
}
