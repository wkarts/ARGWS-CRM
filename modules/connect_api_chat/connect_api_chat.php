<?php

defined('BASEPATH') or exit('No direct script access allowed');

/*
Module Name: Connect|API Chat
Description: Caixa de atendimento colaborativa vinculada à instância ativa do Connect|API Conector.
Author: ARGWS
Version: 1.1.2
Requires at least: 2.9.4
*/

define('CONNECT_API_CHAT_MODULE', 'connect_api_chat');
define('CONNECT_API_CHAT_VERSION', '1.1.2');
define('CONNECT_API_CHAT_PERMISSION', 'connect-api-chat');

register_activation_hook(CONNECT_API_CHAT_MODULE, 'connect_api_chat_activate');
register_deactivation_hook(CONNECT_API_CHAT_MODULE, 'connect_api_chat_deactivate');
register_uninstall_hook(CONNECT_API_CHAT_MODULE, 'connect_api_chat_uninstall');
register_language_files(CONNECT_API_CHAT_MODULE, [CONNECT_API_CHAT_MODULE]);

hooks()->add_action('admin_init', 'connect_api_chat_register_permissions');
hooks()->add_action('admin_init', 'connect_api_chat_register_menu');
hooks()->add_action('app_admin_head', 'connect_api_chat_pwa_head');
hooks()->add_filter('module_connect_api_chat_action_links', 'connect_api_chat_action_links');

if (!function_exists('connect_api_register_parent_menu')) {
    function connect_api_register_parent_menu()
    {
        static $registered = false;
        if ($registered) return;
        $CI = &get_instance();
        $CI->app_menu->add_sidebar_menu_item('connect-api', [
            'name' => _l('connect_api_menu_root'),
            'position' => 78,
            'icon' => 'fa fa-random',
        ]);
        $registered = true;
    }
}


function connect_api_chat_pwa_head()
{
    if (get_option('connect_api_chat_pwa_enabled') === '0' || !connect_api_chat_can('view')) return;
    $uri = function_exists('uri_string') ? (string)uri_string() : '';
    if (strpos($uri, 'connect_api_chat') === false) return;
    echo '<link rel="manifest" crossorigin="use-credentials" href="' . html_escape(admin_url('connect_api_chat/manifest')) . '">';
    echo '<meta name="theme-color" content="#00a884">';
}

function connect_api_chat_activate()
{
    require_once __DIR__ . '/install.php';
    connect_api_chat_install_schema();
}

function connect_api_chat_deactivate() {}
function connect_api_chat_uninstall() {}

function connect_api_chat_action_links($actions)
{
    if (connect_api_chat_can('manage_settings')) {
        $actions[] = '<a href="' . admin_url('connect_api_chat/settings') . '">' . _l('connect_api_chat_settings') . '</a>';
    }
    return $actions;
}

function connect_api_chat_register_permissions()
{
    register_staff_capabilities(CONNECT_API_CHAT_PERMISSION, [
        'capabilities' => [
            'view' => _l('connect_api_chat_permission_view'),
            'send' => _l('connect_api_chat_permission_send'),
            'assign' => _l('connect_api_chat_permission_assign'),
            'view_all' => _l('connect_api_chat_permission_view_all'),
            'delete_message' => _l('connect_api_chat_permission_delete_message'),
            'close_conversation' => _l('connect_api_chat_permission_close_conversation'),
            'manage_settings' => _l('connect_api_chat_permission_settings'),
        ],
    ], _l('connect_api_chat_title'));
}

function connect_api_chat_register_menu()
{
    if (!connect_api_chat_can('view')) return;
    connect_api_register_parent_menu();
    $CI = &get_instance();
    $CI->app_menu->add_sidebar_children_item('connect-api', [
        'slug' => 'connect-api-chat',
        'name' => _l('connect_api_chat_menu'),
        'href' => admin_url('connect_api_chat'),
        'position' => 3,
    ]);
}

function connect_api_chat_can($capability)
{
    if (is_admin()) return true;
    if (!is_staff_logged_in()) return false;

    $allStaff = get_option('connect_api_chat_all_staff') !== '0';
    if ($allStaff && in_array($capability, ['view', 'send', 'assign', 'close_conversation'], true)) {
        return true;
    }
    return staff_can($capability, CONNECT_API_CHAT_PERMISSION);
}

function connect_api_chat_connector_available()
{
    if (function_exists('is_module_active')) {
        return (bool) is_module_active('connect_api_connector');
    }
    return defined('CONNECT_API_CONNECTOR_MODULE');
}

function connect_api_chat_json($payload, $status = 200)
{
    $CI = &get_instance();
    $CI->output->set_status_header($status);
    $CI->output->set_content_type('application/json', 'utf-8');
    $CI->output->set_header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    $CI->output->set_header('Pragma: no-cache');
    $CI->output->set_output(json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
}
