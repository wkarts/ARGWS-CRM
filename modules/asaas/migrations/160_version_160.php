<?php
defined('BASEPATH') or exit('No direct script access allowed');
class Migration_Version_160 extends App_module_migration
{
    public function up()
    {
        // NOOP: payer, hosted-link and refund adapters share the complete service
        // transaction table boundary, including origin policy and provider fences.
        // No account, origin, proof or financial record is rewritten.
    }
}
