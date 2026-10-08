<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Migration_Version_144 extends App_module_migration
{
    public function up()
    {
        // 1.4.4
        // - Adiciona flags de envio de email/celular do customer (settings do gateway).
        // - Sem alterações de schema (settings são registradas via gateway settings).
        // - Ajustes de hooks: cancel/delete invoice e recebimento manual (dinheiro) via receiveInCash.
    }
}
