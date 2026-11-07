<?php
defined('BASEPATH') or exit('No direct script access allowed');
class Migration_Version_139 extends App_module_migration
{
    public function up()
    {
        // NOOP: scoped CNAB240 printable instrument from existing immutable origins.
        // Printing neither reserves numbers nor freezes remittances or records payments.
    }
}
