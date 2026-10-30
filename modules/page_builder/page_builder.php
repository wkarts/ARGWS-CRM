<?php

defined('BASEPATH') or exit('No direct script access allowed');

/*
Module Name: Page Builder
Description: Construtor visual de páginas e landing pages do ARGWS CRM.
Version: 1.0.2
Requires at least: 3.0.*
Author: ulutfa
Author URI: https://codecanyon.net/user/ulutfa
*/

defined('PAGE_BUILDER_MODULE_NAME') or define('PAGE_BUILDER_MODULE_NAME', 'page_builder');
defined('PAGE_BUILDER_MODULE_VERSION') or define('PAGE_BUILDER_MODULE_VERSION', '1.0.2');

$CI = &get_instance();

/**
 * Load the helpers
 */
$CI->load->helper('security');
$CI->load->helper(PAGE_BUILDER_MODULE_NAME . '/' . PAGE_BUILDER_MODULE_NAME);
$CI->load->helper(PAGE_BUILDER_MODULE_NAME . '/' . PAGE_BUILDER_MODULE_NAME . '_setup');
$CI->load->helper(PAGE_BUILDER_MODULE_NAME . '/' . PAGE_BUILDER_MODULE_NAME . '_tree_explorer');
$CI->load->helper(PAGE_BUILDER_MODULE_NAME . '/' . PAGE_BUILDER_MODULE_NAME . '_php8_polyfill');

/**
 * Register language files, must be registered if the module is using languages
 */
register_language_files(PAGE_BUILDER_MODULE_NAME, [PAGE_BUILDER_MODULE_NAME]);

/**
 * Register activation module hook
 */
register_activation_hook(PAGE_BUILDER_MODULE_NAME, 'page_builder_module_activation_hook');
function page_builder_module_activation_hook()
{
    page_builder_install();
}

/**
 * Dactivation module hook
 */
register_deactivation_hook(PAGE_BUILDER_MODULE_NAME, 'page_builder_module_deactivation_hook');
function page_builder_module_deactivation_hook()
{
    page_builder_uninstall();
}

/**
 * Init module menu items in setup in admin_init hook
 * @return null
 */
hooks()->add_action('admin_init', 'page_builder_module_init_menu_items');
function page_builder_module_init_menu_items()
{
    $CI = &get_instance();
    if (has_permission('page_builder', '', 'edit') || has_permission('page_builder', '', 'view')) {
        $CI->app_menu->add_sidebar_menu_item(PAGE_BUILDER_MODULE_NAME, [
            'name' =>   _l('page_builder_menu_title'),
            'icon' => 'fa fa-building',
            'href' => admin_url('page_builder'),
            'position' => 100
        ]);
    }
}


/**
 * Handle permissions
 */
hooks()->add_action('admin_init', 'page_builder_permissions');
function page_builder_permissions()
{
    $capabilities = [];
    $capabilities['capabilities'] = [
        'edit' => _l('page_builder_permission_edit'),
    ];
    register_staff_capabilities('page_builder', $capabilities, _l('page_builder'));
}

// Handle other pages when no controller match i.e potential 404
$controller = $CI->router->fetch_class();
if (empty($controller)) {

    page_builder_maybe_redirect_to_dashboard();

    // Get the requested page
    $page = explode('?', $_SERVER['REQUEST_URI'])[0];
    $page = strpos($page, '.') === false ? (str_ends_with($page, '/') ?  str_replace('//', '/', $page . '/index.html') : $page . '.html') : $page;

    // Validate and Serve the page content
    page_builder_serve_page($page);
}


// Handle landing pages
if (empty(uri_string())) {
    $options = page_builder_get_settings();

    page_builder_maybe_redirect_to_dashboard($options);

    $landingpage = $options['landingpage'] ?? '';
    if (!empty($landingpage)) {

        // Validate and Serve the page content
        page_builder_serve_page($landingpage);
    }
}

// ensure the user is redirected to client portal after logging in and not landing page
hooks()->add_action('after_contact_login', function () {
    $CI = &get_instance();
    $options = page_builder_get_settings();
    if ($options['redirect_to_dashboard'] == 'yes') {
        if (!$CI->session->has_userdata('red_url')) {
            $CI->session->set_userdata([
                'red_url' => site_url('clients/'),
            ]);
        }
    }
});