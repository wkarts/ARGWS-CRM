<?php

defined('BASEPATH') or exit('No direct script access allowed');
/*
Module Name: Integração opcional PIGE
Description: Recepção autenticada e idempotente de solicitações PIGE; emissão financeira indisponível nesta etapa.
Version: 0.1.0
Requires at least: 3.0.0
Author: ARGWS
*/
register_activation_hook('pige_bridge', 'pige_bridge_install');
function pige_bridge_install()
{
    require __DIR__ . '/install.php';
}
