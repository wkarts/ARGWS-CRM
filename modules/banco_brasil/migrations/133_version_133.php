<?php
defined('BASEPATH') or exit('No direct script access allowed');
class Migration_Version_133 extends App_module_migration
{
    public function up()
    {
        // Separate rejected-upload history; no accepted-file, origin or financial rewrite.
        require_once __DIR__.'/../libraries/BancoBrasil/ReturnRejectionSchema.php';
        \BancoBrasil\ReturnRejectionSchema::upgrade($this->ci->db,db_prefix());
    }
}
