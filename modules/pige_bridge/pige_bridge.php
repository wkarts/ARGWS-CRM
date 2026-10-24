<?php

defined('BASEPATH') or exit('No direct script access allowed');
/*
Module Name: Integração opcional PIGE
Description: Conexões PIGE por empresa com HMAC protegido e configuração explícita de fatura local; recursos financeiros dependem de gates próprios.
Version: 1.0.6
Requires at least: 4.7.3
Author: ARGWS
*/
register_activation_hook('pige_bridge', 'pige_bridge_install');
function pige_bridge_install()
{
    require __DIR__ . '/install.php';
}

hooks()->add_action('admin_init','pige_bridge_setup_menu');
function pige_bridge_setup_menu()
{
    if(!is_admin())return;
    $CI=&get_instance();
    // Normal CompanyRequestScope remains authoritative; no isolated-shell hook bypass.
    $CI->app_menu->add_setup_menu_item('pige-bridge',['name'=>'Integração PIGE — ponte CRM','href'=>admin_url('pige_bridge/settings'),'position'=>65]);
}
