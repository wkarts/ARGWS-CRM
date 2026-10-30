<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Migration_Version_107 extends App_module_migration
{
    public function up()
    {
        // 1.0.7: recuperação robusta de mídia pelo messageId do Connect|API; sem alteração de banco.
    }
}
