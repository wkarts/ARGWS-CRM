<?php
defined('BASEPATH') or exit('No direct script access allowed');
class Migration_Version_179 extends App_module_migration
{
    public function up()
    {
        // NOOP: legacy successors retain explicit reconciliation using existing lineage and policy journals.
    }
}
