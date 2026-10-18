<?php
defined('BASEPATH') or exit('No direct script access allowed');
/*
Module Name: Banco do Brasil - Cobrança e CNAB 240/400
Description: Conexões isoladas por emitente, perfis CNAB 240/400 e revisão de retornos. Operação bancária depende de contrato validado e homologação.
Version: 1.0.2
Requires at least: 3.0.*
*/
define('BANCO_BRASIL_MODULE_NAME','banco_brasil');
register_activation_hook(BANCO_BRASIL_MODULE_NAME,'banco_brasil_activate');
hooks()->add_action('admin_init','banco_brasil_admin_init');
function banco_brasil_activate() { require __DIR__.'/install.php'; }
function banco_brasil_admin_init()
{
    register_staff_capabilities('banco_brasil',['capabilities'=>['view'=>'Visualizar','manage'=>'Gerenciar integrações','download'=>'Baixar remessas','import'=>'Importar retornos']], 'Banco do Brasil');
    if (banco_brasil_has_company_access()) get_instance()->app_menu->add_sidebar_menu_item('banco_brasil',[
        'name'=>'Banco do Brasil','href'=>admin_url('banco_brasil'),'icon'=>'fa fa-bank','position'=>61,
    ]);
}

/** Contextual ACL is authoritative; a global role must neither grant nor veto it. */
function banco_brasil_has_company_access(): bool
{
    $CI = &get_instance();
    if (!$CI->app_modules->is_active('issuers')) return false;
    $CI->load->helper('issuers/issuers');
    if (!function_exists('issuer_resource_can')) return false;
    $CI->load->model('issuers/issuers_model');
    require_once __DIR__.'/libraries/BancoBrasil/Transaction.php';
    try { return \BancoBrasil\Transaction::quiet($CI->db,function()use($CI){
        foreach ($CI->issuers_model->available(get_staff_user_id()) as $row) {
            if (issuer_resource_can((int)((array)$row)['id'],get_staff_user_id(),'banco_brasil','view') === true) return true;
        }
        return false;
    }); } catch (Throwable $e) { return false; } // No SQL/error details in unrelated page menus.
}
