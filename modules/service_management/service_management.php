<?php

defined('BASEPATH') or exit('No direct script access allowed');

/*
Module Name: Gestão de Serviços 2024 v1.0.0
Description: Este módulo auxilia no rastreamento, registro e gerenciamento de seus processos de serviço, garantindo eficiência e controle operacional.
Version: 1.0.0
Requires at least: 2.3.*
Author:Wkarts
Author URI: https://t.me/wkartspro
*/

define('SERVICE_MANAGEMENT_MODULE_NAME', 'service_management');
define('SERVICE_MANAGEMENT_MODULE_UPLOAD_FOLDER', module_dir_path(SERVICE_MANAGEMENT_MODULE_NAME, 'uploads'));

/*add folder upload link on here*/
define('SERVICE_MANAGEMENT_PRODUCT_UPLOAD', module_dir_path(SERVICE_MANAGEMENT_MODULE_NAME, 'uploads/products/'));
define('SM_CONTRACT_FOLDER', module_dir_path(SERVICE_MANAGEMENT_MODULE_NAME, 'uploads/contracts/'));
define('SM_CONTRACT_ADDENDUM_FOLDER', module_dir_path(SERVICE_MANAGEMENT_MODULE_NAME, 'uploads/contract_addendums/'));
define('SM_CONTRACTS_UPLOADS_FOLDER', module_dir_path(SERVICE_MANAGEMENT_MODULE_NAME, 'uploads/contract_signs/'));

/*link view on here*/
define('SERVICE_MANAGEMENT_PRINT_ITEM', 'modules/service_management/uploads/print_item/');


hooks()->add_action('admin_init', 'service_management_permissions');
hooks()->add_action('app_admin_head', 'service_management_add_head_components');
hooks()->add_action('app_admin_footer', 'service_management_load_js');
hooks()->add_action('app_search', 'service_management_load_search');
hooks()->add_action('admin_init', 'service_management_module_init_menu_items');

/*add menu on client portal*/
hooks()->add_action('customers_navigation_end', 'init_service_management_portal_menu');

// invoice add stats
hooks()->add_action('invoice_add_customer_account', 'customer_account_stats');
hooks()->add_action('after_payment_added', 'sm_after_payment_added');

register_merge_fields('service_management/merge_fields/sm_contract_merge_fields');
register_merge_fields('service_management/merge_fields/sm_contract_addendum_merge_fields');
hooks()->add_filter('other_merge_fields_available_for', 'sm_contract_register_other_merge_fields');
hooks()->add_filter('other_merge_fields_available_for', 'sm_contract_addendum_register_other_merge_fields');

hooks()->add_action('app_customers_portal_head', 'service_management_portal_add_head_components');
hooks()->add_action('app_customers_portal_footer', 'service_management_portal_add_footer_components');
define('VERSION_SERVICE_MANAGEMENT', 100);

/**
* Register activation module hook
*/
register_activation_hook(SERVICE_MANAGEMENT_MODULE_NAME, 'service_management_module_activation_hook');

function service_management_module_activation_hook()
{
	$CI = &get_instance();
	require_once(__DIR__ . '/install.php');
}


/**
* Register language files, must be registered if the module is using languages
*/
register_language_files(SERVICE_MANAGEMENT_MODULE_NAME, [SERVICE_MANAGEMENT_MODULE_NAME]);


$CI = & get_instance();
$CI->load->helper(SERVICE_MANAGEMENT_MODULE_NAME . '/service_management');

/**
 * Init goals module menu items in setup in admin_init hook
 * @return null
 */
