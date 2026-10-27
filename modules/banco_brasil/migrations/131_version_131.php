<?php
defined('BASEPATH') or exit('No direct script access allowed');
class Migration_Version_131 extends App_module_migration
{
    public function up()
    {
        // NOOP: replay validates retained originals without repeating DDL.
        // No historic bank origin, content, occurrence or payment is rewritten.
        require_once __DIR__.'/../libraries/BancoBrasil/ReturnArchiveSchema.php';
        \BancoBrasil\SchemaValidator::assertCompatible($this->ci->db,db_prefix(),\BancoBrasil\ReturnArchiveSchema::requirements());
    }
}
