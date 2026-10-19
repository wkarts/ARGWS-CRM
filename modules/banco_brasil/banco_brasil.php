<?php
defined('BASEPATH') or exit('No direct script access allowed');
/*
Module Name: Banco do Brasil - Cobrança e CNAB 240/400
Description: Conexões isoladas por emitente, perfis CNAB 240/400 e revisão de retornos. Operação bancária depende de contrato validado e homologação.
Version: 1.0.7
Requires at least: 3.0.*
*/
define('BANCO_BRASIL_MODULE_NAME','banco_brasil');
register_activation_hook(BANCO_BRASIL_MODULE_NAME,'banco_brasil_activate');
hooks()->add_action('admin_init','banco_brasil_admin_init');
function banco_brasil_activate() { require __DIR__.'/install.php'; }
function banco_brasil_admin_init()
{
    register_staff_capabilities('banco_brasil',['capabilities'=>['view'=>'Visualizar','manage'=>'Gerenciar integrações','generate'=>'Gerar remessa CNAB 240','download'=>'Baixar remessas','import'=>'Importar retornos','reconcile'=>'Conciliar retornos com evidência']], 'Banco do Brasil');
    if (banco_brasil_has_company_access()) get_instance()->app_menu->add_sidebar_menu_item('banco_brasil',[
        'name'=>'Banco do Brasil','href'=>admin_url('banco_brasil'),'icon'=>'fa fa-bank','position'=>61,
    ]);
}

/** Companies in the native core are authoritative; the optional legacy issuers UI is not a dependency. */
function banco_brasil_has_company_access(): bool
{
    $CI = &get_instance();
    if (!function_exists('issuer_resource_can')) $CI->load->helper('issuers');
    if (!class_exists('IssuerCore', false)) require_once APPPATH.'services/issuers/IssuerCore.php';
    if (!class_exists('IssuerRegistry', false)) require_once APPPATH.'services/issuers/IssuerRegistry.php';
    if (!function_exists('issuer_resource_can') || !IssuerCore::ready($CI->db, db_prefix())) return false;
    $selected = null;
    if (class_exists('CompanyRequestScope', false)) {
        $context = CompanyRequestScope::context();
        if (!is_object($context) || !method_exists($context, 'issuerId') || !is_int($context->issuerId())) return false;
        $selected = $context->issuerId();
    }
    require_once __DIR__.'/libraries/BancoBrasil/Transaction.php';
    try { return \BancoBrasil\Transaction::quiet($CI->db,function()use($CI,$selected){
        $registry = new IssuerRegistry($CI->db, db_prefix());
        foreach ($registry->available(get_staff_user_id()) as $row) {
            $id = (int)((array)$row)['id'];
            if (($selected === null || $id === $selected)
                && issuer_resource_can($id,get_staff_user_id(),'banco_brasil','view') === true) return true;
        }
        return false;
    }); } catch (Throwable $e) { return false; } // No SQL/error details in unrelated page menus.
}
