<?php
defined('BASEPATH') or exit('No direct script access allowed');
class Migration_Version_127 extends App_module_migration
{
    public function up()
    {
        // NOOP: complete, authorized read-only payment candidates for native invoice forms.
        // No schema/data changes, credential decryption, provider requests or payment activation.
    }
}
