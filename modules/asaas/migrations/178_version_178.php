<?php
defined('BASEPATH') or exit('No direct script access allowed');
class Migration_Version_178 extends App_module_migration
{
    public function up()
    {
        // NOOP: reviewed legacy removal/refund uses existing intent and immutable proof journals.
    }
}
