<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Migration_Version_143 extends App_module_migration
{
    public function up()
    {
        // No database updates for 1.4.3
        // Patch: sincronização de cobrança (create/update) usando SDK-first + gravação do vínculo em asaas_payments_map.
    }
}
