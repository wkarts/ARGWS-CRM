<?php

defined('BASEPATH') or exit('No direct script access allowed');
/*
Module Name: Emitentes — fundação cadastral
Description: Cadastro opt-in de empresas, vínculos e permissões. Não habilita operação financeira multiemitente.
Version: 1.0.1
Requires at least: 3.0.*
*/

define('ISSUERS_MODULE_NAME', 'issuers');
register_language_files(ISSUERS_MODULE_NAME, [ISSUERS_MODULE_NAME]);
register_activation_hook(ISSUERS_MODULE_NAME, 'issuers_activate');
get_instance()->load->helper('issuers/issuers');
hooks()->add_action('admin_init', 'issuers_admin_menu');

function issuers_activate()
{
    require __DIR__ . '/install.php';
}

function issuers_admin_menu()
{
    $CI = &get_instance();
    $CI->load->model('issuers/issuers_model');
    if (is_admin() || count($CI->issuers_model->available(get_staff_user_id())) > 0) {
        $CI->app_menu->add_sidebar_menu_item('issuers', [
            'name' => _l('issuers_title'), 'href' => admin_url('issuers'),
            'icon' => 'fa fa-building', 'position' => 59,
        ]);
    }
}