function service_management_module_init_menu_items()
{   
	 $CI = &get_instance();

	 /*add menu on here*/

	 if(has_permission('service_management','','view') ){
	 	
	 	$CI->app_menu->add_sidebar_menu_item('service_management', [
	 		'name'     => _l('service_management_name'),
	 		'icon'     => 'fa-brands fa-slideshare', 
	 		'position' => 5,
	 	]);
	 }

	 if(has_permission('service_management','','view')){
		 $CI->app_menu->add_sidebar_children_item('service_management', [
			'slug'     => 'sm_products_services',
			'name'     => _l('sm_products_services'),
			'icon'     => 'fa fa-th-list',
			'href'     => admin_url('service_management/product_management'),
			'position' => 1,
		]);
	 }

	 if(has_permission('service_management','','view')){
		 $CI->app_menu->add_sidebar_children_item('service_management', [
			'slug'     => 'sm_subscription_services',
			'name'     => _l('sm_subscription_services'),
			'icon'     => 'fa fa-repeat',
			'href'     => admin_url('service_management/subscription_services_management'),
			'position' => 1,
		]);
	 }

	 if(has_permission('service_management','','view')){
		 $CI->app_menu->add_sidebar_children_item('service_management', [
			'slug'     => 'sm_order_management',
			'name'     => _l('sm_order_management'),
			'icon'     => 'fa-solid fa-bag-shopping',
			'href'     => admin_url('service_management/service_managements'),
			'position' => 1,
		]);
	 }

	 if(has_permission('service_management','','view')){
	 	$CI->app_menu->add_sidebar_children_item('service_management', [
	 		'slug'     => 'sm_services_management',
	 		'name'     => _l('sm_services_management'),
	 		'icon'     => 'fa fa-calendar',
	 		'href'     => admin_url('service_management/product_services'),
	 		'position' => 1,
	 	]);
	 }

	 if(has_permission('service_management','','view')){
		 $CI->app_menu->add_sidebar_children_item('service_management', [
			'slug'     => 'sm_contracts',
			'name'     => _l('sm_contracts'),
			'icon'     => 'fa fa-navicon',
			'href'     => admin_url('service_management/manage_contract'),
			'position' => 1,
		]);
	 }
	 if(has_permission('service_management','','view')){
		 $CI->app_menu->add_sidebar_children_item('service_management', [
			'slug'     => 'sm_contract_addendum',
			'name'     => _l('sm_contract_addendum'),
			'icon'     => 'fa fa-microchip',
			'href'     => admin_url('service_management/manage_contract_addendum'),
			'position' => 1,
		]);
	 }


	if(has_permission('service_management','','view')){
		 $CI->app_menu->add_sidebar_children_item('service_management', [
			'slug'     => 'service_management_setting',
			'name'     => _l('sm_settings'),
			'icon'     => 'fa fa-cog menu-icon',
			'href'     => admin_url('service_management/setting?group=category'),
			'position' => 10,
		]);
	 }


}

	/**
	 * service_management load js
	 */
	function service_management_load_js(){    
		$CI = &get_instance();    
		$viewuri = $_SERVER['REQUEST_URI'];
		
		/*change this code*/
		if(!(strpos($viewuri,'admin/service_management/mo_work_order_manage') === false)){
			echo '<script src="' . module_dir_url(SERVICE_MANAGEMENT_MODULE_NAME, 'assets/plugins/frappe-gantt/frappe-gantt.min.js') . '"></script>';
		}

		if (!(strpos($viewuri, '/admin/service_management/product_detail') === false)) { 
			echo '<script src="' . module_dir_url(SERVICE_MANAGEMENT_MODULE_NAME, 'assets/plugins/simplelightbox/simple-lightbox.min.js') . '"></script>';
			echo '<script src="' . module_dir_url(SERVICE_MANAGEMENT_MODULE_NAME, 'assets/plugins/simplelightbox/simple-lightbox.jquery.min.js') . '"></script>';
			echo '<script src="' . module_dir_url(SERVICE_MANAGEMENT_MODULE_NAME, 'assets/plugins/simplelightbox/masonry-layout-vanilla.min.js') . '"></script>';

		}

	}


	/**
	 * service_management add head components
	 */
	function service_management_add_head_components(){    
		$CI = &get_instance();
		$viewuri = $_SERVER['REQUEST_URI'];

		/*change this code*/
		if(!(strpos($viewuri,'admin/service_management') === false)){
			echo '<link href="' . module_dir_url(SERVICE_MANAGEMENT_MODULE_NAME, 'assets/css/styles.css') . '?v=' . VERSION_SERVICE_MANAGEMENT. '"  rel="stylesheet" type="text/css" />';
		}

		if (!(strpos($viewuri, '/admin/service_management/product_detail') === false)) {
			echo '<link href="' . module_dir_url(SERVICE_MANAGEMENT_MODULE_NAME, 'assets/plugins/simplelightbox/simple-lightbox.min.css') . '"  rel="stylesheet" type="text/css" />';
			echo '<link href="' . module_dir_url(SERVICE_MANAGEMENT_MODULE_NAME, 'assets/plugins/simplelightbox/masonry-layout-vanilla.min.css') . '"  rel="stylesheet" type="text/css" />';
		}
	}



	/**
	 * service_management permissions
	 */
	function service_management_permissions()
	{

		$capabilities = [];

		$capabilities['capabilities'] = [
				'view'   => _l('permission_view') . '(' . _l('permission_global') . ')',
				'create' => _l('permission_create'),
				'edit'   => _l('permission_edit'),
				'delete' => _l('permission_delete'),
		];

		
		register_staff_capabilities('service_management', $capabilities, _l('service_management_name'));

	}

	/**
	 * init service management portal menu
	 * @return [type] 
	 */
	function init_service_management_portal_menu()
	{
		$item ='';
		if(is_client_logged_in()){
			if(get_option('service_management_display_on_portal') == 1){
				$item .= '<li class="customers-nav-item">';
				$item .= '<a href="'.site_url('service_management/service_management_client/service_managements').'">'._l("sm_service_management").'';        
				$item .= '</a>';
				$item .= '</li>';
			}
		}
		echo new_html_entity_decode($item);

	}

	/**
 * customer account stats
 * @return [type] 
 */
	function customer_account_stats($service_status=[])
	{
		$CI = &get_instance();
		$customer_account_stats = '';

		$customer_account_stats .= '<div class="row mbot25">
		<div class="col-md-2 list-status statement-bg  projects-status">
		<a href="'.site_url('service_management/service_management_client/service_managements/cancelled').'" class=" sm-portal-a-cancelled" >
		<h4 class="bold text-uppercase sm-portal-h-cancelled" >'._l('sm_cancelled').'</h4>
		<span class="bold sm-portal-span-cancelled" >'.app_format_number(isset($service_status['cancelled']) ? $service_status['cancelled'] : 0 , true).'</span>
		</a>
		</div>

		<div class="col-md-2 list-status projects-status">
		<a href="'.site_url('service_management/service_management_client/service_managements/service_has_been_renewal').'" class=" sm-portal-a-renewal" >
		<h4 class="bold text-uppercase sm-portal-h-cancelled" >'._l('sm_service_has_been_renewal').'</h4>
		<span class="bold sm-portal-span-cancelled" >'.app_format_number(isset($service_status['service_has_been_renewal']) ? $service_status['service_has_been_renewal'] : 0, true).'</span>
		</a>
		</div>

		<div class="col-md-2 list-status projects-status">
		<a href="'.site_url('service_management/service_management_client/service_managements/expired').'" class=" sm-portal-a-expired">
		<h4 class="bold text-uppercase sm-portal-h-cancelled" >'._l('sm_expired').'</h4>
		<span class="bold sm-portal-span-cancelled" >'.app_format_number(isset($service_status['expired']) ? $service_status['expired'] : 0, true).'</span>
		</a>
		</div>

		<div class="col-md-2 list-status projects-status">
		<a href="'.site_url('service_management/service_management_client/service_managements/pause').'" class=" sm-portal-a-pause" >
		<h4 class="bold text-uppercase text-success sm-portal-h-cancelled" >'._l('sm_pause').'</h4>
		<span class="bold sm-portal-span-cancelled" >'.app_format_number(isset($service_status['pause']) ? $service_status['pause'] : 0, true).'</span>
		</a>
		</div>
		<div class="col-md-2 list-status projects-status">
		<a href="'.site_url('service_management/service_management_client/service_managements/activate').'" class=" sm-portal-a-activate" >
		<h4 class="bold text-uppercase text-success sm-portal-h-cancelled" >'._l('sm_activate').'</h4>
		<span class="bold sm-portal-span-cancelled" >'.app_format_number(isset($service_status['activate']) ? $service_status['activate'] : 0, true).'</span>
		</a>
		</div>
		<div class="col-md-2 list-status projects-status">
		<a href="'.site_url('service_management/service_management_client/service_managements/complete').'" class=" sm-portal-a-complete">
		<h4 class="bold text-uppercase text-success sm-portal-h-cancelled" >'._l('sm_complete').'</h4>
		<span class="bold sm-portal-span-cancelled" >'.app_format_number(isset($service_status['complete']) ? $service_status['complete'] : 0, true).'</span>
		</a>
		</div>
		

		</div>';

		echo new_html_entity_decode($customer_account_stats);

	}

	/**
	 * sm after payment added
	 * @param  [type] $payment_id 
	 * @return [type]             
	 */
	function sm_after_payment_added($payment_id)
	{
		$CI = &get_instance();
		$CI->load->model('service_management/service_management_model');

		$CI->service_management_model->create_servicee_log_for_order($payment_id);
		
		return true;

	}

	/**
	 * sm_contract_register_other_merge_fields
	 * @param  [type] $for 
	 * @return [type]      
	 */
	function sm_contract_register_other_merge_fields($for)
	{
		$for[] = 'sm_contract';

		return $for;
	}

	/**
	 * sm contract addendum register other merge fields
	 * @param  [type] $for 
	 * @return [type]      
	 */
	function sm_contract_addendum_register_other_merge_fields($for)
	{
		$for[] = 'sm_contract_addendum';

		return $for;
	}

	/**
	 * service management portal add head components
	 * @return [type] 
	 */
	function service_management_portal_add_head_components() {
		$CI = &get_instance();
		$viewuri = $_SERVER['REQUEST_URI'];

		if (!(strpos($viewuri, 'service_management/service_management_client') === false)) {
			echo '<link href="' . module_dir_url(SERVICE_MANAGEMENT_MODULE_NAME, 'assets/css/styles.css') . '?v=' . VERSION_SERVICE_MANAGEMENT . '"  rel="stylesheet" type="text/css" />';
		}

		if (!(strpos($viewuri, 'service_management/service_management_client/products_service_managements') === false)) {
			echo '<link href="' . module_dir_url(SERVICE_MANAGEMENT_MODULE_NAME, 'assets/css/client_portals/products/product.css') . '?v=' . VERSION_SERVICE_MANAGEMENT . '"  rel="stylesheet" type="text/css" />';
		}

		if (!(strpos($viewuri, 'service_management/service_management_client/service_managements') === false)) {
			echo '<link href="' . module_dir_url(SERVICE_MANAGEMENT_MODULE_NAME, 'assets/css/client_portals/styles.css') . '?v=' . VERSION_SERVICE_MANAGEMENT . '"  rel="stylesheet" type="text/css" />';
		}

		if (!(strpos($viewuri, 'service_management/service_management_client/detail') === false)) {
			echo '<link href="' . module_dir_url(SERVICE_MANAGEMENT_MODULE_NAME, 'assets/css/client_portals/products/product.css') . '?v=' . VERSION_SERVICE_MANAGEMENT . '"  rel="stylesheet" type="text/css" />';
			echo '<link href="' . module_dir_url(SERVICE_MANAGEMENT_MODULE_NAME, 'assets/css/client_portals/products/product_detail.css') . '?v=' . VERSION_SERVICE_MANAGEMENT . '"  rel="stylesheet" type="text/css" />';
		}

		

	}

	/**
	 * service management portal add footer components
	 * @return [type] 
	 */
	function service_management_portal_add_footer_components() {
		$CI = &get_instance();
		$viewuri = $_SERVER['REQUEST_URI'];

		if (!(strpos($viewuri, 'service_management/service_management_client') === false)) {
			echo '<script src="' . module_dir_url(SERVICE_MANAGEMENT_MODULE_NAME, 'assets/js/client_portals/sm_client.js') . '"></script>';

		}

		if (!(strpos($viewuri, 'service_management/service_management_client/view_cart') === false)) {
			echo '<script type="text/javascript" src="' . site_url('assets/plugins/accounting.js/accounting.js') . '?v=' . VERSION_SERVICE_MANAGEMENT . '"></script>';
		}
	}



