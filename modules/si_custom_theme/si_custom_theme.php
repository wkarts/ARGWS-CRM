<?php
defined('BASEPATH') or exit('No direct script access allowed');
/*
Module Name: Dynamic Customized Theme | Tema Personalizado Dinâmico
Description: Tema avançado que fornecerá modelos predefinidos e fácil personalização de acordo com sua escolha.
Author: Sejal Infotech | Grupo Liquida I.A no Telegram
Author URI: https://t.me/crmperfex
Version: 1.0.8
Requires at least: 2.3.*
*/

define('SI_CUSTOM_THEME_MODULE_NAME', 'si_custom_theme');
define('SI_CUSTOM_THEME_UPLOAD_PATH', APP_MODULES_PATH . SI_CUSTOM_THEME_MODULE_NAME.'/uploads/');
$CI = &get_instance();
hooks()->add_action('app_admin_head', 'si_custom_theme_hook_admin_head');
hooks()->add_action('app_customers_head', 'si_custom_theme_hook_app_customers_head');
hooks()->add_action('app_external_form_head', 'si_custom_theme_hook_load_partial');
hooks()->add_action('app_admin_authentication_head', 'si_custom_theme_hook_load_partial');
hooks()->add_filter('module_'.SI_CUSTOM_THEME_MODULE_NAME.'_action_links', 'module_si_custom_theme_action_links');
hooks()->add_action('admin_init', 'si_custom_theme_hook_admin_init');
hooks()->add_action('app_admin_footer','si_custom_theme_hook_app_admin_footer');

/**
* Load the module helper
*/
$CI->load->helper(SI_CUSTOM_THEME_MODULE_NAME . '/si_custom_theme');

/**
* Load the module model
*/
$CI->load->model(SI_CUSTOM_THEME_MODULE_NAME . '/si_custom_theme_model');

/**
* Register activation module hook
*/
register_activation_hook(SI_CUSTOM_THEME_MODULE_NAME, 'si_custom_theme_activation_hook');
function si_custom_theme_activation_hook()
{
	$CI = &get_instance();
	require_once(__DIR__ . '/install.php');
}
/**
* Register language files, must be registered if the module is using languages
*/
register_language_files(SI_CUSTOM_THEME_MODULE_NAME, [SI_CUSTOM_THEME_MODULE_NAME]);

