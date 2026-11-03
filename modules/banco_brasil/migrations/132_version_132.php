<?php
defined('BASEPATH') or exit('No direct script access allowed');
class Migration_Version_132 extends App_module_migration
{
    public function up()
    {
        // NOOP: the three schema probes share CI3/PDO empty-result semantics.
        // Validate only; never rewrite origins, publications or return bytes.
        require_once __DIR__.'/../libraries/BancoBrasil/CheckoutSchema.php';
        require_once __DIR__.'/../libraries/BancoBrasil/ApiOriginPolicy.php';
        require_once __DIR__.'/../libraries/BancoBrasil/ReturnArchiveSchema.php';
        \BancoBrasil\CheckoutSchema::ensure($this->ci->db,db_prefix(),false);
        \BancoBrasil\ApiOriginPolicy::ensure($this->ci->db,db_prefix(),false);
        \BancoBrasil\SchemaValidator::assertCompatible($this->ci->db,db_prefix(),\BancoBrasil\ReturnArchiveSchema::requirements());
    }
}
