<?php
defined('BASEPATH') or exit('No direct script access allowed');
/*
Module Name: Banco do Brasil - Cobrança e CNAB 240
Description: Conexões isoladas por emitente, preparação CNAB 240 e revisão de retornos. Operação bancária depende de contrato validado e homologação.
Version: 1.0.1
Requires at least: 3.0.*
*/
define('BANCO_BRASIL_MODULE_NAME','banco_brasil');
register_activation_hook(BANCO_BRASIL_MODULE_NAME,'banco_brasil_activate');
hooks()->add_action('admin_init','banco_brasil_admin_init');
function banco_brasil_activate() { require __DIR__.'/install.php'; }
function banco_brasil_admin_init()
{
    register_staff_capabilities('banco_brasil',['capabilities'=>['view'=>'Visualizar','manage'=>'Gerenciar integrações','download'=>'Baixar remessas','import'=>'Importar retornos']], 'Banco do Brasil');
    if (staff_can('view','banco_brasil')) get_instance()->app_menu->add_sidebar_menu_item('banco_brasil',[
        'name'=>'Banco do Brasil','href'=>admin_url('banco_brasil'),'icon'=>'fa fa-bank','position'=>61,
    ]);
}
