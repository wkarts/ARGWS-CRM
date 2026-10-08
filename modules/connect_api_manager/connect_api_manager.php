<?php

defined('BASEPATH') or exit('No direct script access allowed');

/*
Module Name: Connect|API Gestão
Description: Administração, provisionamento e gerenciamento de instâncias do Connect|API.
Author: ARGWS
Version: 1.1.2
Requires at least: 2.9.4
*/

define('CONNECT_API_MANAGER_MODULE', 'connect_api_manager');
define('CONNECT_API_MANAGER_VERSION', '1.1.2');
define('CONNECT_API_MANAGER_PERMISSION', 'connect-api-manager');

register_activation_hook(CONNECT_API_MANAGER_MODULE, 'connect_api_manager_activate');
register_deactivation_hook(CONNECT_API_MANAGER_MODULE, 'connect_api_manager_deactivate');
register_uninstall_hook(CONNECT_API_MANAGER_MODULE, 'connect_api_manager_uninstall');
register_language_files(CONNECT_API_MANAGER_MODULE, [CONNECT_API_MANAGER_MODULE]);

if (!function_exists('connect_api_register_parent_menu')) {
    function connect_api_register_parent_menu()
    {
        static $registered = false;
        if ($registered) {
            return;
        }

        $CI = &get_instance();
        $CI->app_menu->add_sidebar_menu_item('connect-api', [
            'name'     => _l('connect_api_menu_root'),
            'position' => 78,
            'icon'     => 'fa fa-random',
        ]);
        $registered = true;
    }
}

hooks()->add_action('admin_init', 'connect_api_manager_register_permissions');
hooks()->add_action('admin_init', 'connect_api_manager_register_menu');
hooks()->add_filter('connect_api_manager_connector_instances', 'connect_api_manager_connector_instances_provider');

function connect_api_manager_activate()
{
    require_once __DIR__ . '/install.php';
    connect_api_manager_install();
}

function connect_api_manager_deactivate()
{
    // Configuração e credenciais são preservadas para reativação segura.
}

function connect_api_manager_uninstall()
{
    // Não remove credenciais/tabelas automaticamente para evitar perda acidental.
}

function connect_api_manager_register_permissions()
{
    $config = [];
    $config['capabilities'] = [
        'view'        => _l('connect_api_manager_permission_view'),
        'create'      => _l('connect_api_manager_permission_create'),
        'manage'      => _l('connect_api_manager_permission_manage'),
        'delete'      => _l('connect_api_manager_permission_delete'),
        'credentials' => _l('connect_api_manager_permission_credentials'),
    ];

    register_staff_capabilities(CONNECT_API_MANAGER_PERMISSION, $config, _l('connect_api_manager_title'));
}

function connect_api_manager_register_menu()
{
    if (!is_admin() && !staff_can('view', CONNECT_API_MANAGER_PERMISSION)) {
        return;
    }

    connect_api_register_parent_menu();
    $CI = &get_instance();
    $CI->app_menu->add_sidebar_children_item('connect-api', [
        'slug'     => 'connect-api-manager',
        'name'     => _l('connect_api_manager_menu'),
        'href'     => admin_url('connect_api_manager'),
        'position' => 1,
    ]);
}

function connect_api_manager_can($capability)
{
    return is_admin() || staff_can($capability, CONNECT_API_MANAGER_PERMISSION);
}

function connect_api_manager_connector_instances_provider($instances)
{
    if (!connect_api_manager_can('credentials')) {
        return $instances;
    }

    $CI = &get_instance();
    $CI->load->model(CONNECT_API_MANAGER_MODULE . '/connect_api_manager_model');
    $local = $CI->connect_api_manager_model->get_connector_instances();

    return array_merge(is_array($instances) ? $instances : [], $local);
}

function connect_api_manager_translate_state($state)
{
    $state = strtolower(trim((string) $state));
    $map = [
        'open' => 'Conectada',
        'connected' => 'Conectada',
        'connecting' => 'Conectando',
        'close' => 'Desconectada',
        'closed' => 'Desconectada',
        'disconnected' => 'Desconectada',
        'offline' => 'Offline',
        'online' => 'Online',
        'unknown' => 'Desconhecido',
    ];

    return $map[$state] ?? ($state === '' ? 'Desconhecido' : ucfirst($state));
}


function connect_api_manager_state_badge_class($state)
{
    $state = strtolower(trim((string) $state));
    if (in_array($state, ['open', 'connected', 'online'], true)) {
        return 'success';
    }
    if (in_array($state, ['connecting'], true)) {
        return 'warning';
    }
    if (in_array($state, ['close', 'closed', 'disconnected', 'offline'], true)) {
        return 'danger';
    }

    return 'default';
}

function connect_api_manager_namespace_hash()
{
    return strtolower(trim((string) get_option('connect_api_manager_namespace_hash')));
}

function connect_api_manager_namespace_prefix()
{
    $hash = connect_api_manager_namespace_hash();
    return $hash === '' ? 'conn-' : 'conn-' . $hash . '-';
}

function connect_api_manager_namespace_label()
{
    return rtrim(connect_api_manager_namespace_prefix(), '-');
}

function connect_api_manager_scope()
{
    return get_option('connect_api_manager_scope') === 'all' ? 'all' : 'module';
}


function connect_api_manager_format_phone($number)
{
    $digits = preg_replace('/\D+/', '', (string) $number);
    if ($digits === '') {
        return '-';
    }
    if (strpos($digits, '55') === 0 && strlen($digits) === 13) {
        return '+55 (' . substr($digits, 2, 2) . ') ' . substr($digits, 4, 5) . '-' . substr($digits, 9, 4);
    }
    if (strpos($digits, '55') === 0 && strlen($digits) === 12) {
        return '+55 (' . substr($digits, 2, 2) . ') ' . substr($digits, 4, 4) . '-' . substr($digits, 8, 4);
    }
    return '+' . $digits;
}