function module_si_custom_theme_action_links($actions)
{
	$actions[] = '<a href="' . admin_url('si_custom_theme') . '">' . _l('settings') . '</a>';
	return $actions;
}
/**
* Admin side styles will be set
* @return null
*/
function si_custom_theme_hook_admin_head()
{
	//$theme_id = get_option(SI_CUSTOM_THEME_MODULE_NAME.'_default_theme');
	$theme_id = get_instance()->si_custom_theme_model->get_staff_theme_id();
	si_custom_theme_render(['general', 'texts', 'tabs', 'buttons', 'admin', 'modals', 'tags'],$theme_id);
	si_ct_bg_image_render();
	if($theme_id!=1)
		echo '<link href="'.module_dir_url('si_custom_theme','assets/css/si_custom_theme_style.css').'" rel="stylesheet" />';
	si_custom_theme_custom_css('si_custom_theme_custom_admin_area');		
}
/**
* Clients side theme style
* @return null
*/
function si_custom_theme_hook_app_customers_head()
{
	//$theme_id = get_option(SI_CUSTOM_THEME_MODULE_NAME.'_default_clients_theme');
	$theme_id = get_instance()->si_custom_theme_model->get_client_theme_id();
	si_custom_theme_render(['general', 'texts', 'tabs', 'buttons', 'customers', 'modals'],$theme_id);
	si_ct_bg_image_render();
	if($theme_id!=1)
		echo '<link href="'.module_dir_url('si_custom_theme','assets/css/si_custom_theme_style_client.css').'" rel="stylesheet" />';
	si_custom_theme_custom_css('si_custom_theme_custom_clients_area');
	$script = '';
	if(!is_client_logged_in()){
		$show_header = (int)get_option(SI_CUSTOM_THEME_MODULE_NAME.'_customer_login_header');
		$show_footer = (int)get_option(SI_CUSTOM_THEME_MODULE_NAME.'_customer_login_footer');
		if(!$show_header)
			$script .= '$("body.customers nav").addClass("hide");';
		if(!$show_footer)
			$script .= '$("body footer").addClass("hide");';
	}elseif(is_client_logged_in() && (int)get_option(SI_CUSTOM_THEME_MODULE_NAME.'_enable_client_theme')){
		//add theme link client profile
		$script .= '$(".customers-nav-item-logout").before(\'<li class="customers-nav-item-custom-theme"><a href="'.site_url('si_custom_theme/client_theme').'">'._l('si_custom_staff_theme_menu').'</a></li>\')';
	}		
	if($script !='')
		echo '<script>$(document).ready(function(){'.$script.'});</script>' . PHP_EOL;
}
/**
* Custom CSS
* @param  string $main_area clients or admin area options
* @return null
*/
function si_custom_theme_custom_css($main_area)
{
	$clients_or_admin_area             = get_option($main_area);
	$custom_css_admin_and_clients_area = get_option('si_custom_theme_custom_clients_and_admin_area');
	if (!empty($clients_or_admin_area) || !empty($custom_css_admin_and_clients_area)) {
		echo '<style id="si_custom_theme_custom_css">' . PHP_EOL;
		if (!empty($clients_or_admin_area)) {
			$clients_or_admin_area = clear_textarea_breaks($clients_or_admin_area);
			echo ($clients_or_admin_area) . PHP_EOL;
		}
		if (!empty($custom_css_admin_and_clients_area)) {
			$custom_css_admin_and_clients_area = clear_textarea_breaks($custom_css_admin_and_clients_area);
			echo ($custom_css_admin_and_clients_area) . PHP_EOL;
		}
		echo '</style>' . PHP_EOL;
	}
}
/**
* General and buttons styles when no login
* @return null
*/
function si_custom_theme_hook_load_partial()
{
	si_custom_theme_render(['general', 'texts', 'buttons']);
	si_ct_bg_image_render();
}
/**
* Init module menu items in setup
* @return null
*/
function si_custom_theme_hook_admin_init()
{
    $capabilities = [];
    $capabilities['capabilities'] = [
        'view' => _l('permission_view'),
    ];
    register_staff_capabilities('si_custom_theme', $capabilities, _l('si_custom_theme'));

    $CI = &get_instance();
    if (is_admin() || has_permission('si_custom_theme', '', 'view')) {
        $CI->app_menu->add_setup_menu_item('si-custom-theme', [
            'href'     => admin_url('si_custom_theme'),
            'name'     => _l('si_custom_theme'),
            'position' => 60,
        ]);
    }

    if ((int) get_option(SI_CUSTOM_THEME_MODULE_NAME . '_enable_staff_theme')) {
        $CI->app_menu->add_sidebar_menu_item('si-custom-theme', [
            'href'     => admin_url('si_custom_theme/staff_theme'),
            'icon'     => 'fa fa-gear',
            'name'     => _l('si_custom_staff_theme_menu'),
            'position' => 60,
        ]);
    }

    if (is_admin() || has_permission('settings', '', 'view')) {
        $CI->app_tabs->add_settings_tab('si_custom_theme_settings', [
            'name'     => _l('si_ct_settings'),
            'view'     => 'si_custom_theme/si_custom_theme_settings',
            'position' => 60,
        ]);
    }
}
/*hook to set staff theme select url in top menu profile*/
function si_custom_theme_hook_app_admin_footer()
{
	$script = '';
	if(is_staff_logged_in() && (int)get_option(SI_CUSTOM_THEME_MODULE_NAME.'_enable_staff_theme')){
		$script .= '$(".header-logout").before(\'<li class="header-custom-theme"><a href="'.admin_url('si_custom_theme/staff_theme').'">'._l('si_custom_staff_theme_menu').'</a></li>\')';
		echo '<script>$(document).ready(function(){'.$script.'});</script>' . PHP_EOL;
	}
}
